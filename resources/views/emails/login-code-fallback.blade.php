@extends('emails.layouts.custom')

@section('title', 'Anmeldecode für ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        Ihr Anmeldecode
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Hallo {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sie haben einen Anmeldecode f&uuml;r den Zugang zum {{ $gym->name }} Mitglieder-Portal angefordert.
    </p>

    {{-- Code Box --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px 0;">
        <tr>
            <td align="center">
                <table cellpadding="0" cellspacing="0" role="presentation" style="border: 2px dashed #3490dc; border-radius: 5px;">
                    <tr>
                        <td style="padding: 20px 40px; text-align: center;">
                            <p style="margin: 0 0 5px; font-size: 12px; font-weight: 600; color: #b0adc5; text-transform: uppercase; letter-spacing: 1px;">
                                Ihr Anmeldecode
                            </p>
                            <p style="margin: 0; font-size: 36px; font-weight: 900; color: #3490dc; letter-spacing: 8px; font-family: 'Courier New', Monaco, monospace;">{{ $code }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Instructions --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #f8fafc; border-left: 4px solid #3490dc;">
                <p style="margin: 0 0 10px; color: #2d3748; font-size: 14px; font-weight: bold;">
                    So melden Sie sich an:
                </p>
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">1.&nbsp;&nbsp;</td>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;">&Ouml;ffnen Sie die Mitglieder-App oder Website</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">2.&nbsp;&nbsp;</td>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;">Geben Sie diesen 6-stelligen Code ein</td>
                    </tr>
                    <tr>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;" valign="top">3.&nbsp;&nbsp;</td>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;">Klicken Sie auf &quot;Anmelden&quot;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Security Warning --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                <p style="margin: 0 0 8px; color: #975a16; font-size: 14px; font-weight: bold;">
                    Sicherheitshinweise
                </p>
                <table cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td>
                        <td style="padding: 2px 0; color: #975a16; font-size: 13px;">Dieser Code ist nur <strong>{{ $expiryGrace }}</strong> g&uuml;ltig</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td>
                        <td style="padding: 2px 0; color: #975a16; font-size: 13px;">Der Code kann nur <strong>einmal</strong> verwendet werden</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px 8px 2px 0; color: #975a16; font-size: 13px;" valign="top">&bull;</td>
                        <td style="padding: 2px 0; color: #975a16; font-size: 13px;">Teilen Sie den Code <strong>niemals</strong> mit anderen</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 14px; line-height: 1.5em;">
        Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail.
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
