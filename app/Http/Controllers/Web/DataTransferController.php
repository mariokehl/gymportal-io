<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\GymDataExportService;
use App\Services\GymDataImportService;
use App\Services\MemberArchiveImportService;
use App\Services\MemberArchiveParser;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTransferController extends Controller
{
    public function __construct(
        private GymDataExportService $exportService,
        private GymDataImportService $importService,
        private MemberArchiveParser $archiveParser,
        private MemberArchiveImportService $archiveImportService
    ) {}

    /**
     * Display the data transfer page
     */
    public function index(): Response
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        // Check authorization (owner or admin only)
        if (! $this->canAccessDataTransfer($user, $gym)) {
            abort(403, 'Sie haben keine Berechtigung für den Datenimport/-export.');
        }

        $paymentMethods = array_merge(
            array_values($gym->getEnabledStandardPaymentMethods()),
            $gym->getMolliePaymentMethods()
        );

        $membershipPlans = MembershipPlan::where('gym_id', $gym->id)
            ->where('is_active', true)
            ->select('id', 'name', 'price', 'billing_cycle', 'commitment_months')
            ->orderBy('price')
            ->get();

        return Inertia::render('DataTransfer/Index', [
            'currentGym' => $gym,
            'exportStats' => $this->exportService->getExportStats($gym->id),
            'sensitiveDataWarning' => $this->getSensitiveDataWarning(),
            'paymentMethods' => $paymentMethods,
            'membershipPlans' => $membershipPlans,
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
        if (! $this->canAccessDataTransfer($user, $gym)) {
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
        if (! $this->canAccessDataTransfer($user, $gym)) {
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
                'error' => 'Ungültige JSON-Datei: '.json_last_error_msg(),
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
        if (! $this->canAccessDataTransfer($user, $gym)) {
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
                'error' => 'Ungültige JSON-Datei: '.json_last_error_msg(),
            ], 422);
        }

        // Validate before import
        $validation = $this->importService->validateImportData($data);
        if (! $validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => 'Validierungsfehler: '.implode(', ', $validation['errors']),
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
                'error' => 'Import fehlgeschlagen: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Validate CSV import file before processing
     */
    public function validateCsvImport(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'valid' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');

        if (! in_array($file->getClientOriginalExtension(), ['csv', 'CSV'])) {
            return response()->json([
                'valid' => false,
                'error' => 'Bitte wählen Sie eine CSV-Datei aus.',
            ], 422);
        }

        $rows = $this->parseCsvFile($file->path());

        if (empty($rows)) {
            return response()->json([
                'valid' => false,
                'errors' => ['Die CSV-Datei enthält keine Daten.'],
            ], 422);
        }

        $validation = $this->importService->validateCsvData($rows, $gym->id);

        return response()->json($validation);
    }

    /**
     * Process CSV import
     */
    public function importCsv(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'success' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240',
            'start_date' => 'required|date',
            'payment_method_type' => 'required|string',
        ], [
            'file.required' => 'Bitte wählen Sie eine Datei aus.',
            'start_date.required' => 'Bitte wählen Sie ein Startdatum.',
            'payment_method_type.required' => 'Bitte wählen Sie eine Zahlungsart.',
        ]);

        $file = $request->file('file');

        if (! in_array($file->getClientOriginalExtension(), ['csv', 'CSV'])) {
            return response()->json([
                'success' => false,
                'error' => 'Bitte wählen Sie eine CSV-Datei aus.',
            ], 422);
        }

        $rows = $this->parseCsvFile($file->path());

        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'error' => 'Die CSV-Datei enthält keine Daten.',
            ], 422);
        }

        try {
            $result = $this->importService->importCsvData(
                $gym->id,
                $rows,
                $request->input('start_date'),
                $request->input('payment_method_type'),
                (bool) $request->input('delete_existing', false)
            );

            return response()->json([
                'success' => true,
                'message' => 'CSV-Import erfolgreich',
                'stats' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import fehlgeschlagen: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Receive one chunk of a member archive upload.
     *
     * A directory upload can hold far more files than PHP accepts in a single
     * request (max_file_uploads), so the client sends the files in chunks that
     * are collected in one staging directory identified by a token.
     */
    public function uploadArchiveChunk(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'success' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:51200', // 50 MB per file
            'token' => 'nullable|string',
        ], [
            'files.required' => 'Bitte wählen Sie ein Archiv oder einen Ordner aus.',
            'files.*.max' => 'Einzelne Dateien dürfen maximal 50 MB groß sein.',
        ]);

        // The first chunk creates the staging directory, later chunks reuse it.
        $token = $request->input('token');
        $root = $token ? $this->resolveStagedArchive($token) : null;

        if ($token && $root === null) {
            return response()->json([
                'success' => false,
                'error' => 'Der Upload ist abgelaufen. Bitte wählen Sie den Ordner erneut aus.',
            ], 422);
        }

        try {
            $root ??= $this->createStagingDirectory();
            $stored = $this->storeUploadedFiles($request->file('files'), $root);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'token' => basename($root),
            'stored' => $stored,
        ]);
    }

    /**
     * Analyse a staged member archive without writing any data.
     */
    public function validateArchiveImport(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'valid' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'token' => 'required|string',
        ], [
            'token.required' => 'Bitte wählen Sie zuerst ein Archiv oder einen Ordner aus.',
        ]);

        $root = $this->resolveStagedArchive($request->input('token'));

        if ($root === null) {
            return response()->json([
                'valid' => false,
                'errors' => ['Der Upload ist abgelaufen. Bitte wählen Sie den Ordner erneut aus.'],
            ], 422);
        }

        try {
            $root = $this->unwrapUploadedZip($root);
        } catch (\RuntimeException $e) {
            $this->deleteDirectory($root);

            return response()->json([
                'valid' => false,
                'errors' => [$e->getMessage()],
            ], 422);
        }

        $folders = $this->archiveParser->findMemberFolders($root);

        if ($folders === []) {
            $this->deleteDirectory($root);

            return response()->json([
                'valid' => false,
                'errors' => ['Im Upload wurde keine gültige Mitgliedsakte gefunden. Erwartet wird je Mitglied ein Ordner mit einer master_data.xlsx.'],
            ], 422);
        }

        $result = $this->archiveImportService->analyse($gym->id, $folders);
        $result['token'] = basename($root);

        return response()->json($result);
    }

    /**
     * Import a previously analysed member archive.
     */
    public function importArchive(Request $request)
    {
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $this->canAccessDataTransfer($user, $gym)) {
            return response()->json([
                'success' => false,
                'error' => 'Sie haben keine Berechtigung für den Datenimport.',
            ], 403);
        }

        $request->validate([
            'token' => 'required|string',
            'fallback_start_date' => 'nullable|date',
            'create_missing_plans' => 'boolean',
        ], [
            'token.required' => 'Bitte prüfen Sie das Archiv zuerst.',
        ]);

        $root = $this->resolveStagedArchive($request->input('token'));

        if ($root === null) {
            return response()->json([
                'success' => false,
                'error' => 'Der geprüfte Upload ist nicht mehr verfügbar. Bitte laden Sie das Archiv erneut hoch.',
            ], 422);
        }

        try {
            $folders = $this->archiveParser->findMemberFolders($root);

            if ($folders === []) {
                return response()->json([
                    'success' => false,
                    'error' => 'Im Upload wurde keine gültige Mitgliedsakte gefunden.',
                ], 422);
            }

            $stats = $this->archiveImportService->import(
                $gym->id,
                $folders,
                $request->input('fallback_start_date'),
                $request->boolean('create_missing_plans', true)
            );

            return response()->json([
                'success' => true,
                'message' => 'Import erfolgreich',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Import fehlgeschlagen: '.$e->getMessage(),
            ], 422);
        } finally {
            $this->deleteDirectory($root);
        }
    }

    /**
     * Move uploaded files into the staging directory, keeping their relative
     * path so one folder per member can be reconstructed.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storeUploadedFiles(array $files, string $root): int
    {
        $stored = 0;

        foreach ($files as $file) {
            // The directory picker sends the relative path in webkitRelativePath;
            // it is the only way to reconstruct the folder per member.
            $relative = $this->sanitizeUploadPath(
                $file->getClientOriginalPath() ?: $file->getClientOriginalName()
            );

            if ($relative === null) {
                continue;
            }

            $destination = $root.'/'.$relative;
            $directory = dirname($destination);

            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                continue;
            }

            $file->move($directory, basename($destination));
            $stored++;
        }

        return $stored;
    }

    /**
     * If the upload was a single ZIP archive, extract it and continue with the
     * extracted directory instead.
     */
    private function unwrapUploadedZip(string $root): string
    {
        $entries = glob($root.'/*') ?: [];

        if (count($entries) !== 1 || ! is_file($entries[0]) || strtolower(pathinfo($entries[0], PATHINFO_EXTENSION)) !== 'zip') {
            return $root;
        }

        $extracted = $this->archiveParser->extractZip($entries[0]);
        $this->deleteDirectory($root);

        return $extracted;
    }

    /**
     * Reduce an uploaded relative path to a safe path inside the staging directory.
     */
    private function sanitizeUploadPath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '__MACOSX') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * Create a staging directory for an uploaded archive.
     */
    private function createStagingDirectory(): string
    {
        $root = storage_path('app/tmp/member-archive-'.bin2hex(random_bytes(8)));

        if (! mkdir($root, 0700, true) && ! is_dir($root)) {
            throw new \RuntimeException('Temporäres Verzeichnis konnte nicht angelegt werden.');
        }

        return $root;
    }

    /**
     * Resolve a staging token back to its directory, rejecting any traversal.
     */
    private function resolveStagedArchive(string $token): ?string
    {
        if (! preg_match('/^member-archive-[0-9a-f]{16}$/', $token)) {
            return null;
        }

        $root = storage_path('app/tmp/'.$token);

        return is_dir($root) ? $root : null;
    }

    /**
     * Remove a staging directory and everything below it.
     */
    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path) || ! str_starts_with($path, storage_path('app/tmp/'))) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    /**
     * Parse a CSV file into an array of associative rows
     */
    private function parseCsvFile(string $path): array
    {
        // Remove UTF-8 BOM if present
        $content = file_get_contents($path);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $cleanPath = tempnam(sys_get_temp_dir(), 'csv_');
            file_put_contents($cleanPath, substr($content, 3));
            $path = $cleanPath;
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle, 0, ';');
        if (! $header) {
            fclose($handle);

            return [];
        }
        $header = array_map('trim', $header);

        $rows = [];
        while (($values = fgetcsv($handle, 0, ';')) !== false) {
            if (count($values) === count($header)) {
                $rows[] = array_combine($header, $values);
            }
        }

        fclose($handle);

        if (isset($cleanPath)) {
            unlink($cleanPath);
        }

        return $rows;
    }

    /**
     * Check if user can access data transfer features
     */
    private function canAccessDataTransfer($user, $gym): bool
    {
        if (! $gym) {
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
