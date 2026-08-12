<?php

namespace App\Console\Commands;

use App\Models\CollectionCase;
use App\Models\Gym;
use App\Services\Diagonal\DiagonalApiException;
use App\Services\Diagonal\DiagonalClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pulls the processing state of transmitted collection cases.
 *
 * The DIAGONAL API is asynchronous, so the state of every case that carries a
 * GUID has to be polled until the case is closed.
 */
class SyncDiagonalStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inkasso:sync-states
                            {--gym-id= : Process only a specific gym}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise the state of transmitted collection cases with the partner';

    public function __construct(protected DiagonalClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $gyms = Gym::query()
            ->when($this->option('gym-id'), fn ($query, $gymId) => $query->where('id', $gymId))
            ->get()
            ->filter(fn (Gym $gym) => $gym->isInkassoEnabled());

        if ($gyms->isEmpty()) {
            $this->warn('Keine Organisation mit aktivem Inkassopartner gefunden.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($gyms as $gym) {
            $cases = CollectionCase::where('gym_id', $gym->id)
                ->whereNotNull('diagonal_guid')
                ->whereIn('status', CollectionCase::OPEN_STATUSES)
                ->get();

            foreach ($cases as $case) {
                try {
                    $state = $this->client->getFileState($gym, $case->diagonal_guid);

                    $case->update([
                        'diagonal_state' => $state,
                        'diagonal_synced_at' => now(),
                    ]);

                    $synced++;
                } catch (DiagonalApiException $e) {
                    $failed++;

                    $this->warn(sprintf('Akte %s: %s', $case->case_number, $e->getMessage()));

                    Log::warning('Failed to sync collection case state', [
                        'case_id' => $case->id,
                        'gym_id' => $gym->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Statusabgleich abgeschlossen: {$synced} aktualisiert, {$failed} fehlgeschlagen.");

        return self::SUCCESS;
    }
}
