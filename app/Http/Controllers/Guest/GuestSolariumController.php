<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\RedeemSolariumRequest;
use App\Models\Member;
use App\Models\PendingSolariumRedemption;
use App\Services\SolariumRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GuestSolariumController extends Controller
{
    public function __construct(
        private SolariumRedemptionService $redemptions
    ) {}

    /**
     * Redeem solarium minutes. Requires a recent scan at a solarium of the
     * guest's gym; the minutes are reserved until the terminal confirms.
     *
     * Route: POST /api/guests/solarium/redeem
     */
    public function redeem(RedeemSolariumRequest $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        try {
            $redemption = $this->redemptions->reserve($member, (int) $request->validated('minutes'));
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'redemption_id' => $redemption->id,
                'status' => $redemption->status,
                'minutes' => $redemption->minutes,
                'expires_in_seconds' => PendingSolariumRedemption::EXPIRY_SECONDS,
                'remaining_minutes' => (int) $member->accessConfig()->value('solarium_minutes'),
            ],
        ]);
    }

    /**
     * Route: GET /api/guests/solarium/redemption/{id}/status
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $redemption = $this->findOwn($request, $id);

        if (! $redemption) {
            return $this->notFound();
        }

        if ($redemption->isExpired()) {
            $this->redemptions->expireStale($redemption->gym_id);
            $redemption->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'redemption_id' => $redemption->id,
                'status' => $redemption->status,
                'minutes' => $redemption->minutes,
                'failure_reason' => $redemption->failure_reason,
                'created_at' => $redemption->created_at->toIso8601String(),
                'acknowledged_at' => $redemption->acknowledged_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * The guest cancels a pending redemption and gets the minutes back.
     *
     * Route: DELETE /api/guests/solarium/redemption/{id}
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $redemption = $this->findOwn($request, $id);

        if (! $redemption) {
            return $this->notFound();
        }

        try {
            $this->redemptions->cancel($redemption);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'current_status' => $redemption->fresh()->status,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Einlösung abgebrochen, die Minuten wurden zurückgebucht.',
        ]);
    }

    private function findOwn(Request $request, int $id): ?PendingSolariumRedemption
    {
        return PendingSolariumRedemption::where('member_id', $request->user()->id)->find($id);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Einlösung nicht gefunden',
        ], 404);
    }
}
