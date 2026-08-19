<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Http\Requests\StationCheckInRequest;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Services\StationCheckInService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class CheckInController extends Controller
{
    public function __construct(
        private StationCheckInService $stationService
    ) {}

    /**
     * Get the latest active check-in for the authenticated member
     */
    public function getLatest(): JsonResponse
    {
        try {
            $member = request()->user();

            if (! $member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member nicht authentifiziert',
                ], 401);
            }

            // Find the latest check-in that hasn't been checked out yet
            $latestCheckin = CheckIn::where('member_id', $member->id)
                ->whereNull('check_out_time')
                ->where('check_in_time', '>=', now()->subDay())
                ->with(['gym', 'member', 'checkedInBy'])
                ->orderBy('check_in_time', 'desc')
                ->first();

            if (! $latestCheckin) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Kein aktiver Check-In gefunden',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $latestCheckin->id,
                    'member_id' => $latestCheckin->member_id,
                    'gym_id' => $latestCheckin->gym_id,
                    'check_in_time' => $latestCheckin->check_in_time,
                    'check_out_time' => $latestCheckin->check_out_time,
                    'check_in_method' => $latestCheckin->check_in_method,
                    'check_in_method_text' => $latestCheckin->check_in_method_text,
                    'duration' => $latestCheckin->duration,
                    'duration_formatted' => $latestCheckin->duration_formatted,
                    'created_at' => $latestCheckin->created_at,
                    'updated_at' => $latestCheckin->updated_at,
                    'gym' => $latestCheckin->gym ? [
                        'id' => $latestCheckin->gym->id,
                        'name' => $latestCheckin->gym->name,
                    ] : null,
                    'member' => [
                        'id' => $latestCheckin->member->id,
                        'first_name' => $latestCheckin->member->first_name,
                        'last_name' => $latestCheckin->member->last_name,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching latest check-in: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Abrufen des Check-Ins',
            ], 500);
        }
    }

    /**
     * End a check-in by setting the check_out_time
     */
    public function endCheckin(Request $request, $id): JsonResponse
    {
        try {
            $member = request()->user();

            if (! $member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member nicht authentifiziert',
                ], 401);
            }

            // Find the check-in
            $checkin = CheckIn::where('id', $id)
                ->where('member_id', $member->id)
                ->whereNull('check_out_time')
                ->first();

            if (! $checkin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-In nicht gefunden oder bereits beendet',
                ], 404);
            }

            // Check if check-in is within 6 hours (matching frontend logic)
            $checkinTime = Carbon::parse($checkin->check_in_time);
            $now = Carbon::now();
            $hoursDifference = $now->diffInHours($checkinTime);

            if ($hoursDifference > 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-In kann nur innerhalb von 6 Stunden beendet werden',
                ], 422);
            }

            // Update the check-in with check_out_time
            $checkin->update([
                'check_out_time' => $now,
            ]);

            // Reload the model to get fresh data with computed attributes
            $checkin->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Check-In erfolgreich beendet',
                'data' => [
                    'id' => $checkin->id,
                    'member_id' => $checkin->member_id,
                    'gym_id' => $checkin->gym_id,
                    'check_in_time' => $checkin->check_in_time,
                    'check_out_time' => $checkin->check_out_time,
                    'check_in_method' => $checkin->check_in_method,
                    'check_in_method_text' => $checkin->check_in_method_text,
                    'duration' => $checkin->duration,
                    'duration_formatted' => $checkin->duration_formatted,
                    'created_at' => $checkin->created_at,
                    'updated_at' => $checkin->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error ending check-in: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Beenden des Check-Ins',
            ], 500);
        }
    }

    /**
     * Check in or out by scanning the printed station code.
     *
     * Route: POST /api/pwa/member/checkin/toggle
     *
     * For gyms running without scanner hardware: a sheet at the counter carries
     * a static QR code holding the PWA URL plus the gym's station token. The
     * member scans it with their own phone, the PWA opens on an authenticated
     * session and posts here.
     *
     * The token authenticates the *station*, never the member — the member is
     * whoever the bearer token says. A photographed code therefore lets nobody
     * check in as anyone else; it only lets its holder check *themselves* in
     * from elsewhere, which is why a gym that needs proof of presence has to
     * keep its scanner hardware.
     *
     * Direction is decided server-side from the member's open check-in, so one
     * printed code covers both check-in and check-out.
     */
    public function toggleAtStation(StationCheckInRequest $request): JsonResponse
    {
        $member = $request->user();

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member nicht authentifiziert',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // Throttled per member, not per IP: a whole studio shares one NAT
        // address, and locking out the gym because one phone misbehaves would
        // be worse than the abuse it prevents.
        $throttleKey = 'station-checkin:'.$member->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele Versuche. Bitte warte einen Moment.',
                'error_code' => 'TOO_MANY_ATTEMPTS',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        try {
            $gym = Gym::where('slug', $request->input('gym_slug'))
                ->pwaEnabled()
                ->first();

            // An unknown gym, a disabled station and a wrong token all answer
            // the same way. Distinguishing them would turn this endpoint into
            // an oracle for guessing tokens.
            if (! $gym
                || ! $gym->hasCheckinStation()
                || ! $gym->matchesCheckinStationToken($request->input('station_token'))) {
                Log::warning('Station check-in rejected: unknown gym or invalid token', [
                    'gym_slug' => $request->input('gym_slug'),
                    'member_id' => $member->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Dieser Check-in-Code ist ungültig. Bitte wende dich an das Studio-Team.',
                    'error_code' => 'INVALID_STATION',
                ], 404);
            }

            $result = $this->stationService->toggle($gym, $member);

            $granted = $result['action'] !== 'denied';

            // A successful scan clears the throttle: the limit exists to slow
            // down guessing, not to cap how often a member may come and go.
            if ($granted) {
                RateLimiter::clear($throttleKey);
            }

            return response()->json([
                'success' => $granted,
                'message' => $result['message'],
                'error_code' => $result['code'],
                'data' => [
                    'action' => $result['action'],
                    'gym' => [
                        'id' => $gym->id,
                        'name' => $gym->name,
                        'slug' => $gym->slug,
                    ],
                    'checkin' => $result['checkin']
                        ? $this->presentCheckin($result['checkin'])
                        : null,
                ],
            ], $result['status']);

        } catch (\Exception $e) {
            Log::error('Station check-in failed: '.$e->getMessage(), [
                'member_id' => $member->id,
                'gym_slug' => $request->input('gym_slug'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Check-in fehlgeschlagen. Bitte versuche es erneut.',
                'error_code' => 'CHECKIN_FAILED',
            ], 500);
        }
    }

    /**
     * The check-in fields the PWA renders, in the shape the other endpoints of
     * this controller already return.
     *
     * @return array<string, mixed>
     */
    private function presentCheckin(CheckIn $checkin): array
    {
        return [
            'id' => $checkin->id,
            'member_id' => $checkin->member_id,
            'gym_id' => $checkin->gym_id,
            'check_in_time' => $checkin->check_in_time,
            'check_out_time' => $checkin->check_out_time,
            'check_in_method' => $checkin->check_in_method,
            'check_in_method_text' => $checkin->check_in_method_text,
            'duration' => $checkin->duration,
            'duration_formatted' => $checkin->duration_formatted,
            'created_at' => $checkin->created_at,
            'updated_at' => $checkin->updated_at,
        ];
    }
}
