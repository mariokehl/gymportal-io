<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\Membership;
use App\Models\Member;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:cleanup-test-data
                            {--days=30 : Delete test data older than X days}
                            {--rollback-files : Clean up old rollback files}
                            {--orphaned : Clean up orphaned records}
                            {--duplicates : Remove duplicate payments}
                            {--logs : Clean up old log files}
                            {--dry-run : Preview what would be deleted}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up test data, orphaned records, and old files';

    protected array $stats = [
        'test_members' => 0,
        'test_payments' => 0,
        'orphaned_payments' => 0,
        'duplicate_payments' => 0,
        'rollback_files' => 0,
        'log_files' => 0,
        'total_space_freed' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysOld = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("===========================================");
        $this->info("Test Data Cleanup");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'PRODUCTION'));
        $this->info("Cleanup data older than: {$daysOld} days");
        $this->info("===========================================\n");

        // Confirmation prompt (unless forced or dry-run)
        if (!$dryRun && !$force) {
            if (!$this->confirm('This will permanently delete data. Continue?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }
        }

        try {
            // 1. Clean up test member data
            $this->cleanupTestMembers($daysOld, $dryRun);

            // 2. Clean up test payments
            $this->cleanupTestPayments($daysOld, $dryRun);

            // 3. Clean up orphaned records
            if ($this->option('orphaned')) {
                $this->cleanupOrphanedRecords($dryRun);
            }

            // 4. Remove duplicate payments
            if ($this->option('duplicates')) {
                $this->removeDuplicatePayments($dryRun);
            }

            // 5. Clean up rollback files
            if ($this->option('rollback-files')) {
                $this->cleanupRollbackFiles($daysOld, $dryRun);
            }

            // 6. Clean up old log files
            if ($this->option('logs')) {
                $this->cleanupLogFiles($daysOld, $dryRun);
            }

            // 7. Optimize database tables
            if (!$dryRun) {
                $this->optimizeDatabaseTables();
            }

        } catch (\Exception $e) {
            $this->error("Error during cleanup: " . $e->getMessage());
            Log::error('Test data cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        $this->printSummary();
        return 0;
    }

    /**
     * Clean up test member data
     */
    protected function cleanupTestMembers(int $daysOld, bool $dryRun): void
    {
        $this->info("Cleaning up test members...");

        $cutoffDate = Carbon::now()->subDays($daysOld);

        // Find test members (various patterns)
        $query = Member::where(function ($q) use ($cutoffDate) {
            $q->where('email', 'like', '%test%')
              ->orWhere('email', 'like', '%demo%')
              ->orWhere('email', 'like', '%example.com')
              ->orWhere('first_name', 'like', 'Test%')
              ->orWhere('last_name', 'like', 'Test%')
              ->orWhereJsonContains('widget_data->is_test', true)
              ->orWhereJsonContains('metadata->test_account', true);
        })->where('created_at', '<', $cutoffDate);

        $testMembers = $query->get();
        $this->stats['test_members'] = $testMembers->count();

        if ($testMembers->isEmpty()) {
            $this->line("  No test members found.");
            return;
        }

        $this->line("  Found {$testMembers->count()} test members to remove");

        if (!$dryRun) {
            DB::beginTransaction();
            try {
                foreach ($testMembers as $member) {
                    // Delete related data cascade
                    $this->deleteMemberCascade($member);
                }
                DB::commit();
                $this->info("  ✓ Deleted {$testMembers->count()} test members");
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } else {
            foreach ($testMembers as $member) {
                $this->line("  [DRY RUN] Would delete: {$member->full_name} ({$member->email})");
            }
        }
    }

    /**
     * Delete member and all related data
     */
    protected function deleteMemberCascade(Member $member): void
    {
        // Delete related records in correct order
        $member->payments()->delete();
        $member->paymentMethods()->delete();
        $member->checkIns()->delete();
        $member->courseBookings()->delete();
        $member->notificationRecipients()->delete();
        $member->widgetRegistrations()->delete();

        // Delete memberships and their payments
        foreach ($member->memberships as $membership) {
            $membership->payments()->delete();
            $membership->delete();
        }

        // Finally delete the member
        $member->delete();
    }

    /**
     * Clean up test payments
     */
    protected function cleanupTestPayments(int $daysOld, bool $dryRun): void
    {
        $this->info("Cleaning up test payments...");

        $cutoffDate = Carbon::now()->subDays($daysOld);

        // Find test payments
        $query = Payment::where(function ($q) {
            $q->where('amount', '=', 0.01) // Test amount
              ->orWhere('amount', '=', 1.00) // Common test amount
              ->orWhere('description', 'like', '%test%')
              ->orWhere('description', 'like', '%demo%')
              ->orWhereJsonContains('metadata->is_test', true)
              ->orWhereJsonContains('metadata->created_via', 'test');
        })->where('created_at', '<', $cutoffDate)
          ->where('status', '!=', 'paid'); // Don't delete paid payments

        $testPayments = $query->get();
        $this->stats['test_payments'] = $testPayments->count();

        if ($testPayments->isEmpty()) {
            $this->line("  No test payments found.");
            return;
        }

        $this->line("  Found {$testPayments->count()} test payments to remove");

        if (!$dryRun) {
            $deleted = $query->delete();
            $this->info("  ✓ Deleted {$deleted} test payments");
        } else {
            foreach ($testPayments->take(10) as $payment) {
                $this->line("  [DRY RUN] Would delete payment #{$payment->id}: {$payment->description} ({$payment->formatted_amount})");
            }
            if ($testPayments->count() > 10) {
                $this->line("  ... and " . ($testPayments->count() - 10) . " more");
            }
        }
    }

    /**
     * Clean up orphaned records
     */
    protected function cleanupOrphanedRecords(bool $dryRun): void
    {
        $this->info("Cleaning up orphaned records...");

        // Orphaned payments (no membership or member)
        $orphanedPayments = Payment::whereDoesntHave('membership')
            ->orWhereDoesntHave('member')
            ->get();

        $this->stats['orphaned_payments'] = $orphanedPayments->count();

        if ($orphanedPayments->isNotEmpty()) {
            $this->line("  Found {$orphanedPayments->count()} orphaned payments");

            if (!$dryRun) {
                Payment::whereDoesntHave('membership')
                    ->orWhereDoesntHave('member')
                    ->delete();
                $this->info("  ✓ Deleted orphaned payments");
            }
        }

        // Orphaned payment methods (no member)
        $orphanedPaymentMethods = PaymentMethod::whereDoesntHave('member')->count();

        if ($orphanedPaymentMethods > 0) {
            $this->line("  Found {$orphanedPaymentMethods} orphaned payment methods");

            if (!$dryRun) {
                PaymentMethod::whereDoesntHave('member')->delete();
                $this->info("  ✓ Deleted orphaned payment methods");
            }
        }

        // Orphaned memberships (no member)
        $orphanedMemberships = Membership::whereDoesntHave('member')->count();

        if ($orphanedMemberships > 0) {
            $this->line("  Found {$orphanedMemberships} orphaned memberships");

            if (!$dryRun) {
                Membership::whereDoesntHave('member')->delete();
                $this->info("  ✓ Deleted orphaned memberships");
            }
        }

        if ($orphanedPayments->isEmpty() && $orphanedPaymentMethods == 0 && $orphanedMemberships == 0) {
            $this->line("  No orphaned records found.");
        }
    }

    /**
     * Remove duplicate payments
     */
    protected function removeDuplicatePayments(bool $dryRun): void
    {
        $this->info("Removing duplicate payments...");

        // Find duplicate payments (same member, amount, date, status)
        $duplicates = DB::table('payments')
            ->select('member_id', 'membership_id', 'amount', 'due_date', 'status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('member_id')
            ->whereNotNull('due_date')
            ->groupBy('member_id', 'membership_id', 'amount', 'due_date', 'status')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->line("  No duplicate payments found.");
            return;
        }

        $totalDuplicates = 0;

        foreach ($duplicates as $duplicate) {
            // Keep the first, delete the rest
            $payments = Payment::where('member_id', $duplicate->member_id)
                ->where('membership_id', $duplicate->membership_id)
                ->where('amount', $duplicate->amount)
                ->where('due_date', $duplicate->due_date)
                ->where('status', $duplicate->status)
                ->orderBy('id')
                ->get();

            $toDelete = $payments->skip(1); // Keep first one
            $totalDuplicates += $toDelete->count();

            if (!$dryRun) {
                foreach ($toDelete as $payment) {
                    $payment->delete();
                }
            } else {
                $this->line("  [DRY RUN] Would delete {$toDelete->count()} duplicates for member #{$duplicate->member_id}");
            }
        }

        $this->stats['duplicate_payments'] = $totalDuplicates;

        if (!$dryRun) {
            $this->info("  ✓ Deleted {$totalDuplicates} duplicate payments");
        } else {
            $this->line("  [DRY RUN] Total duplicates to remove: {$totalDuplicates}");
        }
    }

    /**
     * Clean up old rollback files
     */
    protected function cleanupRollbackFiles(int $daysOld, bool $dryRun): void
    {
        $this->info("Cleaning up rollback files...");

        $cutoffDate = Carbon::now()->subDays($daysOld);
        $files = Storage::files(''); // Root of storage/app
        $rollbackFiles = array_filter($files, function($file) {
            return str_starts_with(basename($file), 'scheduler_rollback_');
        });

        $oldFiles = [];
        $totalSize = 0;

        foreach ($rollbackFiles as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));
            if ($lastModified < $cutoffDate) {
                $oldFiles[] = $file;
                $totalSize += Storage::size($file);
            }
        }

        $this->stats['rollback_files'] = count($oldFiles);
        $this->stats['total_space_freed'] += $totalSize;

        if (empty($oldFiles)) {
            $this->line("  No old rollback files found.");
            return;
        }

        $sizeInMB = round($totalSize / 1024 / 1024, 2);
        $this->line("  Found " . count($oldFiles) . " old rollback files ({$sizeInMB} MB)");

        if (!$dryRun) {
            foreach ($oldFiles as $file) {
                Storage::delete($file);
            }
            $this->info("  ✓ Deleted " . count($oldFiles) . " rollback files");
        } else {
            foreach (array_slice($oldFiles, 0, 5) as $file) {
                $this->line("  [DRY RUN] Would delete: " . basename($file));
            }
            if (count($oldFiles) > 5) {
                $this->line("  ... and " . (count($oldFiles) - 5) . " more");
            }
        }
    }

    /**
     * Clean up old log files
     */
    protected function cleanupLogFiles(int $daysOld, bool $dryRun): void
    {
        $this->info("Cleaning up old log files...");

        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays($daysOld);
        $oldFiles = [];
        $totalSize = 0;

        // Scheduler-specific logs
        $schedulerLogs = [
            'scheduler-payments.log',
            'scheduler-retry.log',
            'scheduler-expiring.log',
            'scheduler-sepa.log',
            'scheduler-cleanup.log',
        ];

        foreach ($schedulerLogs as $logFile) {
            $fullPath = $logPath . '/' . $logFile;
            if (file_exists($fullPath)) {
                $lastModified = Carbon::createFromTimestamp(filemtime($fullPath));
                $size = filesize($fullPath);

                // For active log files, we truncate instead of delete
                if ($size > 100 * 1024 * 1024) { // > 100MB
                    $oldFiles[] = [
                        'path' => $fullPath,
                        'action' => 'truncate',
                        'size' => $size
                    ];
                    $totalSize += $size;
                }
            }
        }

        // Old Laravel logs (rotated)
        $laravelLogs = glob($logPath . '/laravel-*.log');
        foreach ($laravelLogs as $file) {
            $lastModified = Carbon::createFromTimestamp(filemtime($file));
            if ($lastModified < $cutoffDate) {
                $size = filesize($file);
                $oldFiles[] = [
                    'path' => $file,
                    'action' => 'delete',
                    'size' => $size
                ];
                $totalSize += $size;
            }
        }

        $this->stats['log_files'] = count($oldFiles);
        $this->stats['total_space_freed'] += $totalSize;

        if (empty($oldFiles)) {
            $this->line("  No old log files found.");
            return;
        }

        $sizeInMB = round($totalSize / 1024 / 1024, 2);
        $this->line("  Found " . count($oldFiles) . " log files to process ({$sizeInMB} MB)");

        if (!$dryRun) {
            foreach ($oldFiles as $file) {
                if ($file['action'] === 'truncate') {
                    // Keep last 1000 lines
                    $lines = file($file['path']);
                    $keep = array_slice($lines, -1000);
                    file_put_contents($file['path'], implode('', $keep));
                    $this->line("  ✓ Truncated " . basename($file['path']));
                } else {
                    unlink($file['path']);
                    $this->line("  ✓ Deleted " . basename($file['path']));
                }
            }
        } else {
            foreach (array_slice($oldFiles, 0, 5) as $file) {
                $action = $file['action'] === 'truncate' ? 'truncate' : 'delete';
                $this->line("  [DRY RUN] Would {$action}: " . basename($file['path']));
            }
            if (count($oldFiles) > 5) {
                $this->line("  ... and " . (count($oldFiles) - 5) . " more");
            }
        }
    }

    /**
     * Optimize database tables
     */
    protected function optimizeDatabaseTables(): void
    {
        $this->info("Optimizing database tables...");

        try {
            $tables = [
                'members',
                'memberships',
                'payments',
                'payment_methods',
                'invoices',
                'check_ins',
                'course_bookings',
                'notification_recipients'
            ];

            foreach ($tables as $table) {
                DB::statement("OPTIMIZE TABLE {$table}");
                $this->line("  ✓ Optimized table: {$table}");
            }

            // Update statistics
            DB::statement("ANALYZE TABLE " . implode(', ', $tables));
            $this->info("  ✓ Updated table statistics");

        } catch (\Exception $e) {
            $this->warn("  Could not optimize tables: " . $e->getMessage());
        }
    }

    /**
     * Print cleanup summary
     */
    protected function printSummary(): void
    {
        $this->info("\n===========================================");
        $this->info("Cleanup Summary");
        $this->info("===========================================");

        if ($this->option('dry-run')) {
            $this->info("MODE: DRY RUN - No actual deletions performed");
            $this->info("");
        }

        $this->info("Test Data:");
        $this->info("  - Test members: {$this->stats['test_members']}");
        $this->info("  - Test payments: {$this->stats['test_payments']}");

        if ($this->option('orphaned')) {
            $this->info("\nOrphaned Records:");
            $this->info("  - Orphaned payments: {$this->stats['orphaned_payments']}");
        }

        if ($this->option('duplicates')) {
            $this->info("\nDuplicates:");
            $this->info("  - Duplicate payments: {$this->stats['duplicate_payments']}");
        }

        if ($this->option('rollback-files') || $this->option('logs')) {
            $this->info("\nFiles:");
            if ($this->option('rollback-files')) {
                $this->info("  - Rollback files: {$this->stats['rollback_files']}");
            }
            if ($this->option('logs')) {
                $this->info("  - Log files: {$this->stats['log_files']}");
            }

            $freedMB = round($this->stats['total_space_freed'] / 1024 / 1024, 2);
            $this->info("  - Space freed: {$freedMB} MB");
        }

        $this->info("===========================================\n");

        if (!$this->option('dry-run')) {
            Log::info('Test data cleanup completed', $this->stats);
        }
    }
}
