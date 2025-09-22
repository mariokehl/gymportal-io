<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckInController extends Controller
{
    /**
     * Get the latest active check-in for the authenticated member
     */
    public function getLatest(): JsonResponse
    {
        try {
            $member = request()->user();

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member nicht authentifiziert'
                ], 401);
            }

            // Find the latest check-in that hasn't been checked out yet
            $latestCheckin = CheckIn::where('member_id', $member->id)
                ->whereNull('check_out_time')
                ->with(['gym', 'member', 'checkedInBy'])
                ->orderBy('check_in_time', 'desc')
                ->first();

            if (!$latestCheckin) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'Kein aktiver Check-In gefunden'
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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching latest check-in: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Abrufen des Check-Ins'
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

            if (!$member) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member nicht authentifiziert'
                ], 401);
            }

            // Find the check-in
            $checkin = CheckIn::where('id', $id)
                ->where('member_id', $member->id)
                ->whereNull('check_out_time')
                ->first();

            if (!$checkin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-In nicht gefunden oder bereits beendet'
                ], 404);
            }

            // Check if check-in is within 6 hours (matching frontend logic)
            $checkinTime = Carbon::parse($checkin->check_in_time);
            $now = Carbon::now();
            $hoursDifference = $now->diffInHours($checkinTime);

            if ($hoursDifference > 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Check-In kann nur innerhalb von 6 Stunden beendet werden'
                ], 422);
            }

            // Update the check-in with check_out_time
            $checkin->update([
                'check_out_time' => $now
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
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error ending check-in: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Beenden des Check-Ins'
            ], 500);
        }
    }
}
