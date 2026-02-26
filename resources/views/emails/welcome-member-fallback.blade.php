@extends('emails.layouts.custom')

@section('title', 'Willkommen bei ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        Willkommen bei {{ $gym->name }}!
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Liebe/r {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        herzlich willkommen bei <strong style="color: #2d3748;">{{ $gym->name }}</strong>! Wir freuen uns sehr, Sie als neues Mitglied in unserer Community begr&uuml;&szlig;en zu d&uuml;rfen.
    </p>

    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold;">
        Ihre Mitgliedschaft ist ab sofort aktiv
    </h2>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Ihre Online-Anmeldung wurde erfolgreich verarbeitet. Sie k&ouml;nnen ab heute unser Fitnessstudio nutzen!
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        <strong style="color: #2d3748;">Zugang zum Studio:</strong> Den Zugang erhalten Sie ganz einfach &uuml;ber den QR-Code in Ihrem pers&ouml;nlichen Mitgliederbereich.
    </p>

    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold;">
        Ihr Mitgliederbereich
    </h2>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Loggen Sie sich jetzt in Ihren pers&ouml;nlichen Mitgliederbereich ein:
    </p>

    {{-- CTA Button --}}
    <table class="body-action" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto; text-align: center;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td>
                            <a href="https://members.gymportal.io/{{ $gym->slug }}" class="button button--primary" style="display: inline-block; color: #ffffff; text-decoration: none; border-radius: 3px; background-color: #3490dc; border-top: 10px solid #3490dc; border-right: 18px solid #3490dc; border-bottom: 10px solid #3490dc; border-left: 18px solid #3490dc;">
                                Zum Mitgliederbereich
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">Hier finden Sie:</p>

    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 0 20px;">
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Ihren pers&ouml;nlichen QR-Code f&uuml;r den Studiozugang</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Ihre Vertrags- und Rechnungsdaten</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Buchungsm&ouml;glichkeiten f&uuml;r Kurse</td></tr>
        <tr><td style="padding: 2px 8px 2px 0; color: #718096; font-size: 15px;" valign="top">&bull;</td><td style="padding: 2px 0; color: #718096; font-size: 15px;">Aktuelle Informationen und Updates</td></tr>
    </table>

    @if($gym->phone)
        <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
            Bei Fragen stehen wir Ihnen gerne zur Verf&uuml;gung. Sie erreichen uns telefonisch unter <strong style="color: #2d3748;">{{ $gym->phone }}</strong> oder per E-Mail.
        </p>
    @endif

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Wir w&uuml;nschen Ihnen viel Erfolg beim Training und freuen uns auf Ihren ersten Besuch!
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
