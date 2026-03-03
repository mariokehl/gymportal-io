@extends('emails.layouts.custom')

@section('title', 'Zugang zur Mitglieder-App')

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        Mitglieder-App Zugang
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Hallo {{ $memberName }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sie haben einen Zugangslink zur Mitglieder-App angefordert. Mit diesem Link k&ouml;nnen Sie sich direkt in der App anmelden und auf alle Ihre Mitgliederfunktionen zugreifen.
    </p>

    {{-- CTA Button --}}
    <table class="body-action" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto; text-align: center;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td>
                            <a href="{{ $loginUrl }}" class="button button--primary" style="display: inline-block; color: #ffffff; text-decoration: none; border-radius: 3px; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;">
                                Zur Mitglieder-App
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em; margin-top: 25px;">
        Falls der Button nicht funktioniert, k&ouml;nnen Sie diesen Link kopieren:
    </p>
    <p style="color: #718096; font-size: 13px; line-height: 1.5em; background-color: #f8fafc; padding: 8px 12px; border-radius: 3px; word-break: break-all;">
        {{ $loginUrl }}
    </p>

    {{-- Features --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #f8fafc; border-left: 4px solid #3490dc;">
                <p style="margin: 0 0 10px; color: #2d3748; font-size: 14px; font-weight: bold;">
                    Was Sie in der App k&ouml;nnen:
                </p>
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">QR-Code f&uuml;r den Gym-Zugang anzeigen</td></tr>
                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Ihre Mitgliedschaft verwalten</td></tr>
                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Check-in Historie einsehen</td></tr>
                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Kontaktdaten aktualisieren</td></tr>
                    <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Zus&auml;tzliche Services nutzen</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- PWA Installation --}}
    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold; margin-top: 30px;">
        App installieren (PWA)
    </h2>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Die Mitglieder-App kann als Progressive Web App (PWA) auf Ihrem Smartphone installiert werden:
    </p>

    <p style="color: #2d3748; font-size: 14px; font-weight: bold; margin-bottom: 5px;">iOS (iPhone/iPad):</p>
    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 15px;">
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">1.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">&Ouml;ffnen Sie den Link in Safari</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">2.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Tippen Sie auf das Teilen-Symbol</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">3.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">W&auml;hlen Sie &quot;Zum Home-Bildschirm&quot;</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">4.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Tippen Sie auf &quot;Hinzuf&uuml;gen&quot;</td></tr>
    </table>

    <p style="color: #2d3748; font-size: 14px; font-weight: bold; margin-bottom: 5px;">Android:</p>
    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 15px;">
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">1.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">&Ouml;ffnen Sie den Link in Chrome</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">2.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Tippen Sie auf das Men&uuml; (3 Punkte)</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">3.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">W&auml;hlen Sie &quot;App installieren&quot; oder &quot;Zum Startbildschirm hinzuf&uuml;gen&quot;</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 14px;" valign="top">4.</td><td style="padding: 2px 0; color: #718096; font-size: 14px;">Folgen Sie den Anweisungen</td></tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
