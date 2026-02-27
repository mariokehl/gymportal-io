@extends('emails.layouts.custom')

@section('title', 'Zahlung fehlgeschlagen - ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        Zahlung fehlgeschlagen
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Liebe/r {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        leider konnten wir Ihre letzte Zahlung nicht erfolgreich verarbeiten.
    </p>

    {{-- Alert Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                <p style="margin: 0 0 5px; color: #975a16; font-size: 14px; font-weight: bold;">
                    Wichtiger Hinweis: Zugang vor&uuml;bergehend gesperrt
                </p>
                <p style="margin: 0; color: #975a16; font-size: 14px;">
                    Aufgrund der fehlgeschlagenen Zahlung wurde Ihr Zugang zu <strong>{{ $gym->name }}</strong> vor&uuml;bergehend gesperrt. Sie k&ouml;nnen unsere Einrichtungen derzeit nicht nutzen.
                </p>
            </td>
        </tr>
    </table>

    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold;">
        Was ist passiert?
    </h2>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Die automatische Abbuchung Ihres Mitgliedsbeitrags konnte nicht durchgef&uuml;hrt werden. Dies kann verschiedene Gr&uuml;nde haben:
    </p>

    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 20px;">
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Unzureichende Deckung auf Ihrem Konto</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Abgelaufene oder gesperrte Zahlungsmethode</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Technische Probleme bei der Zahlungsabwicklung</td></tr>
    </table>

    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold;">
        Was m&uuml;ssen Sie tun?
    </h2>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Um Ihren Zugang wiederherzustellen, bitten wir Sie:
    </p>

    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 20px;">
        <tr><td style="padding: 3px 8px 3px 0; color: #718096; font-size: 15px;" valign="top">1.</td><td style="padding: 3px 0; color: #718096; font-size: 15px;">&Uuml;berpr&uuml;fen Sie Ihre hinterlegte Zahlungsmethode</td></tr>
        <tr><td style="padding: 3px 8px 3px 0; color: #718096; font-size: 15px;" valign="top">2.</td><td style="padding: 3px 0; color: #718096; font-size: 15px;">Stellen Sie sicher, dass ausreichend Guthaben verf&uuml;gbar ist</td></tr>
        <tr><td style="padding: 3px 8px 3px 0; color: #718096; font-size: 15px;" valign="top">3.</td><td style="padding: 3px 0; color: #718096; font-size: 15px;">Loggen Sie sich in Ihren Mitgliederbereich ein, um die Zahlung zu aktualisieren</td></tr>
    </table>

    {{-- CTA Button --}}
    <table class="body-action" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto; text-align: center;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td>
                            <a href="https://members.gymportal.io/{{ $gym->slug }}" class="button button--red" style="display: inline-block; color: #ffffff; text-decoration: none; border-radius: 3px; background-color: #e3342f; border-top: 10px solid #e3342f; border-right: 18px solid #e3342f; border-bottom: 10px solid #e3342f; border-left: 18px solid #e3342f;">
                                Zum Mitgliederbereich
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Contact Info --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #f8fafc; border-radius: 5px;">
                <p style="margin: 0 0 5px; color: #2d3748; font-size: 14px; font-weight: bold;">
                    Ben&ouml;tigen Sie Hilfe?
                </p>
                <p style="margin: 0; color: #718096; font-size: 14px;">
                    Unser Team steht Ihnen gerne zur Verf&uuml;gung:
                    @if($gym->phone)
                        <br>Telefon: <strong style="color: #2d3748;">{{ $gym->phone }}</strong>
                    @endif
                    @if($gym->email)
                        <br>E-Mail: <strong style="color: #2d3748;">{{ $gym->email }}</strong>
                    @endif
                </p>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sobald die Zahlung erfolgreich verarbeitet wurde, wird Ihr Zugang automatisch wieder freigeschaltet.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
