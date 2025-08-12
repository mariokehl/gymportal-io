<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\Member;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckExpiringContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:check-expiring
                            {--days=30,14,7,3,1 : Comma-separated days before expiry to check}
                            {--send-notifications : Actually send notifications}
                            {--gym-id= : Process only specific gym}
                            {--verbose-output : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring membership contracts and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notificationDays = explode(',', $this->option('days'));
        $sendNotifications = $this->option('send-notifications');
        $gymId = $this->option('gym-id');
        $verbose = $this->option('verbose-output');

        $this->info("===========================================");
        $this->info("Checking Expiring Contracts");
        $this->info("Notification days: " . implode(', ', $notificationDays));
        $this->info("Send notifications: " . ($sendNotifications ? 'YES' : 'NO (Preview only)'));
        $this->info("===========================================\n");

        $stats = [
            'total_expiring' => 0,
            'can_cancel' => 0,
            'will_renew' => 0,
            'notifications_sent' => 0,
            'by_days' => [],
        ];

        foreach ($notificationDays as $days) {
            $days = (int) trim($days);
            $stats['by_days'][$days] = $this->checkExpiringInDays($days, $sendNotifications, $gymId, $verbose);
            $stats['total_expiring'] += $stats['by_days'][$days]['count'];
        }

        // Get additional statistics
        $this->getAdditionalStats($stats, $gymId);

        $this->printSummary($stats);

        return 0;
    }

    /**
     * Check memberships expiring in specific number of days
     */
    protected function checkExpiringInDays(int $days, bool $sendNotifications, ?int $gymId, bool $verbose): array
    {
        $targetDate = Carbon::today()->addDays($days);

        $query = Membership::where('status', 'active')
            ->whereDate('end_date', $targetDate)
            ->with(['member', 'membershipPlan']);

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $expiring = $query->get();

        $result = [
            'count' => $expiring->count(),
            'can_cancel' => 0,
            'will_renew' => 0,
            'notifications' => 0,
        ];

        if ($expiring->isEmpty()) {
            $this->info("No memberships expiring in {$days} days");
            return $result;
        }

        $this->info("Found {$result['count']} memberships expiring in {$days} days");

        foreach ($expiring as $membership) {
            $plan = $membership->membershipPlan;
            $member = $membership->member;

            // Check cancellation status
            $cancellationDeadline = $membership->end_date->copy()
                ->subDays($plan->cancellation_period_days ?? 30);

            $canStillCancel = Carbon::today() <= $cancellationDeadline;
            $willAutoRenew = !$membership->cancellation_date && !$canStillCancel;

            if ($canStillCancel) {
                $result['can_cancel']++;
            }

            if ($willAutoRenew) {
                $result['will_renew']++;
            }

            if ($verbose) {
                $this->displayMembershipInfo($membership, $days, $canStillCancel, $willAutoRenew);
            }

            // Send notification
            if ($sendNotifications) {
                if ($this->sendExpiryNotification($membership, $days, $canStillCancel, $willAutoRenew)) {
                    $result['notifications']++;
                }
            }
        }

        return $result;
    }

    /**
     * Display detailed membership information
     */
    protected function displayMembershipInfo(
        Membership $membership,
        int $daysUntilExpiry,
        bool $canStillCancel,
        bool $willAutoRenew
    ): void {
        $member = $membership->member;
        $plan = $membership->membershipPlan;

        $this->line("");
        $this->line("  Member: {$member->full_name} (#{$member->id})");
        $this->line("  Plan: {$plan->name}");
        $this->line("  End date: {$membership->end_date->format('d.m.Y')}");

        if ($membership->cancellation_date) {
            $this->line("  Status: <fg=yellow>Cancelled (ending {$membership->end_date->format('d.m.Y')})</>");
        } elseif ($willAutoRenew) {
            $this->line("  Status: <fg=green>Will auto-renew</>");
        } elseif ($canStillCancel) {
            $cancellationDeadline = $membership->end_date->copy()
                ->subDays($plan->cancellation_period_days ?? 30);
            $this->line("  Status: <fg=cyan>Can cancel until {$cancellationDeadline->format('d.m.Y')}</>");
        }
    }

    /**
     * Send expiry notification to member
     */
    protected function sendExpiryNotification(
        Membership $membership,
        int $daysUntilExpiry,
        bool $canStillCancel,
        bool $willAutoRenew
    ): bool {
        try {
            $member = $membership->member;
            $plan = $membership->membershipPlan;

            // Check if notification was already sent
            $alreadySent = $this->wasNotificationAlreadySent($membership, $daysUntilExpiry);
            if ($alreadySent) {
                return false;
            }

            // Determine notification type and content
            $notificationType = $this->determineNotificationType($daysUntilExpiry, $canStillCancel, $willAutoRenew);
            $content = $this->generateNotificationContent($membership, $daysUntilExpiry, $canStillCancel, $willAutoRenew);

            // Send email notification
            Mail::send('emails.membership-expiry', [
                'member' => $member,
                'membership' => $membership,
                'plan' => $plan,
                'daysUntilExpiry' => $daysUntilExpiry,
                'canStillCancel' => $canStillCancel,
                'willAutoRenew' => $willAutoRenew,
                'content' => $content,
            ], function ($message) use ($member, $notificationType) {
                $message->to($member->email)
                        ->subject($this->getEmailSubject($notificationType));
            });

            // Log notification
            $this->logNotification($membership, $daysUntilExpiry, $notificationType);

            Log::info('Expiry notification sent', [
                'membership_id' => $membership->id,
                'member_id' => $member->id,
                'days_until_expiry' => $daysUntilExpiry,
                'type' => $notificationType,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send expiry notification', [
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if notification was already sent
     */
    protected function wasNotificationAlreadySent(Membership $membership, int $days): bool
    {
        $key = "expiry_notification_{$days}days";
        $metadata = $membership->metadata ?? [];

        if (isset($metadata[$key])) {
            $sentDate = Carbon::parse($metadata[$key]);
            // Don't send again if sent within last 7 days
            return $sentDate->diffInDays(now()) < 7;
        }

        return false;
    }

    /**
     * Determine notification type
     */
    protected function determineNotificationType(int $days, bool $canCancel, bool $willRenew): string
    {
        if ($days >= 30) {
            return 'early_reminder';
        } elseif ($days >= 14 && $canCancel) {
            return 'cancellation_reminder';
        } elseif ($days >= 7 && $willRenew) {
            return 'renewal_notice';
        } elseif ($days <= 3) {
            return 'final_notice';
        } else {
            return 'standard_reminder';
        }
    }

    /**
     * Generate notification content
     */
    protected function generateNotificationContent(
        Membership $membership,
        int $days,
        bool $canCancel,
        bool $willRenew
    ): array {
        $plan = $membership->membershipPlan;
        $content = [];

        if ($willRenew) {
            $content['main'] = "Ihre Mitgliedschaft '{$plan->name}' verlängert sich automatisch in {$days} Tagen um weitere {$plan->commitment_months} Monate.";
            $content['action'] = "Falls Sie nicht verlängern möchten, können Sie Ihre Mitgliedschaft in Ihrem Konto kündigen.";
        } elseif ($canCancel) {
            $cancellationDeadline = $membership->end_date->copy()
                ->subDays($plan->cancellation_period_days ?? 30);
            $content['main'] = "Ihre Mitgliedschaft '{$plan->name}' läuft in {$days} Tagen aus.";
            $content['action'] = "Sie können noch bis zum {$cancellationDeadline->format('d.m.Y')} kündigen.";
        } else {
            $content['main'] = "Ihre Mitgliedschaft '{$plan->name}' endet in {$days} Tagen.";
            $content['action'] = "Bitte kontaktieren Sie uns, wenn Sie Ihre Mitgliedschaft fortsetzen möchten.";
        }

        $content['price'] = "Monatlicher Beitrag: {$plan->formatted_price}";

        return $content;
    }

    /**
     * Get email subject based on notification type
     */
    protected function getEmailSubject(string $type): string
    {
        return match($type) {
            'early_reminder' => 'Ihre Mitgliedschaft läuft bald aus',
            'cancellation_reminder' => 'Letzte Chance zur Kündigung Ihrer Mitgliedschaft',
            'renewal_notice' => 'Ihre Mitgliedschaft wird automatisch verlängert',
            'final_notice' => 'Wichtig: Ihre Mitgliedschaft endet in Kürze',
            default => 'Erinnerung: Ihre Mitgliedschaft',
        };
    }

    /**
     * Log notification in membership metadata
     */
    protected function logNotification(Membership $membership, int $days, string $type): void
    {
        $key = "expiry_notification_{$days}days";
        $metadata = $membership->metadata ?? [];
        $metadata[$key] = now()->toDateTimeString();
        $metadata['last_notification_type'] = $type;

        $membership->update(['metadata' => $metadata]);
    }

    /**
     * Get additional statistics
     */
    protected function getAdditionalStats(array &$stats, ?int $gymId): void
    {
        $query = Membership::where('status', 'active');

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        // Count memberships that can still cancel
        $stats['can_cancel'] = $query->clone()
            ->whereNull('cancellation_date')
            ->whereRaw('DATE_SUB(end_date, INTERVAL (SELECT cancellation_period_days FROM membership_plans WHERE membership_plans.id = memberships.membership_plan_id) DAY) >= CURDATE()')
            ->count();

        // Count memberships that will auto-renew
        $stats['will_renew'] = $query->clone()
            ->whereNull('cancellation_date')
            ->whereRaw('DATE_SUB(end_date, INTERVAL (SELECT cancellation_period_days FROM membership_plans WHERE membership_plans.id = memberships.membership_plan_id) DAY) < CURDATE()')
            ->where('end_date', '>', Carbon::today())
            ->count();
    }

    /**
     * Print summary
     */
    protected function printSummary(array $stats): void
    {
        $this->info("\n===========================================");
        $this->info("Summary");
        $this->info("===========================================");
        $this->info("Total expiring contracts: {$stats['total_expiring']}");
        $this->info("Can still cancel: {$stats['can_cancel']}");
        $this->info("Will auto-renew: {$stats['will_renew']}");

        if ($this->option('send-notifications')) {
            $totalNotifications = array_sum(array_column($stats['by_days'], 'notifications'));
            $this->info("Notifications sent: {$totalNotifications}");
        }

        $this->info("\nBreakdown by days:");
        foreach ($stats['by_days'] as $days => $data) {
            $this->info("  {$days} days: {$data['count']} contracts");
            if ($this->option('verbose-output')) {
                $this->info("    - Can cancel: {$data['can_cancel']}");
                $this->info("    - Will renew: {$data['will_renew']}");
                if ($this->option('send-notifications')) {
                    $this->info("    - Notifications: {$data['notifications']}");
                }
            }
        }

        $this->info("===========================================\n");
    }
}
