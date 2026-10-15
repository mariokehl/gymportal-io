<?php

namespace App\Console\Commands;

use App\Models\Gym;
use App\Models\Membership;
use App\Services\PresaleActivationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * One-off activation of pre-registrations once a gym's opening date is
 * confirmed. See PresaleActivationService for what happens per membership.
 */
class ActivatePresaleMemberships extends Command
{
    protected $signature = 'memberships:activate-presale
                            {gym : ID des Studios}
                            {--opening-date= : Vertragsbeginn zur Eröffnung (Y-m-d)}
                            {--limit= : Höchstens so viele Mitgliedschaften verarbeiten}
                            {--dry-run : Nur prüfen, nichts ändern}
                            {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Aktiviert Voranmeldungen zur Eröffnung: Vertragsbeginn verschieben, auf Mollie umstellen, Vertrag erzeugen und versenden';

    public function handle(PresaleActivationService $service): int
    {
        $gym = Gym::find($this->argument('gym'));

        if (! $gym) {
            $this->error('Studio nicht gefunden.');

            return self::FAILURE;
        }

        $openingDate = $this->openingDate();

        if (! $openingDate) {
            $this->error('Bitte --opening-date im Format Y-m-d angeben.');

            return self::FAILURE;
        }

        if ($blockReason = $service->gymBlockReason($gym)) {
            $this->error($blockReason);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $candidates = $service->candidates($gym, $openingDate);

        if ($limit = (int) $this->option('limit')) {
            $candidates = $candidates->take($limit);
        }

        $this->info(sprintf(
            '%s: %d Voranmeldung(en) mit Vertragsbeginn bis %s gefunden.%s',
            $gym->name,
            $candidates->count(),
            $openingDate->format('d.m.Y'),
            $dryRun ? ' (Testlauf, es wird nichts geändert)' : ''
        ));

        if ($candidates->isEmpty()) {
            return self::SUCCESS;
        }

        if (! $dryRun && ! $this->option('force')
            && ! $this->confirm('Mitgliedschaften jetzt aktivieren, auf Mollie umstellen und Willkommens-E-Mails versenden?')) {
            return self::FAILURE;
        }

        $rows = $candidates->map(fn (Membership $membership) => $this->processOne($service, $membership, $openingDate, $dryRun));

        $this->table(['Mitgliedschaft', 'Mitgliedsnr.', 'Beginn bisher', 'Ergebnis', 'Hinweis'], $rows->all());

        foreach ($rows->countBy('outcome') as $outcome => $count) {
            $this->line("{$outcome}: {$count}");
        }

        $this->info('Bericht: '.Storage::disk('local')->path($this->writeReport($gym, $rows->all(), $dryRun)));

        return $rows->contains('outcome', PresaleActivationService::OUTCOME_FAILED) ? self::FAILURE : self::SUCCESS;
    }

    private function openingDate(): ?Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m-d', (string) $this->option('opening-date')) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, ?string>
     */
    private function processOne(PresaleActivationService $service, Membership $membership, Carbon $openingDate, bool $dryRun): array
    {
        $originalStart = $membership->start_date->format('d.m.Y');

        try {
            $result = $service->process($membership, $openingDate, $dryRun);
        } catch (Throwable $e) {
            report($e);
            $result = ['outcome' => PresaleActivationService::OUTCOME_FAILED, 'reason' => $e->getMessage()];
        }

        return [
            'membership_id' => (string) $membership->id,
            'member_number' => $membership->member->member_number,
            'original_start_date' => $originalStart,
            'outcome' => $result['outcome'],
            'reason' => $result['reason'],
        ];
    }

    /**
     * Writes the result as CSV; it holds no personal data beyond member numbers.
     *
     * @param  array<int, array<string, ?string>>  $rows
     */
    private function writeReport(Gym $gym, array $rows, bool $dryRun): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_keys($rows[0]), ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $path = sprintf(
            'reports/presale-activation/%d_%s%s.csv',
            $gym->id,
            now()->format('Y-m-d_His'),
            $dryRun ? '_dry-run' : ''
        );

        Storage::disk('local')->put($path, $csv);

        return $path;
    }
}
