<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\Gym;
use App\Models\Member;

class EmailTemplateService
{
    /**
     * Get the appropriate template for a specific type and gym.
     */
    public function getTemplate(Gym $gym, string $type): ?EmailTemplate
    {
        // First try to get the default template for this type
        $template = EmailTemplate::forGym($gym->id)
            ->ofType($type)
            ->default()
            ->active()
            ->first();

        // If no default found, get any active template of this type
        if (!$template) {
            $template = EmailTemplate::forGym($gym->id)
                ->ofType($type)
                ->active()
                ->first();
        }

        return $template;
    }

    /**
     * Render a template with member and gym data.
     */
    public function renderTemplate(
        EmailTemplate $template,
        Gym $gym,
        ?Member $member = null,
        array $additionalData = []
    ): array {
        $placeholderData = $this->buildPlaceholderData($gym, $member, $additionalData);
        return $template->replacePlaceholders($placeholderData);
    }

    /**
     * Get template and render it in one step.
     */
    public function getAndRenderTemplate(
        Gym $gym,
        string $type,
        ?Member $member = null,
        array $additionalData = []
    ): ?array {
        $template = $this->getTemplate($gym, $type);

        if (!$template) {
            return null;
        }

        return $this->renderTemplate($template, $gym, $member, $additionalData);
    }

    /**
     * Get template definitions for frontend template creation.
     */
    public function getTemplateDefinitions(): array
    {
        return [
            'welcome' => [
                'name' => 'Willkommens-E-Mail',
                'subject' => 'Herzlich willkommen bei [Fitnessstudio-Name]!',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>herzlich willkommen bei <strong>[Fitnessstudio-Name]</strong>! Wir freuen uns sehr, Sie als neues Mitglied in unserer Community begrüßen zu dürfen.</p>

                    <h2>Ihre Mitgliedschaft ist ab sofort aktiv</h2>
                    <p>Ihre Online-Anmeldung wurde erfolgreich verarbeitet. Sie können ab heute unser Fitnessstudio nutzen!</p>

                    <p><strong>Zugang zum Studio:</strong> Den Zugang erhalten Sie ganz einfach über den QR-Code in Ihrem persönlichen Mitgliederbereich.</p>

                    <h2>Ihr Mitgliederbereich</h2>
                    <p>Loggen Sie sich jetzt in Ihren persönlichen Mitgliederbereich ein:<br>
                    <strong><a href="[Mitgliederbereich-Link]">→ Zum Mitgliederbereich</a></strong></p>

                    <p>Hier finden Sie:</p>
                    <ul>
                        <li>Ihren persönlichen QR-Code für den Studiozugang</li>
                        <li>Ihre Vertrags- und Rechnungsdaten</li>
                        <li>Buchungsmöglichkeiten für Kurse</li>
                        <li>Aktuelle Informationen und Updates</li>
                    </ul>

                    <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung. Sie erreichen uns telefonisch unter <strong>[Telefon]</strong> oder per E-Mail.</p>

                    <p>Wir wünschen Ihnen viel Erfolg beim Training und freuen uns auf Ihren ersten Besuch!</p>

                    <p>Sportliche Grüße<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'confirmation' => [
                'name' => 'Bestätigungs-E-Mail',
                'subject' => 'Ihre Anmeldung bei [Fitnessstudio-Name] ist bestätigt',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>vielen Dank für Ihre Anmeldung bei <strong>[Fitnessstudio-Name]</strong>!</p>

                    <p>Ihre Mitgliedsdaten:</p>
                    <ul>
                        <li><strong>Mitgliedsnummer:</strong> [Mitgliedsnummer]</li>
                        <li><strong>Startdatum:</strong> [Startdatum]</li>
                        <li><strong>Vertragslaufzeit:</strong> [Vertragslaufzeit]</li>
                        <li><strong>Monatsbeitrag:</strong> [Monatsbeitrag] EUR</li>
                    </ul>

                    <p>Sie erhalten in Kürze weitere Informationen zur Nutzung unserer Einrichtungen.</p>

                    <p>Mit freundlichen Grüßen<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'reminder' => [
                'name' => 'Erinnerungs-E-Mail',
                'subject' => 'Wir vermissen Sie - [Fitnessstudio-Name]',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>wir möchten Sie daran erinnern, dass wir Sie schon lange nicht mehr in unserem Studio gesehen haben.</p>

                    <p>Kommen Sie doch wieder vorbei - wir freuen uns auf Sie!</p>

                    <p>Öffnungszeiten und weitere Informationen finden Sie auf unserer Website: <a href="[Website]">[Website]</a></p>

                    <p>Bei Fragen erreichen Sie uns unter [Telefon].</p>

                    <p>Sportliche Grüße<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'cancellation' => [
                'name' => 'Kündigungs-Bestätigung',
                'subject' => 'Bestätigung Ihrer Kündigung - [Fitnessstudio-Name]',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>wir bestätigen hiermit den Erhalt Ihrer Kündigung.</p>

                    <p>Ihre Mitgliedschaft endet zum <strong>[Enddatum]</strong>.</p>

                    <p>Bis zu diesem Datum können Sie selbstverständlich weiterhin alle Einrichtungen nutzen.</p>

                    <p>Wir bedauern Ihre Entscheidung und würden uns freuen, Sie vielleicht in Zukunft wieder bei uns begrüßen zu dürfen.</p>

                    <p>Mit freundlichen Grüßen<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'invoice' => [
                'name' => 'Rechnungs-E-Mail',
                'subject' => 'Ihre Rechnung von [Fitnessstudio-Name]',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>anbei erhalten Sie Ihre aktuelle Rechnung über <strong>[Monatsbeitrag] EUR</strong>.</p>

                    <p>Der Betrag wird wie vereinbart von Ihrem Konto abgebucht.</p>

                    <p>Bei Fragen zur Rechnung stehen wir Ihnen gerne zur Verfügung.</p>

                    <p>Mit freundlichen Grüßen<br>
                    Ihr [Fitnessstudio-Name] Team</p>

                    <p>Telefon: [Telefon]<br>
                    E-Mail: Antworten Sie einfach auf diese E-Mail</p>
                '
            ],
            'general' => [
                'name' => 'Allgemeine E-Mail',
                'subject' => 'Information von [Fitnessstudio-Name]',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>wir möchten Sie über Neuigkeiten in unserem Fitnessstudio informieren.</p>

                    <p>[Hier können Sie Ihren individuellen Inhalt einfügen]</p>

                    <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>

                    <p>Mit freundlichen Grüßen<br>
                    Ihr [Fitnessstudio-Name] Team</p>

                    <p>Kontakt:<br>
                    Telefon: [Telefon]<br>
                    Website: [Website]</p>
                '
            ],
            'payment_failed' => [
                'name' => 'Zahlung fehlgeschlagen',
                'subject' => 'Wichtig: Ihre Zahlung konnte nicht verarbeitet werden - [Fitnessstudio-Name]',
                'body' => '
                    <p>Liebe/r [Vorname],</p>

                    <p>leider konnten wir Ihre letzte Zahlung nicht erfolgreich verarbeiten.</p>

                    <h2 style="color: #dc3545;">Wichtiger Hinweis: Zugang vorübergehend gesperrt</h2>
                    <p>Aufgrund der fehlgeschlagenen Zahlung wurde Ihr Zugang zu <strong>[Fitnessstudio-Name]</strong> vorübergehend gesperrt. Sie können unsere Einrichtungen derzeit nicht nutzen.</p>

                    <h3>Was ist passiert?</h3>
                    <p>Die automatische Abbuchung Ihres Mitgliedsbeitrags konnte nicht durchgeführt werden. Dies kann verschiedene Gründe haben:</p>
                    <ul>
                        <li>Unzureichende Deckung auf Ihrem Konto</li>
                        <li>Abgelaufene oder gesperrte Zahlungsmethode</li>
                        <li>Technische Probleme bei der Zahlungsabwicklung</li>
                    </ul>

                    <h3>Was müssen Sie tun?</h3>
                    <p>Um Ihren Zugang wiederherzustellen, bitten wir Sie:</p>
                    <ol>
                        <li>Überprüfen Sie Ihre hinterlegte Zahlungsmethode</li>
                        <li>Stellen Sie sicher, dass ausreichend Guthaben verfügbar ist</li>
                        <li>Loggen Sie sich in Ihren Mitgliederbereich ein, um die Zahlung zu aktualisieren</li>
                    </ol>

                    <p style="text-align: center;"><strong><a href="[Mitgliederbereich-Link]">→ Zum Mitgliederbereich</a></strong></p>

                    <p><strong>Benötigen Sie Hilfe?</strong><br>
                    Unser Team steht Ihnen gerne zur Verfügung. Kontaktieren Sie uns unter <strong>[Telefon]</strong>.</p>

                    <p>Sobald die Zahlung erfolgreich verarbeitet wurde, wird Ihr Zugang automatisch wieder freigeschaltet.</p>

                    <p>Mit freundlichen Grüßen<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ]
        ];
    }

    /**
     * Build placeholder data array from gym, member and additional data.
     */
    public function buildPlaceholderData(Gym $gym, ?Member $member = null, array $additionalData = []): array
    {
        $data = [];

        // Member data
        if ($member) {
            $data = array_merge($data, [
                '[Vorname]' => $member->first_name ?? '',
                '[Nachname]' => $member->last_name ?? '',
                '[Anrede]' => $this->getAnrede($member),
                '[E-Mail]' => $member->email ?? '',
                '[Mitgliedsnummer]' => $member->membership_number ?? '',
            ]);
        } else {
            $data = array_merge($data, [
                '[Vorname]' => '',
                '[Nachname]' => '',
                '[Anrede]' => '',
                '[E-Mail]' => '',
                '[Mitgliedsnummer]' => '',
            ]);
        }

        // Gym data
        $data = array_merge($data, [
            '[Fitnessstudio-Name]' => $gym->name ?? '',
            '[Adresse]' => $this->formatAddress($gym),
            '[Telefon]' => $gym->phone ?? '',
            '[Website]' => $gym->website ?? '',
        ]);

        // System data
        $data = array_merge($data, [
            '[Mitgliederbereich-Link]' => $this->generateMemberPortalLink($gym),
            '[Datum]' => now()->format('d.m.Y'),
            '[Uhrzeit]' => now()->format('H:i'),
        ]);

        // Contract data (if member has active contract)
        // Prioritize linked paid membership if available (e.g., when current membership is a free trial)
        if ($member && $member->activeMembership()) {
            $contract = $member->activeMembership();

            // If current membership has a linked paid membership, use that for contract data
            if ($contract->linkedPaidMembership) {
                $contract = $contract->linkedPaidMembership;
            }

            $data = array_merge($data, [
                '[Vertragslaufzeit]' => $contract->membershipPlan->commitment_months . ' Monate',
                '[Monatsbeitrag]' => number_format($contract->membershipPlan->price, 2, ',', '.'),
                '[Startdatum]' => $contract->start_date ? $contract->start_date->format('d.m.Y') : '',
                '[Enddatum]' => $contract->end_date ? $contract->end_date->format('d.m.Y') : '',
            ]);
        } else {
            $data = array_merge($data, [
                '[Vertragslaufzeit]' => '',
                '[Monatsbeitrag]' => '',
                '[Startdatum]' => '',
                '[Enddatum]' => '',
            ]);
        }

        return array_merge($data, $additionalData);
    }

    private function getAnrede(?Member $member): string
    {
        if (!$member) return '';
        return isset($member->gender) && $member->gender === 'female' ? 'Frau' : 'Herr';
    }

    private function formatAddress(Gym $gym): string
    {
        $parts = array_filter([
            $gym->address,
            $gym->postal_code . ' ' . $gym->city
        ]);
        return implode(', ', $parts);
    }

    private function generateMemberPortalLink(Gym $gym): string
    {
        return "https://members.gymportal.io/{$gym->slug}";
    }
}
