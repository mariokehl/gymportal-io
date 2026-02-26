@extends('emails.layouts.custom')

@section('title', 'Eingangsbestätigung Ihres Widerrufs')

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        Eingangsbest&auml;tigung Ihres Widerrufs
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sehr geehrte/r {{ $member->first_name }} {{ $member->last_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        hiermit best&auml;tigen wir den <strong style="color: #2d3748;">Eingang</strong> Ihres Widerrufs.
    </p>

    <h2 style="color: #2d3748; font-size: 16px; font-weight: bold;">
        Angaben zum Widerruf
    </h2>

    {{-- Info Table --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 15px 0 25px;">
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Vertragsnummer</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">#{{ $membership->id }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Tarif</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">{{ $membership->membershipPlan->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Eingangsdatum</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">{{ $withdrawalDate }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Eingangszeit</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">{{ $withdrawalTime }} Uhr</td>
        </tr>
    </table>

    @if($refundAmount > 0)
        {{-- Refund Box --}}
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
            <tr>
                <td style="padding: 15px 20px; background-color: #f0fff4; border: 1px solid #c6f6d5; border-radius: 5px;">
                    <p style="margin: 0 0 5px; color: #276749; font-size: 14px; font-weight: bold;">
                        Erstattung
                    </p>
                    <p style="margin: 0; color: #276749; font-size: 14px;">
                        Der Betrag von <strong>{{ number_format($refundAmount, 2, ',', '.') }} &euro;</strong> wird Ihnen innerhalb von 14 Tagen auf das urspr&uuml;ngliche Zahlungsmittel erstattet.
                    </p>
                </td>
            </tr>
        </table>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr><td style="border-top: 1px solid #edeff2; height: 20px;"></td></tr>
    </table>

    {{-- Legal Notice --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 15px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                <p style="margin: 0; color: #975a16; font-size: 13px;">
                    <strong>Hinweis:</strong> Diese Best&auml;tigung dokumentiert ausschlie&szlig;lich den Eingang Ihrer Widerrufserkl&auml;rung &uuml;ber unsere elektronische Widerrufsfunktion gem&auml;&szlig; &sect; 356a BGB.
                </p>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Mit freundlichen Gr&uuml;&szlig;en,
    </p>
    <p style="color: #2d3748; font-size: 16px; font-weight: bold;">
        {{ $gym->name }}
    </p>
@endsection
