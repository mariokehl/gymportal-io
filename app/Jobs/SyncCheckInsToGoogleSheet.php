<?php

namespace App\Jobs;

use App\Models\CheckIn;
use App\Models\GymGoogleSheetIntegration;
use App\Services\GoogleSheetsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCheckInsToGoogleSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 120, 300]; // 30s, 2min, 5min

    /**
     * @param  int  $gymId  The gym whose check-ins should be mirrored.
     * @param  string  $date  Target day in Y-m-d format (defaults handled by caller).
     */
    public function __construct(
        public int $gymId,
        public string $date,
    ) {}

    public function handle(GoogleSheetsService $googleSheets): void
    {
        $integration = GymGoogleSheetIntegration::where('gym_id', $this->gymId)->first();

        if (! $integration || ! $integration->isConfigured()) {
            Log::info('Skipping Google Sheet sync: integration not configured', [
                'gym_id' => $this->gymId,
            ]);

            return;
        }

        $credentials = $integration->credentialsArray();

        if (empty($credentials)) {
            Log::warning('Skipping Google Sheet sync: credentials could not be decoded', [
                'gym_id' => $this->gymId,
            ]);

            return;
        }

        $day = Carbon::parse($this->date);

        $checkIns = CheckIn::where('gym_id', $this->gymId)
            ->whereBetween('check_in_time', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->with('member')
            ->orderBy('check_in_time')
            ->get();

        if ($checkIns->isEmpty()) {
            $integration->update(['last_synced_at' => now()]);

            Log::info('Google Sheet sync: no check-ins for day', [
                'gym_id' => $this->gymId,
                'date' => $this->date,
            ]);

            return;
        }

        $rows = $checkIns->map(fn (CheckIn $checkIn) => $this->buildRow($checkIn))->all();

        $googleSheets->ensureHeaderRow($credentials, $integration->spreadsheet_id);
        $googleSheets->appendRows($credentials, $integration->spreadsheet_id, $rows);

        $integration->update(['last_synced_at' => now()]);

        Log::info('Google Sheet sync completed', [
            'gym_id' => $this->gymId,
            'date' => $this->date,
            'rows' => count($rows),
        ]);
    }

    /**
     * Build a single spreadsheet row. Only the app-owned columns are filled;
     * the reviewer columns are left empty to be maintained manually.
     *
     * @return array<int, string>
     */
    private function buildRow(CheckIn $checkIn): array
    {
        $member = $checkIn->member;

        $name = $member
            ? trim("{$member->first_name} {$member->last_name}")
            : '';

        $membershipType = $member?->activeMembership()?->membershipPlan?->name ?? '';

        return [
            $name,                                          // Name
            $checkIn->check_in_time->format('Y-m-d H:i:s'), // CheckIn Time
            $membershipType,                                // Mitgliederart
            '',                                             // unauthorized occurrence?
            $member?->email ?? '',                          // Email-Adresse
            '',                                             // EmailVersand?
            '',                                             // Case-ID
            '',                                             // Antwort erhalten
            '',                                             // Antwort erhalten Datum
            '',                                             // Für Tagesticket vorgeschlagen
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Google Sheet sync job failed permanently', [
            'gym_id' => $this->gymId,
            'date' => $this->date,
            'error' => $exception->getMessage(),
        ]);
    }
}
