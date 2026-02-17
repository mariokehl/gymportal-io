<?php

namespace App\Services;

use App\Events\MembershipActivated;
use App\Mail\PaymentFailedMail;
use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberStatusService
{
    /**
     * Handle status change actions for a member.
     * Refactored from MemberController to be reusable across the application.
     *
     * @param Member $member The member whose status changed
     * @param string $oldStatus The previous status
     * @param string $newStatus The new status
     * @param User|null $user The user who triggered the change (null for system changes)
     * @param string|null $triggeredBy The trigger source for automatic changes
     */
    public function handleStatusChangeActions(
        Member $member,
        string $oldStatus,
        string $newStatus,
        ?User $user = null,
        ?string $triggeredBy = null
    ): void {
        // Aktivierung von Pending
        if ($oldStatus === 'pending' && $newStatus === 'active') {
            $this->handlePendingToActive($member, $user, $triggeredBy);
        }

        // Inaktivierung
        if ($newStatus === 'inactive') {
            $this->handleToInactive($member, $user, $triggeredBy);
        }

        // Von Overdue zu Active
        if ($oldStatus === 'overdue' && $newStatus === 'active') {
            $this->handleOverdueToActive($member, $user, $triggeredBy);
        }

        // Zu Overdue
        if ($newStatus === 'overdue' && $oldStatus === 'active') {
            $this->handleActiveToOverdue($member, $user, $triggeredBy);
        }
    }

    /**
     * Handle transition from pending to active status.
     */
    private function handlePendingToActive(Member $member, ?User $user, ?string $triggeredBy): void
    {
        // Hole alle pending Mitgliedschaften, um Events dispatchen zu können
        $pendingMemberships = $member->memberships()
            ->where('memberships.status', 'pending')
            ->get();

        $activatedCount = $pendingMemberships->count();

        if ($activatedCount > 0) {
            // Aktiviere alle pending Mitgliedschaften
            $member->memberships()
                ->where('memberships.status', 'pending')
                ->update(['status' => 'active']);

            $activatedMembershipIds = $pendingMemberships->pluck('id')->toArray();

            // Event für jede aktivierte Mitgliedschaft dispatchen (z.B. Vertragserstellung)
            foreach ($pendingMemberships as $membership) {
                $membership->status = 'active'; // Status aktualisieren für den Event-Listener
                MembershipActivated::dispatch($membership);
            }

            $this->recordStatusChange($member, 'pending', 'active', $user, [
                'reason' => "Automatische Aktivierung von {$activatedCount} Mitgliedschaft(en)",
                'metadata' => [
                    'activated_memberships' => $activatedCount,
                    'activated_membership_ids' => $activatedMembershipIds,
                    'activated_at' => now()->toISOString(),
                    'action_type' => 'auto_activation',
                    'triggered_by' => $triggeredBy ?? 'manual'
                ]
            ]);
        }
    }

    /**
     * Handle transition to inactive status.
     */
    private function handleToInactive(Member $member, ?User $user, ?string $triggeredBy): void
    {
        // Pausiere alle aktiven Mitgliedschaften
        $pausedCount = $member->memberships()
            ->where('memberships.status', 'active')
            ->update(['status' => 'paused']);

        if ($pausedCount > 0) {
            $pausedMembershipIds = $member->memberships()
                ->where('memberships.status', 'paused')
                ->pluck('memberships.id')
                ->toArray();

            $this->recordStatusChange($member, 'active', 'paused', $user, [
                'reason' => "Mitgliedschaften pausiert wegen Mitgliedsinaktivierung",
                'metadata' => [
                    'paused_memberships' => $pausedCount,
                    'paused_membership_ids' => $pausedMembershipIds,
                    'triggered_by' => $triggeredBy ?? 'member_inactivation'
                ]
            ]);
        }
    }

    /**
     * Handle transition from overdue to active status.
     */
    private function handleOverdueToActive(Member $member, ?User $user, ?string $triggeredBy): void
    {
        // Reaktiviere pausierte Mitgliedschaften (prüfe über Status History)
        $recentOverduePause = MemberStatusHistory::where('member_id', $member->id)
            ->where('new_status', 'paused')
            ->where('metadata->triggered_by', 'payment_overdue')
            ->latest()
            ->first();

        if ($recentOverduePause) {
            $reactivatedCount = $member->memberships()
                ->where('memberships.status', 'paused')
                ->where('memberships.updated_at', '>=', $recentOverduePause->created_at)
                ->update(['status' => 'active']);

            if ($reactivatedCount > 0) {
                $reactivatedMembershipIds = $member->memberships()
                    ->where('memberships.status', 'active')
                    ->pluck('memberships.id')
                    ->toArray();

                $this->recordStatusChange($member, 'paused', 'active', $user, [
                    'reason' => "Mitgliedschaften reaktiviert nach Zahlungseingang",
                    'metadata' => [
                        'reactivated_memberships' => $reactivatedCount,
                        'reactivated_membership_ids' => $reactivatedMembershipIds,
                        'reactivated_at' => now()->toISOString(),
                        'triggered_by' => $triggeredBy ?? 'payment_resolved'
                    ]
                ]);
            }
        }
    }

    /**
     * Handle transition from active to overdue status.
     */
    private function handleActiveToOverdue(Member $member, ?User $user, ?string $triggeredBy): void
    {
        // Pausiere aktive Mitgliedschaften
        $pausedCount = $member->memberships()
            ->where('memberships.status', 'active')
            ->update(['status' => 'paused']);

        if ($pausedCount > 0) {
            $pausedMembershipIds = $member->memberships()
                ->where('memberships.status', 'paused')
                ->pluck('memberships.id')
                ->toArray();

            $this->recordStatusChange($member, 'active', 'paused', $user, [
                'reason' => "Mitgliedschaften pausiert wegen überfälliger Zahlung",
                'metadata' => [
                    'paused_memberships' => $pausedCount,
                    'paused_membership_ids' => $pausedMembershipIds,
                    'triggered_by' => $triggeredBy ?? 'payment_overdue'
                ]
            ]);
        }
    }

    /**
     * Handle failed payment from Mollie webhook.
     * Changes member status to overdue and sends notifications.
     *
     * @param Member $member The member whose payment failed
     * @param array $paymentData Additional payment data for context
     */
    public function handlePaymentFailed(Member $member, array $paymentData = []): void
    {
        $oldStatus = $member->status;

        // Only process if member is active
        if ($oldStatus !== 'active') {
            Log::info('Payment failed but member is not active, skipping status change', [
                'member_id' => $member->id,
                'current_status' => $oldStatus,
                'payment_data' => $paymentData
            ]);
            return;
        }

        // Update member status to overdue
        $member->update(['status' => 'overdue']);

        // Handle status change actions (pause memberships, etc.)
        $this->handleStatusChangeActions($member, $oldStatus, 'overdue', null, 'payment_overdue');

        // Record the status change
        MemberStatusHistory::recordAutomaticChange(
            $member,
            $oldStatus,
            'overdue',
            'payment_overdue',
            'Status auf Überfällig geändert wegen fehlgeschlagener Zahlung',
            [
                'payment_data' => $paymentData,
                'mollie_payment_id' => $paymentData['mollie_payment_id'] ?? null,
                'amount' => $paymentData['amount'] ?? null
            ]
        );

        // Send notification to gym staff
        $this->sendPaymentFailedNotification($member, $paymentData);

        // Send email to member
        $this->sendPaymentFailedEmail($member, $paymentData);

        Log::info('Payment failed handling completed', [
            'member_id' => $member->id,
            'old_status' => $oldStatus,
            'new_status' => 'overdue',
            'payment_data' => $paymentData
        ]);
    }

    /**
     * Send notification to gym staff about failed payment.
     */
    private function sendPaymentFailedNotification(Member $member, array $paymentData): void
    {
        try {
            $gym = $member->gym;

            // Notify all users who have this gym as their current gym
            $gymUsers = User::where('current_gym_id', $gym->id)
                ->where('is_blocked', false)
                ->whereNull('deleted_at')
                ->get();

            // Add the gym owner if they haven't selected this gym as their current gym
            if ($gym->owner_id && !$gymUsers->contains('id', $gym->owner_id)) {
                $owner = User::where('id', $gym->owner_id)
                    ->where('is_blocked', false)
                    ->whereNull('deleted_at')
                    ->first();

                if ($owner) {
                    $gymUsers->push($owner);
                }
            }

            foreach ($gymUsers as $user) {
                $user->notify(new PaymentFailedNotification($member, $gym, $paymentData));
            }

            Log::info('Payment failed notification sent to gym staff', [
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'notified_users' => $gymUsers->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed notification', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email to member about failed payment and access suspension.
     */
    private function sendPaymentFailedEmail(Member $member, array $paymentData): void
    {
        try {
            if (!$member->email) {
                Log::warning('Cannot send payment failed email - member has no email', [
                    'member_id' => $member->id
                ]);
                return;
            }

            $gym = $member->gym;

            Mail::to($member->email)->send(new PaymentFailedMail($member, $gym, $paymentData));

            Log::info('Payment failed email sent to member', [
                'member_id' => $member->id,
                'email' => $member->email
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment failed email to member', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record a status change in the history.
     */
    private function recordStatusChange(Member $member, string $oldStatus, string $newStatus, ?User $user, array $data): void
    {
        if ($user) {
            MemberStatusHistory::create([
                'member_id' => $member->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $data['reason'] ?? null,
                'changed_by' => $user->id,
                'metadata' => $data['metadata'] ?? []
            ]);
        } else {
            MemberStatusHistory::recordAutomaticChange(
                $member,
                $oldStatus,
                $newStatus,
                $data['metadata']['triggered_by'] ?? 'system',
                $data['reason'] ?? null,
                $data['metadata'] ?? []
            );
        }
    }
}
