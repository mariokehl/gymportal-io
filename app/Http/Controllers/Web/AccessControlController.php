<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGoogleSheetSettingsRequest;
use App\Jobs\SyncCheckInsToGoogleSheet;
use App\Models\Addon;
use App\Models\GymScanner;
use App\Models\ScannerAccessLog;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccessControlController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the access control dashboard
     */
    public function index(Request $request): Response
    {
        $gym = Auth::user()->currentGym;

        $scanners = $gym->scanners()
            ->with('addon:id,name')
            ->selectRaw('gym_scanners.*')
            ->selectSub(
                ScannerAccessLog::selectRaw('COUNT(*)')
                    ->whereColumn('scanner_access_logs.device_number', 'gym_scanners.device_number')
                    ->where('scanner_access_logs.gym_id', $gym->id)
                    ->whereDate('scanner_access_logs.created_at', today()),
                'today_scans'
            )
            ->orderBy('device_number')
            ->get()
            ->map(function ($scanner) {
                // Cache-Heartbeat hat Vorrang vor DB-Zeitstempel für "Zuletzt gesehen"
                $cachedHeartbeat = Cache::get("scanner_heartbeat:{$scanner->id}");
                if ($cachedHeartbeat) {
                    $scanner->last_seen_at = $cachedHeartbeat;
                }

                return $scanner;
            });

        $recentLogs = ScannerAccessLog::forGym($gym->id)
            ->withScanner($gym->id)
            ->with('member')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($log) => $this->formatLogForFrontend($log));

        $statistics = ScannerAccessLog::getStatistics($gym->id, now()->startOfDay(), now());

        // Usage add-ons a dispenser or area-control device can settle against.
        $usageAddons = Addon::where('gym_id', $gym->id)
            ->usageServices()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'quota_amount', 'quota_interval']);

        return Inertia::render('AccessControl/Index', [
            'scanners' => $scanners,
            'usageAddons' => $usageAddons,
            'recentLogs' => $recentLogs,
            'statistics' => $statistics,
            'gymId' => $gym->id,
            'scannerSecretKey' => $gym->getAttributes()['scanner_secret_key'] ?? null,
            'rollingQrEnabled' => (bool) $gym->rolling_qr_enabled,
            'rollingQrInterval' => (int) ($gym->rolling_qr_interval ?? 3),
            'rollingQrToleranceWindows' => (int) ($gym->rolling_qr_tolerance_windows ?? 1),
            'googleSheet' => $this->googleSheetPayload($gym),
        ]);
    }

    /**
     * Build the safe (secret-free) Google Sheet integration payload for the UI.
     */
    private function googleSheetPayload($gym): array
    {
        $integration = $gym->googleSheetIntegration;

        return [
            'enabled' => (bool) $integration?->google_sheet_enabled,
            'configured' => (bool) $integration?->isConfigured(),
            'sheet_url' => $integration?->sheet_url,
            'service_account_email' => $integration?->service_account_email,
            'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * Get access logs with pagination and filters
     */
    public function logs(Request $request)
    {
        $gym = Auth::user()->currentGym;

        $query = ScannerAccessLog::forGym($gym->id)
            ->withScanner($gym->id)
            ->with('member')
            ->latest();

        // Apply filters
        if ($request->filled('scanner')) {
            $query->forScanner($request->scanner);
        }

        if ($request->filled('type')) {
            if ($request->type === 'qr_code') {
                $query->qrCode();
            } elseif ($request->type === 'nfc_card') {
                $query->nfcCard();
            }
        }

        if ($request->filled('status')) {
            if ($request->status === 'granted') {
                $query->granted();
            } elseif ($request->status === 'denied') {
                $query->denied();
            }
        }

        // Filter by device task. Logs reference the device by device_number,
        // so restrict to the gym's devices carrying that task.
        if ($request->filled('task') && in_array($request->task, GymScanner::DEVICE_TASKS, true)) {
            $query->whereIn('device_number', function ($sub) use ($gym, $request) {
                $sub->select('device_number')
                    ->from('gym_scanners')
                    ->where('gym_id', $gym->id)
                    ->where('device_task', $request->task);
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to.' 23:59:59');
        }

        $logs = $query->paginate($request->input('per_page', 50));

        $logs->getCollection()->transform(fn ($log) => $this->formatLogForFrontend($log));

        return response()->json($logs);
    }

    /**
     * Get statistics for the dashboard
     */
    public function statistics(Request $request)
    {
        $gym = Auth::user()->currentGym;

        $period = $request->input('period', 'today');
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        $statistics = ScannerAccessLog::getStatistics($gym->id, $startDate, now());

        // Add hourly distribution for chart
        $hourlyStats = ScannerAccessLog::forGym($gym->id)
            ->where('created_at', '>=', $startDate)
            ->selectRaw(ScannerAccessLog::extractHourSql('created_at').' as hour')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(ScannerAccessLog::sumBooleanSql('access_granted').' as granted')
            ->groupByRaw(ScannerAccessLog::extractHourSql('created_at'))
            ->orderByRaw(ScannerAccessLog::extractHourSql('created_at'))
            ->get()
            ->keyBy('hour');

        // Fill missing hours with zeros
        $hourlyDistribution = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyDistribution[$hour] = [
                'hour' => $hour,
                'total' => $hourlyStats[$hour]->total ?? 0,
                'granted' => $hourlyStats[$hour]->granted ?? 0,
                'denied' => ($hourlyStats[$hour]->total ?? 0) - ($hourlyStats[$hour]->granted ?? 0),
            ];
        }

        $statistics['hourly_distribution'] = array_values($hourlyDistribution);

        // Get denial reasons breakdown
        $denialReasons = ScannerAccessLog::forGym($gym->id)
            ->denied()
            ->where('created_at', '>=', $startDate)
            ->selectRaw('denial_reason, COUNT(*) as count')
            ->groupBy('denial_reason')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => [
                'denial_reason' => $item->denial_reason,
                'count' => (int) $item->count,
            ]);

        $statistics['denial_reasons'] = $denialReasons->toArray();

        return response()->json($statistics);
    }

    /**
     * Store a new scanner
     */
    public function storeScanner(Request $request)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $validated = $request->validate($this->deviceRules($request, $gym));

        // Filter empty IP values
        $allowedIps = array_filter($validated['allowed_ips'] ?? []);

        $scanner = $gym->scanners()->create($this->deviceAttributes($validated) + [
            'allowed_ips' => ! empty($allowedIps) ? array_values($allowedIps) : null,
            'token_expires_at' => $validated['token_expires_at'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gerät erfolgreich angelegt',
            'scanner' => $scanner->load('addon:id,name'),
            // Only chance to hand out the token — stored is its hash alone.
            'api_token' => $scanner->getPlainTextToken(),
        ]);
    }

    /**
     * Shared validation rules for creating and updating a device.
     */
    private function deviceRules(Request $request, $gym): array
    {
        return [
            'device_name' => 'required|string|max:255',
            'device_task' => ['required', Rule::in(GymScanner::DEVICE_TASKS)],
            'addon_id' => [
                Rule::requiredIf(fn () => in_array(
                    $request->input('device_task'),
                    GymScanner::ADDON_LINKED_TASKS,
                    true
                )),
                'nullable',
                // Only usage add-ons of the current gym may be linked.
                Rule::exists('addons', 'id')
                    ->where('gym_id', $gym->id)
                    ->where('service_type', Addon::SERVICE_TYPE_USAGE)
                    ->whereNull('deleted_at'),
            ],
            'enforce_quota' => 'boolean',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'nullable|string',
            // Null means the token never expires, which is what most devices
            // run with — a door must not close because a date passed.
            'token_expires_at' => 'nullable|date|after:today',
        ];
    }

    /**
     * Normalise the task-dependent attributes. The add-on link and the quota
     * mode only apply to devices that settle against a usage add-on.
     */
    private function deviceAttributes(array $validated): array
    {
        $isAddonLinked = in_array(
            $validated['device_task'],
            GymScanner::ADDON_LINKED_TASKS,
            true
        );

        return [
            'device_name' => $validated['device_name'],
            'device_task' => $validated['device_task'],
            'addon_id' => $isAddonLinked ? $validated['addon_id'] : null,
            'enforce_quota' => $isAddonLinked ? ($validated['enforce_quota'] ?? true) : true,
        ];
    }

    /**
     * Update a scanner
     */
    public function updateScanner(Request $request, GymScanner $scanner)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        if ($scanner->gym_id !== $gym->id) {
            abort(403);
        }

        $validated = $request->validate(
            $this->deviceRules($request, $gym) + ['is_active' => 'boolean']
        );

        // Filter empty IP values
        $allowedIps = array_filter($validated['allowed_ips'] ?? []);

        $scanner->update($this->deviceAttributes($validated) + [
            'allowed_ips' => ! empty($allowedIps) ? array_values($allowedIps) : null,
            'is_active' => $validated['is_active'] ?? $scanner->is_active,
            // Sent as null when "never expires" is ticked, so it has to be
            // written through rather than defaulted to the current value.
            'token_expires_at' => $validated['token_expires_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gerät erfolgreich aktualisiert',
            'scanner' => $scanner->fresh()->load('addon:id,name'),
        ]);
    }

    /**
     * Delete a scanner
     */
    public function destroyScanner(GymScanner $scanner)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        if ($scanner->gym_id !== $gym->id) {
            abort(403);
        }

        $scanner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Gerät erfolgreich gelöscht',
        ]);
    }

    /**
     * Toggle scanner active status
     */
    public function toggleScanner(GymScanner $scanner)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        if ($scanner->gym_id !== $gym->id) {
            abort(403);
        }

        $scanner->update(['is_active' => ! $scanner->is_active]);
        $status = $scanner->is_active ? 'aktiviert' : 'deaktiviert';

        return response()->json([
            'success' => true,
            'message' => "Scanner wurde {$status}",
            'scanner' => $scanner,
        ]);
    }

    /**
     * Regenerate scanner API token
     */
    public function regenerateToken(GymScanner $scanner)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        if ($scanner->gym_id !== $gym->id) {
            abort(403);
        }

        $newToken = $scanner->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'API-Token wurde erneuert',
            'api_token' => $newToken,
        ]);
    }

    /**
     * Download scanner configuration file
     */
    public function downloadConfig(GymScanner $scanner)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        if ($scanner->gym_id !== $gym->id) {
            abort(403);
        }

        // The token is only stored as a hash, so it cannot be put back into a
        // config file. Devices created before that change still carry their
        // plaintext; for all others the operator has to issue a new token.
        if (! $scanner->getPlainTextToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Der API-Token dieses Geräts ist aus Sicherheitsgründen nur als Hash gespeichert '
                    .'und lässt sich nicht erneut ausgeben. Erneuern Sie den Token, um eine neue '
                    .'Konfigurationsdatei zu erhalten.',
            ], 409);
        }

        $config = $this->generateScannerConfig($gym, $scanner);

        return response()->streamDownload(function () use ($config) {
            echo $config;
        }, "scanner_{$scanner->device_number}_config.env", [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * Regenerate gym scanner secret key
     */
    public function regenerateSecretKey()
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $gym->generateScannerSecretKey();

        return response()->json([
            'success' => true,
            'message' => 'Scanner-Secret-Key wurde erneuert. Alle Scanner müssen neu konfiguriert werden.',
            'scanner_secret_key' => $gym->fresh()->getAttributes()['scanner_secret_key'],
        ]);
    }

    /**
     * Update rolling QR code settings
     */
    public function updateRollingQrSettings(Request $request)
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $validated = $request->validate([
            'rolling_qr_enabled' => 'required|boolean',
            'rolling_qr_interval' => 'required|integer|min:1|max:60',
            'rolling_qr_tolerance_windows' => 'required|integer|min:0|max:5',
        ]);

        $gym->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rolling QR-Code Einstellungen wurden gespeichert.',
        ]);
    }

    /**
     * Create or update the per-gym Google Sheet integration.
     */
    public function updateGoogleSheetSettings(
        UpdateGoogleSheetSettingsRequest $request,
        GoogleSheetsService $googleSheets
    ): JsonResponse {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $validated = $request->validated();
        $integration = $gym->googleSheetIntegration;

        // Resolve the credentials: use the freshly uploaded key, or fall back to
        // the already-stored one when the operator only edits the sheet URL.
        $credentials = null;
        if ($request->hasFile('credentials_file')) {
            $credentials = json_decode(
                $request->file('credentials_file')->get(),
                true
            );

            if (! $googleSheets->isValidServiceAccountKey($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Die hochgeladene Datei ist kein gültiger Service-Account-Schlüssel.',
                ], 422);
            }
        } elseif ($integration) {
            $credentials = $integration->credentialsArray();
        }

        if ($validated['enabled'] && empty($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Bitte lade zuerst einen Service-Account-Schlüssel (JSON) hoch.',
            ], 422);
        }

        $spreadsheetId = $googleSheets->extractSpreadsheetId($validated['sheet_url'] ?? '');

        if ($validated['enabled'] && ! $spreadsheetId) {
            return response()->json([
                'success' => false,
                'message' => 'Aus der angegebenen URL konnte keine Google-Sheet-ID ermittelt werden.',
            ], 422);
        }

        $serviceAccountEmail = $googleSheets->serviceAccountEmailFrom($credentials ?? []);

        // When enabling, confirm access up front so nightly syncs don't fail silently.
        if ($validated['enabled']) {
            if (! $googleSheets->verifyAccess($credentials, $spreadsheetId)) {
                return response()->json([
                    'success' => false,
                    'message' => "Kein Zugriff auf das Sheet. Bitte teile das Google Sheet mit {$serviceAccountEmail} als Bearbeiter.",
                ], 422);
            }

            $googleSheets->ensureHeaderRow($credentials, $spreadsheetId);
        }

        $gym->googleSheetIntegration()->updateOrCreate(
            ['gym_id' => $gym->id],
            [
                'google_sheet_enabled' => $validated['enabled'],
                'credentials' => $credentials ? json_encode($credentials) : null,
                'service_account_email' => $serviceAccountEmail,
                'spreadsheet_id' => $spreadsheetId,
                'sheet_url' => $validated['sheet_url'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Google-Sheet-Verknüpfung wurde gespeichert.',
            'googleSheet' => $this->googleSheetPayload($gym->refresh()),
        ]);
    }

    /**
     * Remove the per-gym Google Sheet integration (deletes the stored key).
     */
    public function removeGoogleSheetSettings(): JsonResponse
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $gym->googleSheetIntegration()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Google-Sheet-Verknüpfung wurde entfernt.',
            'googleSheet' => $this->googleSheetPayload($gym->refresh()),
        ]);
    }

    /**
     * Trigger an immediate sync of the previous day's check-ins for this gym.
     */
    public function testGoogleSheetSync(): JsonResponse
    {
        $gym = Auth::user()->currentGym;
        $this->authorize('manage', $gym);

        $integration = $gym->googleSheetIntegration;

        if (! $integration || ! $integration->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Es ist keine aktive Google-Sheet-Verknüpfung konfiguriert.',
            ], 422);
        }

        SyncCheckInsToGoogleSheet::dispatch($gym->id, now()->subDay()->toDateString());

        return response()->json([
            'success' => true,
            'message' => 'Testlauf wurde gestartet. Die Check-Ins des Vortages werden in Kürze angehängt.',
        ]);
    }

    /**
     * Format a log entry for frontend display
     */
    private function formatLogForFrontend(ScannerAccessLog $log): array
    {
        return [
            'id' => $log->id,
            'device_number' => $log->device_number,
            'scanner_name' => $log->scanner?->device_name ?? 'Scanner #'.$log->device_number,
            // Read from the relation like scanner_name, so re-tasking a device
            // also changes what older entries show.
            'device_task' => $log->scanner?->device_task,
            'scan_type' => $log->scan_type,
            'scan_type_label' => $log->scan_type_label,
            'access_granted' => $log->access_granted,
            'status_label' => $log->status_label,
            'denial_reason' => $log->denial_reason,
            'member_id' => $log->member_id,
            'member_name' => $log->member ? trim($log->member->first_name.' '.$log->member->last_name) : null,
            'member_number' => $log->member?->member_number,
            'member_url' => $log->member ? route('members.show', $log->member->id) : null,
            'nfc_card_id' => $log->metadata['nfc_card_id'] ?? null,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at->toIso8601String(),
            'formatted_time' => $log->formatted_time,
            'time_ago' => $log->time_ago,
        ];
    }

    /**
     * Generate scanner configuration file content
     */
    private function generateScannerConfig($gym, GymScanner $scanner): string
    {
        $config = [
            '# Scanner Configuration',
            '# Generated: '.now()->toIso8601String(),
            '# Gym: '.$gym->name,
            '# Device: '.$scanner->device_name.' (#'.$scanner->device_number.')',
            '',
            '# API Configuration',
            'SAAS_API_BASE_URL="'.config('app.url').'/api"',
            'SAAS_API_KEY="'.$scanner->getPlainTextToken().'"',
            'DEVICE_NUMBER="'.$scanner->device_number.'"',
            'DEVICE_TASK="'.$scanner->device_task.'"',
            '',
            '# Usage service settled by this device (dispenser / area control)',
            'ADDON_ID="'.($scanner->addon_id ?? '').'"',
            'ENFORCE_QUOTA='.($scanner->enforce_quota ? 'True' : 'False'),
            '',
            '# Security Configuration',
            'SECRET_KEY="'.$gym->scanner_secret_key.'"',
            'QR_CODE_VALIDITY_MINUTES=30',
            'ENABLE_TIMESTAMP_CHECK=True',
            'ENABLE_HASH_CHECK=True',
            'ENABLE_NFC_CARDS=True',
            '',
            '# Rolling QR-Code Konfiguration',
            'ENABLE_ROLLING_QR='.($gym->rolling_qr_enabled ? 'True' : 'False'),
            'ROLLING_QR_INTERVAL_SECONDS='.($gym->rolling_qr_interval ?? 3),
            'ROLLING_QR_TOLERANCE_WINDOWS='.($gym->rolling_qr_tolerance_windows ?? 1),
            '',
            '# Optional IP Whitelist',
            '# ALLOWED_IPS='.($scanner->allowed_ips ? implode(',', $scanner->allowed_ips) : ''),
        ];

        return implode("\n", $config);
    }
}
