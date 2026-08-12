<?php

namespace App\Console\Commands;

use App\Models\Gym;
use App\Services\DunningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDunning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dunning:process
                            {--gym-id= : Process only a specific gym}
                            {--dry-run : Show what would happen without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Escalate dunning levels for members with overdue payments';

    public function __construct(protected DunningService $dunningService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $gyms = Gym::query()
            ->when($this->option('gym-id'), fn ($query, $gymId) => $query->where('id', $gymId))
            ->get();

        if ($gyms->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $totalEscalated = 0;

        foreach ($gyms as $gym) {
            $result = $this->dunningService->processGym($gym, $dryRun);
            $totalEscalated += $result['escalated'];

            $this->line(sprintf(
                '%s: %d eskaliert, %d übersprungen',
                $gym->name,
                $result['escalated'],
                $result['skipped']
            ));

            foreach ($result['notices'] as $notice) {
                $this->line(sprintf(
                    '  - Mitglied #%d auf Stufe %d (%s €)',
                    $notice->member_id,
                    $notice->level,
                    number_format((float) $notice->fee, 2, ',', '.')
                ));
            }
        }

        if ($dryRun) {
            $this->info("Testlauf: {$totalEscalated} Eskalationen wären erfolgt.");

            return self::SUCCESS;
        }

        $this->info("Mahnlauf abgeschlossen: {$totalEscalated} Eskalationen.");

        Log::info('Dunning run completed', ['escalated' => $totalEscalated]);

        return self::SUCCESS;
    }
}
