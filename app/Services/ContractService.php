<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContractService
{
    public function __construct(
        private EmailTemplateService $emailTemplateService
    ) {}

    /**
     * PDF-Vertrag für eine Mitgliedschaft generieren.
     * Gibt den Storage-Pfad zurück oder null bei Fehler/Deaktivierung.
     */
    public function generateContract(Membership $membership): ?string
    {
        $member = $membership->member;
        $gym = $member->gym;

        if (!$gym->isOnlineContractEnabled()) {
            return null;
        }

        $contractSettings = $gym->contract_settings;
        $templateBody = $contractSettings['contract_template_body'] ?? null;

        if (!$templateBody) {
            Log::warning('Vertragsvorlage fehlt', ['gym_id' => $gym->id]);
            return null;
        }

        try {
            $renderedBody = $this->renderContractBody($templateBody, $membership);

            $pdf = Pdf::loadView('pdf.contract', [
                'content' => $renderedBody,
                'gym' => $gym,
                'member' => $member,
                'membership' => $membership,
                'title' => $contractSettings['contract_template_subject'] ?? 'Mitgliedschaftsvertrag',
            ]);

            $pdf->setPaper('a4', 'portrait');

            $fileName = sprintf(
                'contracts/%d/%d/Vertrag_%s_%s.pdf',
                $gym->id,
                $member->id,
                $member->member_number,
                now()->format('Y-m-d_His')
            );

            Storage::disk('local')->put($fileName, $pdf->output());

            $membership->update(['contract_file_path' => $fileName]);

            Log::info('Vertrag generiert', [
                'membership_id' => $membership->id,
                'member_id' => $member->id,
                'path' => $fileName,
            ]);

            return $fileName;
        } catch (\Exception $e) {
            Log::error('Vertragsgenerierung fehlgeschlagen', [
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Vertrag nachträglich generieren (gleiche Logik).
     */
    public function generateContractRetroactively(Membership $membership): ?string
    {
        return $this->generateContract($membership);
    }

    /**
     * Vertragsvorlage mit Platzhaltern rendern.
     */
    private function renderContractBody(string $templateBody, Membership $membership): string
    {
        $member = $membership->member;
        $gym = $member->gym;

        // Standard-Platzhalter aus EmailTemplateService wiederverwenden
        $placeholderData = $this->emailTemplateService->buildPlaceholderData(
            $gym,
            $member,
            $this->getContractSpecificPlaceholders($membership)
        );

        $renderedBody = $templateBody;
        foreach ($placeholderData as $placeholder => $value) {
            $renderedBody = str_replace($placeholder, (string) $value, $renderedBody);
        }

        return $renderedBody;
    }

    /**
     * Vertragsspezifische Platzhalter.
     */
    private function getContractSpecificPlaceholders(Membership $membership): array
    {
        $member = $membership->member;
        $plan = $membership->membershipPlan;

        return [
            // Mitglied-Zusatzdaten
            '[Geburtsdatum]' => $member->birth_date ? $member->birth_date->format('d.m.Y') : '',
            '[Strasse]' => $member->address ?? '',
            '[PLZ]' => $member->postal_code ?? '',
            '[Ort]' => $member->city ?? '',
            // Vertragsdaten
            '[Vertragsnummer]' => (string) $membership->id,
            '[Vertragsdatum]' => now()->format('d.m.Y'),
            '[Kuendigungsfrist]' => $plan->formatted_cancellation_period ?? 'Keine',
            '[Aktivierungsgebuehr]' => $plan->setup_fee
                ? number_format($plan->setup_fee, 2, ',', '.') . ' €'
                : 'Keine',
            '[Aufnahmegebuehr]' => $plan->setup_fee
                ? number_format($plan->setup_fee, 2, ',', '.') . ' €'
                : 'Keine',
            '[Abrechnungszyklus]' => $plan->billing_cycle_text ?? $plan->billing_cycle ?? '',
            '[Tarif-Name]' => $plan->name ?? '',
        ];
    }

    /**
     * Vollständiger Pfad für einen Vertrag-Download.
     */
    public function getContractFullPath(Membership $membership): ?string
    {
        if (!$membership->contract_file_path) {
            return null;
        }
        return Storage::disk('local')->path($membership->contract_file_path);
    }

    /**
     * Alle verfügbaren Platzhalter für die Vertragsvorlage.
     */
    public static function getAvailablePlaceholders(): array
    {
        return [
            'member' => [
                '[Vorname]' => 'Vorname des Mitglieds',
                '[Nachname]' => 'Nachname des Mitglieds',
                '[Anrede]' => 'Anrede (Herr/Frau)',
                '[E-Mail]' => 'E-Mail-Adresse',
                '[Mitgliedsnummer]' => 'Mitgliedsnummer',
                '[Geburtsdatum]' => 'Geburtsdatum des Mitglieds',
                '[Strasse]' => 'Straße und Hausnummer',
                '[PLZ]' => 'Postleitzahl des Mitglieds',
                '[Ort]' => 'Wohnort des Mitglieds',
            ],
            'gym' => [
                '[Fitnessstudio-Name]' => 'Name des Fitnessstudios',
                '[Adresse]' => 'Vollständige Adresse des Studios',
                '[Telefon]' => 'Telefonnummer',
                '[Website]' => 'Website-URL',
            ],
            'contract' => [
                '[Vertragslaufzeit]' => 'Laufzeit des Vertrags in Monaten',
                '[Monatsbeitrag]' => 'Monatlicher Beitrag',
                '[Startdatum]' => 'Vertragsbeginn',
                '[Enddatum]' => 'Vertragsende',
                '[Vertragsnummer]' => 'Eindeutige Vertragsnummer',
                '[Vertragsdatum]' => 'Datum der Vertragserstellung',
                '[Tarif-Name]' => 'Name des gewählten Tarifs',
                '[Abrechnungszyklus]' => 'Abrechnungszyklus (monatlich, quartalsweise etc.)',
                '[Kuendigungsfrist]' => 'Kündigungsfrist',
                '[Aktivierungsgebuehr]' => 'Einmalige Aktivierungsgebühr',
                '[Aufnahmegebuehr]' => 'Aufnahmegebühr (Alias für Aktivierungsgebühr)',
            ],
            'system' => [
                '[Datum]' => 'Aktuelles Datum',
                '[Uhrzeit]' => 'Aktuelle Uhrzeit',
            ],
        ];
    }
}
