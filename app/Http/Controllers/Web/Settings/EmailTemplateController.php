<?php

namespace App\Http\Controllers\Web\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateAttachment;
use App\Models\Gym;
use App\Services\EmailTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateService $emailTemplateService
    ) {}

    /**
     * Display a listing of the email templates.
     */
    public function index(Request $request)
    {
        $gym = $this->getCurrentGym($request);

        $templates = EmailTemplate::forGym($gym->id)
            ->with('fileAttachments')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => $templates,
            'placeholders' => EmailTemplate::getAllPlaceholders(),
            'template_definitions' => $this->emailTemplateService->getTemplateDefinitions(),
        ]);
    }

    /**
     * Show the specified email template.
     */
    public function show(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $emailTemplate->load('fileAttachments');

        return response()->json([
            'template' => $emailTemplate,
            'placeholders' => EmailTemplate::getAllPlaceholders(),
        ]);
    }

    /**
     * Store a newly created email template.
     */
    public function store(Request $request)
    {
        $gym = $this->getCurrentGym($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(EmailTemplate::TYPES))
            ],
            'subject' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'use_template_definition' => 'boolean',
        ]);

        $validated['gym_id'] = $gym->id;

        // Wenn Template-Definition verwendet werden soll, fülle Subject und Body aus Service
        if ($validated['use_template_definition'] ?? false) {
            $definitions = $this->emailTemplateService->getTemplateDefinitions();

            if (isset($definitions[$validated['type']])) {
                $definition = $definitions[$validated['type']];
                $validated['subject'] = $definition['subject'] ?: $validated['subject'];
                $validated['body'] = $definition['body'] ?: $validated['body'];
            }
        }

        // Fallback für leere Werte
        $validated['subject'] = $validated['subject'] ?: 'Betreff eingeben';
        $validated['body'] = $validated['body'] ?: '<p>Inhalt eingeben...</p>';

        // If setting as default, ensure no other template of this type is default
        if ($validated['is_default'] ?? false) {
            EmailTemplate::forGym($gym->id)
                ->ofType($validated['type'])
                ->update(['is_default' => false]);
        }

        $template = EmailTemplate::create($validated);

        return response()->json([
            'template' => $template,
            'message' => 'E-Mail-Vorlage erfolgreich erstellt!'
        ], 201);
    }

    /**
     * Update the specified email template.
     */
    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => [
                'required',
                'string',
                Rule::in(array_keys(EmailTemplate::TYPES))
            ],
            'subject' => 'required|string|max:500',
            'body' => 'required|string',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        // If setting as default, ensure no other template of this type is default
        if (($validated['is_default'] ?? false) && !$emailTemplate->is_default) {
            EmailTemplate::forGym($gym->id)
                ->ofType($validated['type'])
                ->where('id', '!=', $emailTemplate->id)
                ->update(['is_default' => false]);
        }

        $emailTemplate->update($validated);

        return response()->json([
            'template' => $emailTemplate->fresh(),
            'message' => 'E-Mail-Vorlage erfolgreich aktualisiert!'
        ]);
    }

    /**
     * Remove the specified email template.
     */
    public function destroy(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $emailTemplate->delete();

        return response()->json([
            'message' => 'E-Mail-Vorlage erfolgreich gelöscht!'
        ]);
    }

    /**
     * Duplicate the specified email template.
     */
    public function duplicate(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $newName = $request->input('name') ?: ($emailTemplate->name . ' (Kopie)');
        $duplicate = $emailTemplate->duplicate($newName);

        return response()->json([
            'template' => $duplicate,
            'message' => 'E-Mail-Vorlage erfolgreich dupliziert!'
        ], 201);
    }

    /**
     * Get available template definitions for creating new templates.
     */
    public function getTemplateDefinitions()
    {
        $definitions = $this->emailTemplateService->getTemplateDefinitions();

        return response()->json([
            'template_definitions' => $definitions,
            'template_types' => EmailTemplate::TYPES,
        ]);
    }

    /**
     * Preview an email template with sample data.
     */
    public function preview(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $sampleData = $this->getSampleData($gym);
        $preview = $emailTemplate->replacePlaceholders($sampleData);

        return response()->json([
            'preview' => $preview,
            'sample_data' => $sampleData,
        ]);
    }

    /**
     * Get templates by type.
     */
    public function byType(Request $request, string $type)
    {
        $gym = $this->getCurrentGym($request);

        $templates = EmailTemplate::forGym($gym->id)
            ->ofType($type)
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => $templates,
            'type' => $type,
        ]);
    }

    /**
     * Get the default template for a specific type.
     */
    public function getDefault(Request $request, string $type)
    {
        $gym = $this->getCurrentGym($request);

        $template = EmailTemplate::forGym($gym->id)
            ->ofType($type)
            ->default()
            ->active()
            ->first();

        if (!$template) {
            $template = EmailTemplate::forGym($gym->id)
                ->ofType($type)
                ->active()
                ->first();
        }

        return response()->json([
            'template' => $template,
            'type' => $type,
        ]);
    }

    /**
     * Render template with actual data.
     */
    public function render(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym($request);

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $validated = $request->validate([
            'data' => 'required|array',
        ]);

        $rendered = $emailTemplate->replacePlaceholders($validated['data']);

        return response()->json([
            'rendered' => $rendered,
        ]);
    }

    /**
     * Get available placeholders.
     */
    public function placeholders()
    {
        return response()->json([
            'placeholders' => EmailTemplate::getAvailablePlaceholders(),
            'flat_placeholders' => EmailTemplate::getAllPlaceholders(),
        ]);
    }

    /**
     * Get the current gym from the request or session.
     */
    private function getCurrentGym(): Gym
    {
        /** @var User $user */
        $user = Auth::user();

        $gym = Gym::findOrFail($user->current_gym_id);

        // Verify user has access to this gym
        if (!$user->ownedGyms()->where('gyms.id', $gym->id)->exists()) {
            abort(403, 'Access denied to this gym.');
        }

        return $gym;
    }

    /**
     * Generate sample data for template preview.
     */
    private function getSampleData(Gym $gym): array
    {
        return [
            '[Vorname]' => 'Max',
            '[Nachname]' => 'Mustermann',
            '[Anrede]' => 'Herr',
            '[E-Mail]' => 'max.mustermann@example.com',
            '[Mitgliedsnummer]' => 'GM-' . date('Y') . '-001',
            '[Fitnessstudio-Name]' => $gym->name,
            '[Adresse]' => $gym->address . ', ' . $gym->postal_code . ' ' . $gym->city,
            '[Telefon]' => $gym->phone,
            '[Website]' => $gym->website ?: 'https://ihr-studio.de',
            '[Vertragslaufzeit]' => '12 Monate',
            '[Monatsbeitrag]' => '49,90',
            '[Startdatum]' => now()->format('d.m.Y'),
            '[Enddatum]' => now()->addYear()->format('d.m.Y'),
            '[QR-Code-Link]' => 'https://members.gymportal.io/qr-code',
            '[Mitgliederbereich-Link]' => 'https://members.gymportal.io/' . $gym->slug,
            '[Datum]' => now()->format('d.m.Y'),
            '[Uhrzeit]' => now()->format('H:i'),
        ];
    }

    /**
     * Anhang zu einer E-Mail-Vorlage hochladen.
     */
    public function uploadAttachment(Request $request, EmailTemplate $emailTemplate)
    {
        $gym = $this->getCurrentGym();

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ], [
            'file.required' => 'Bitte wählen Sie eine Datei aus.',
            'file.mimes' => 'Nur PDF-Dateien sind erlaubt.',
            'file.max' => 'Die Datei darf maximal 10 MB groß sein.',
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $storagePath = "email-attachments/{$gym->id}/{$emailTemplate->id}";
        $uniqueName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $filePath = $file->storeAs($storagePath, $uniqueName, 'local');

        $maxSortOrder = $emailTemplate->fileAttachments()->max('sort_order') ?? 0;

        $attachment = EmailTemplateAttachment::create([
            'email_template_id' => $emailTemplate->id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sort_order' => $maxSortOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'attachment' => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_size' => $attachment->file_size,
                'formatted_file_size' => $attachment->formatted_file_size,
                'sort_order' => $attachment->sort_order,
            ],
            'message' => 'Anhang wurde erfolgreich hochgeladen.',
        ]);
    }

    /**
     * Anhang einer E-Mail-Vorlage löschen.
     */
    public function deleteAttachment(EmailTemplate $emailTemplate, EmailTemplateAttachment $attachment)
    {
        $gym = $this->getCurrentGym();

        if ($emailTemplate->gym_id !== $gym->id) {
            abort(404);
        }

        if ($attachment->email_template_id !== $emailTemplate->id) {
            abort(404);
        }

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Anhang wurde erfolgreich gelöscht.',
        ]);
    }
}
