<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GymDataExportService;
use App\Services\GymDataImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTransferController extends Controller
{
    public function __construct(
        private GymDataExportService $exportService,
        private GymDataImportService $importService
    ) {}

    /**
     * Display the data transfer page
     */
    public function index(): Response
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        // Check authorization (owner or admin only)
        if (!$this->canAccessDataTransfer($user, $gym)) {
            abort(403, 'Sie haben keine Berechtigung für den Datenimport/-export.');
        }

        return Inertia::render('DataTransfer/Index', [
            'currentGym' => $gym,
            'exportStats' => $this->exportService->getExportStats($gym->id),
            'sensitiveDataWarning' => $this->getSensitiveDataWarning(),
        ]);
    }

    /**
     * Export all gym data as JSON
     */
    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        // Check authorization
        if (!$this->canAccessDataTransfer($user, $gym)) {
            abort(403, 'Sie haben keine Berechtigung für den Datenexport.');
        }

        $data = $this->exportService->exportGymData($gym->id);

        $filename = sprintf(
            'gym_export_%s_%s.json',
            $gym->slug,
            now()->format('Y-m-d_H-i-s')
        );

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Validate import file before processing
     */
    public function validateImport(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        // Check authorization
        if (!$this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'valid' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
        ]);

        $file = $request->file('file');

        // Check file extension
        if ($file->getClientOriginalExtension() !== 'json') {
            return response()->json([
                'valid' => false,
                'error' => 'Bitte wählen Sie eine JSON-Datei aus.',
            ], 422);
        }

        $content = file_get_contents($file->path());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'valid' => false,
                'error' => 'Ungültige JSON-Datei: ' . json_last_error_msg(),
            ], 422);
        }

        $validation = $this->importService->validateImportData($data);

        return response()->json($validation);
    }

    /**
     * Process the import
     */
    public function import(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        // Check authorization
        if (!$this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'success' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $rules = [
            'file' => 'required|file|max:102400',
            'mode' => 'required|in:replace,append',
        ];

        // Only require confirmation for replace mode
        if ($request->input('mode') === 'replace') {
            $rules['confirm_replace'] = 'required|accepted';
        }

        $request->validate($rules, [
            'file.required' => 'Bitte wählen Sie eine Datei aus.',
            'file.max' => 'Die Datei darf maximal 100 MB groß sein.',
            'mode.required' => 'Bitte wählen Sie einen Import-Modus.',
            'mode.in' => 'Ungültiger Import-Modus.',
            'confirm_replace.required' => 'Bitte bestätigen Sie das Ersetzen der Daten.',
            'confirm_replace.accepted' => 'Bitte bestätigen Sie das Ersetzen der Daten.',
        ]);

        $file = $request->file('file');

        // Check file extension
        if ($file->getClientOriginalExtension() !== 'json') {
            return response()->json([
                'success' => false,
                'error' => 'Bitte wählen Sie eine JSON-Datei aus.',
            ], 422);
        }

        $content = file_get_contents($file->path());
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'error' => 'Ungültige JSON-Datei: ' . json_last_error_msg(),
            ], 422);
        }

        // Validate before import
        $validation = $this->importService->validateImportData($data);
        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => 'Validierungsfehler: ' . implode(', ', $validation['errors']),
            ], 422);
        }

        try {
            $result = $this->importService->importGymData(
                $gym->id,
                $data,
                $request->input('mode')
            );

            return response()->json([
                'success' => true,
                'message' => 'Daten erfolgreich importiert',
                'stats' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import fehlgeschlagen: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if user can access data transfer features
     */
    private function canAccessDataTransfer($user, $gym): bool
    {
        if (!$gym) {
            return false;
        }

        // Must be the current gym
        if ($user->current_gym_id !== $gym->id) {
            return false;
        }

        // Owner can always access
        if ($gym->owner_id === $user->id) {
            return true;
        }

        // Check if user is admin for this gym
        $gymUser = $gym->users()->where('user_id', $user->id)->first();
        if ($gymUser && $gymUser->pivot->role === 'admin') {
            return true;
        }

        return false;
    }

    /**
     * Get sensitive data warning information
     */
    private function getSensitiveDataWarning(): array
    {
        return [
            'excluded' => [
                'Zahlungsmethoden (IBAN, Kreditkartendaten, SEPA-Mandate)',
                'Mollie-Konfiguration (API-Schlüssel)',
                'Scanner-Tokens und Secret Keys',
                'Benutzerpasswörter',
            ],
        ];
    }
}
