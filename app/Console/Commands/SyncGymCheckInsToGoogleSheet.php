<?php

namespace App\Console\Commands;

use App\Jobs\SyncCheckInsToGoogleSheet;
use App\Models\GymGoogleSheetIntegration;
use Illuminate\Console\Command;

class SyncGymCheckInsToGoogleSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-sheets:sync-checkins
                            {--gym-id= : Only sync a specific gym}
                            {--date= : Target day (Y-m-d), defaults to yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mirror the previous day\'s check-ins into each gym\'s linked Google Sheet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ?: now()->subDay()->toDateString();

        $query = GymGoogleSheetIntegration::where('google_sheet_enabled', true)
            ->whereNotNull('credentials')
            ->whereNotNull('spreadsheet_id');

        if ($gymId = $this->option('gym-id')) {
            $query->where('gym_id', $gymId);
        }

        $integrations = $query->get();

        if ($integrations->isEmpty()) {
            $this->info('No gyms with an active Google Sheet integration found.');

            return self::SUCCESS;
        }

        foreach ($integrations as $integration) {
            SyncCheckInsToGoogleSheet::dispatch($integration->gym_id, $date);
            $this->line("Dispatched sync for gym #{$integration->gym_id} (date: {$date}).");
        }

        $this->info("Dispatched {$integrations->count()} Google Sheet sync job(s).");

        return self::SUCCESS;
    }
}
