<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymScanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * @deprecated v0.0.53 Use {@see \App\Http\Controllers\Web\AccessControlController} instead.
 */
class ScannerManagementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Neuen Scanner anlegen
     */
    public function store(Request $request, Gym $gym)
    {
        $this->authorize('manage', $gym);

        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'token_expires_at' => 'nullable|date|after:today'
        ]);

        $scanner = $gym->scanners()->create([
            'device_name' => $validated['device_name'],
            'allowed_ips' => $validated['allowed_ips'] ?? null,
            'token_expires_at' => $validated['token_expires_at'] ?? null,
            'is_active' => true
        ]);

        // Token wird automatisch durch Model Boot generiert

        return redirect()
            ->route('admin.gym.scanners.show', [$gym, $scanner])
            ->with('success', 'Scanner erfolgreich angelegt')
            ->with('show_token', true) // Flag um Token einmalig anzuzeigen
            ->with('api_token', $scanner->api_token);
    }

    /**
     * Scanner Details anzeigen
     */
    public function show(Gym $gym, GymScanner $scanner)
    {
        $this->authorize('manage', $gym);

        // Token nur beim ersten Mal nach Erstellung zeigen
        $showToken = session('show_token', false);
        $apiToken = session('api_token');

        return view('admin.scanner-details', [
            'gym' => $gym,
            'scanner' => $scanner,
            'showToken' => $showToken,
            'apiToken' => $apiToken,
            'recentLogs' => $scanner->accessLogs()
                ->latest()
                ->limit(50)
                ->get()
        ]);
    }

    /**
     * Token regenerieren
     */
    public function regenerateToken(Gym $gym, GymScanner $scanner)
    {
        $this->authorize('manage', $gym);

        $newToken = $scanner->regenerateToken();

        return redirect()
            ->route('admin.gym.scanners.show', [$gym, $scanner])
            ->with('success', 'API Token wurde erneuert')
            ->with('show_token', true)
            ->with('api_token', $newToken);
    }

    /**
     * Scanner deaktivieren/aktivieren
     */
    public function toggle(Gym $gym, GymScanner $scanner)
    {
        $this->authorize('manage', $gym);

        $scanner->update([
            'is_active' => !$scanner->is_active
        ]);

        $status = $scanner->is_active ? 'aktiviert' : 'deaktiviert';

        return redirect()
            ->route('admin.gym.scanners.show', [$gym, $scanner])
            ->with('success', "Scanner wurde {$status}");
    }

    /**
     * Konfigurationsdatei für Raspberry Pi generieren
     */
    public function downloadConfig(Gym $gym, GymScanner $scanner)
    {
        $this->authorize('manage', $gym);

        $config = $this->generateScannerConfig($gym, $scanner);

        return response()
            ->streamDownload(function() use ($config) {
                echo $config;
            }, "scanner_{$scanner->device_number}_config.env");
    }

    /**
     * Generiert die Konfiguration für den Scanner
     */
    private function generateScannerConfig(Gym $gym, GymScanner $scanner): string
    {
        $config = [
            '# Scanner Configuration',
            '# Generated: ' . now()->toIso8601String(),
            '# Gym: ' . $gym->name,
            '# Device: ' . $scanner->device_name,
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
            '# Rolling QR-Code Konfiguration',
            'ENABLE_ROLLING_QR=' . ($gym->rolling_qr_enabled ? 'True' : 'False'),
            'ROLLING_QR_INTERVAL_SECONDS=' . ($gym->rolling_qr_interval ?? 3),
            'ROLLING_QR_TOLERANCE_WINDOWS=' . ($gym->rolling_qr_tolerance_windows ?? 1),
            '',
            '# Optional IP Whitelist',
            '# ALLOWED_IPS=' . ($scanner->allowed_ips ? implode(',', $scanner->allowed_ips) : ''),
        ];

        return implode("\n", $config);
    }
}
