<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GymScanner;
use App\Models\ScannerAccessLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->selectRaw('gym_scanners.*')
            ->selectSub(
                ScannerAccessLog::selectRaw('COUNT(*)')
                    ->whereColumn('scanner_access_logs.device_number', 'gym_scanners.device_number')
                    ->where('scanner_access_logs.gym_id', $gym->id)
                    ->whereDate('scanner_access_logs.created_at', today()),
                'today_scans'
            )
            ->orderBy('device_number')
            ->get();

        $recentLogs = ScannerAccessLog::forGym($gym->id)
            ->with(['scanner', 'member'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($log) => $this->formatLogForFrontend($log));

        $statistics = ScannerAccessLog::getStatistics($gym->id, now()->startOfDay(), now());

        return Inertia::render('AccessControl/Index', [
            'scanners' => $scanners,
            'recentLogs' => $recentLogs,
            'statistics' => $statistics,
            'gymId' => $gym->id,
            'scannerSecretKey' => $gym->getAttributes()['scanner_secret_key'] ?? null,
        ]);
    }

    /**
     * Get access logs with pagination and filters
     */
    public function logs(Request $request)
    {
        $gym = Auth::user()->currentGym;

        $query = ScannerAccessLog::forGym($gym->id)
            ->with(['scanner', 'member'])
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

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate($request->input('per_page', 50));

        $logs->getCollection()->transform(fn($log) => $this->formatLogForFrontend($log));

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
            ->selectRaw('HOUR(created_at) as hour')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(access_granted) as granted')
            ->groupByRaw('HOUR(created_at)')
            ->orderByRaw('HOUR(created_at)')
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

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'nullable|string',
            'token_expires_at' => 'nullable|date|after:today',
        ]);

        // Filter empty IP values
        $allowedIps = array_filter($validated['allowed_ips'] ?? []);

        $scanner = $gym->scanners()->create([
            'device_name' => $validated['device_name'],
            'allowed_ips' => !empty($allowedIps) ? array_values($allowedIps) : null,
            'token_expires_at' => $validated['token_expires_at'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Scanner erfolgreich angelegt',
            'scanner' => $scanner,
            'api_token' => $scanner->api_token,
        ]);
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

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Filter empty IP values
        $allowedIps = array_filter($validated['allowed_ips'] ?? []);

        $scanner->update([
            'device_name' => $validated['device_name'],
            'allowed_ips' => !empty($allowedIps) ? array_values($allowedIps) : null,
            'is_active' => $validated['is_active'] ?? $scanner->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Scanner erfolgreich aktualisiert',
            'scanner' => $scanner->fresh(),
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
            'message' => 'Scanner erfolgreich gelöscht',
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

        $scanner->update(['is_active' => !$scanner->is_active]);
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
     * Format a log entry for frontend display
     */
    private function formatLogForFrontend(ScannerAccessLog $log): array
    {
        return [
            'id' => $log->id,
            'device_number' => $log->device_number,
            'scanner_name' => $log->scanner?->device_name ?? 'Scanner #' . $log->device_number,
            'scan_type' => $log->scan_type,
            'scan_type_label' => $log->scan_type_label,
            'access_granted' => $log->access_granted,
            'status_label' => $log->status_label,
            'denial_reason' => $log->denial_reason,
            'member_id' => $log->member_id,
            'member_name' => $log->member ? trim($log->member->first_name . ' ' . $log->member->last_name) : null,
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
            '# Generated: ' . now()->toIso8601String(),
            '# Gym: ' . $gym->name,
            '# Device: ' . $scanner->device_name . ' (#' . $scanner->device_number . ')',
            '',
            '# API Configuration',
            'SAAS_API_BASE_URL="' . config('app.url') . '/api/scanner"',
            'SCANNER_API_TOKEN="' . $scanner->api_token . '"',
            'DEVICE_NUMBER="' . $scanner->device_number . '"',
            '',
            '# Security Configuration',
            'SECRET_KEY="' . $gym->scanner_secret_key . '"',
            'QR_CODE_VALIDITY_MINUTES=30',
            'ENABLE_TIMESTAMP_CHECK=true',
            'ENABLE_HASH_CHECK=true',
            'ENABLE_NFC_CARDS=true',
            '',
            '# Optional IP Whitelist',
            '# ALLOWED_IPS=' . ($scanner->allowed_ips ? implode(',', $scanner->allowed_ips) : ''),
        ];

        return implode("\n", $config);
    }
}
