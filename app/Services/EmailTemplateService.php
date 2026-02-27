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
        $definitions = [
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

                    <p>Sportliche Grüße<br>
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

                    <p>Sportliche Grüße<br>
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

                    <p>Sportliche Grüße<br>
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

                    <p>Sportliche Grüße<br>
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

                    <p>Sportliche Grüße<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'login_code' => [
                'name' => 'Anmeldecode',
                'subject' => 'Ihr Anmeldecode für [Fitnessstudio-Name]',
                'body' => '
                    <p>Hallo [Vorname],</p>

                    <p>Sie haben einen Anmeldecode für den Zugang zum [Fitnessstudio-Name] Mitglieder-Portal angefordert.</p>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px 0;">
                        <tr>
                            <td align="center">
                                <table cellpadding="0" cellspacing="0" role="presentation" style="border: 2px dashed #3490dc; border-radius: 5px;">
                                    <tr>
                                        <td style="padding: 20px 40px; text-align: center;">
                                            <p style="margin: 0 0 5px; font-size: 12px; font-weight: 600; color: #b0adc5; text-transform: uppercase; letter-spacing: 1px;">Ihr Anmeldecode</p>
                                            <p style="margin: 0; font-size: 36px; font-weight: 900; color: #3490dc; letter-spacing: 8px; font-family: \'Courier New\', Monaco, monospace;">[Anmeldecode]</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
                        <tr>
                            <td style="padding: 15px 20px; background-color: #f8fafc; border-left: 4px solid #3490dc;">
                                <p style="margin: 0 0 10px; color: #2d3748; font-size: 14px; font-weight: bold;">So melden Sie sich an:</p>
                                <table cellpadding="0" cellspacing="0" role="presentation">
                                    <tr><td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">1.&nbsp;&nbsp;</td><td style="padding: 3px 0; color: #718096; font-size: 14px;">Öffnen Sie die Mitglieder-App oder Website</td></tr>
                                    <tr><td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">2.&nbsp;&nbsp;</td><td style="padding: 3px 0; color: #718096; font-size: 14px;">Geben Sie diesen 6-stelligen Code ein</td></tr>
                                    <tr><td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">3.&nbsp;&nbsp;</td><td style="padding: 3px 0; color: #718096; font-size: 14px;">Klicken Sie auf &quot;Anmelden&quot;</td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
                        <tr>
                            <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                                <p style="margin: 0 0 8px; color: #975a16; font-size: 14px; font-weight: bold;">Sicherheitshinweise</p>
                                <table cellpadding="0" cellspacing="0" role="presentation">
                                    <tr><td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #975a16; font-size: 13px;">Dieser Code ist nur <strong>wenige Minuten</strong> gültig</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #975a16; font-size: 13px;">Der Code kann nur <strong>einmal</strong> verwendet werden</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #975a16; font-size: 13px;">Teilen Sie den Code <strong>niemals</strong> mit anderen</td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <p style="color: #718096; font-size: 14px; line-height: 1.5em;">Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail.</p>

                    <p>Sportliche Grüße<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ],
            'member_app_access' => [
                'name' => 'App-Zugangslink',
                'subject' => 'Zugang zur [Fitnessstudio-Name] Mitglieder-App',
                'body' => '
                    <p>Hallo [Vorname],</p>

                    <p>Sie haben einen Zugangslink zur Mitglieder-App angefordert. Mit diesem Link können Sie sich direkt in der App anmelden und auf alle Ihre Mitgliederfunktionen zugreifen.</p>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto; text-align: center;">
                        <tr>
                            <td align="center">
                                <table cellpadding="0" cellspacing="0" role="presentation">
                                    <tr>
                                        <td>
                                            <a href="[Mitgliederbereich-Link]" style="display: inline-block; color: #ffffff; text-decoration: none; border-radius: 3px; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;">Zur Mitglieder-App</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <p>Falls der Button nicht funktioniert, können Sie diesen Link kopieren:</p>
                    <p style="color: #718096; font-size: 13px; line-height: 1.5em; background-color: #f8fafc; padding: 8px 12px; border-radius: 3px; word-break: break-all;">[Mitgliederbereich-Link]</p>

                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
                        <tr>
                            <td style="padding: 15px 20px; background-color: #f8fafc; border-left: 4px solid #3490dc;">
                                <p style="margin: 0 0 10px; color: #2d3748; font-size: 14px; font-weight: bold;">Was Sie in der App können:</p>
                                <table cellpadding="0" cellspacing="0" role="presentation">
                                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">QR-Code für den Gym-Zugang anzeigen</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Ihre Mitgliedschaft verwalten</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Check-in Historie einsehen</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Kontaktdaten aktualisieren</td></tr>
                                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Zusätzliche Services nutzen</td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <h2>App installieren (PWA)</h2>

                    <p>Die Mitglieder-App kann als Progressive Web App (PWA) auf Ihrem Smartphone installiert werden:</p>

                    <h3>iOS (iPhone/iPad):</h3>
                    <ol style="padding-left: 0; margin-left: 0; list-style-position: inside;">
                        <li>Öffnen Sie den Link in Safari</li>
                        <li>Tippen Sie auf das Teilen-Symbol</li>
                        <li>Wählen Sie &quot;Zum Home-Bildschirm&quot;</li>
                        <li>Tippen Sie auf &quot;Hinzufügen&quot;</li>
                    </ol>

                    <h3>Android:</h3>
                    <ol style="padding-left: 0; margin-left: 0; list-style-position: inside;">
                        <li>Öffnen Sie den Link in Chrome</li>
                        <li>Tippen Sie auf das Menü (3 Punkte)</li>
                        <li>Wählen Sie &quot;App installieren&quot; oder &quot;Zum Startbildschirm hinzufügen&quot;</li>
                        <li>Folgen Sie den Anweisungen</li>
                    </ol>

                    <p>Sportliche Grüße<br>
                    Ihr [Fitnessstudio-Name] Team</p>
                '
            ]
        ];

        // Remove PHP source code indentation from body strings
        foreach ($definitions as &$definition) {
            $definition['body'] = $this->dedentHtml($definition['body']);
        }

        return $definitions;
    }

    /**
     * Remove common leading whitespace from a multi-line HTML string.
     */
    private function dedentHtml(string $html): string
    {
        $html = trim($html, "\n");
        $lines = explode("\n", $html);

        // Find minimum indentation (ignoring empty lines)
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $indent = strlen($line) - strlen(ltrim($line));
            $minIndent = min($minIndent, $indent);
        }

        if ($minIndent === 0 || $minIndent === PHP_INT_MAX) {
            return trim($html);
        }

        // Remove the common indentation from each line
        $dedented = array_map(function ($line) use ($minIndent) {
            return strlen($line) >= $minIndent ? substr($line, $minIndent) : $line;
        }, $lines);

        return implode("\n", $dedented);
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
