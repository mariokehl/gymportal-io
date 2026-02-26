@extends('emails.layouts.custom')

@section('title', 'SEPA-Lastschriftmandat erforderlich - ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        SEPA-Lastschriftmandat erforderlich
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Liebe/r {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        f&uuml;r die Abbuchung Ihres Mitgliedsbeitrags bei <strong style="color: #2d3748;">{{ $gym->name }}</strong> ben&ouml;tigen wir ein unterzeichnetes SEPA-Lastschriftmandat.
    </p>

    {{-- Mandate Info --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 15px 0 25px;">
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Mandatsreferenz</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">{{ $mandateReference }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; font-weight: bold; width: 40%; color: #b0adc5; font-size: 14px;">Frist</td>
            <td style="padding: 10px; border-bottom: 1px solid #edeff2; color: #718096; font-size: 14px;">{{ $deadline }}</td>
        </tr>
    </table>

    {{-- Warning --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                <p style="margin: 0; color: #975a16; font-size: 14px;">
                    <strong>Wichtig:</strong> Bitte senden Sie das unterzeichnete Mandat bis sp&auml;testens <strong>{{ $deadline }}</strong> an uns zur&uuml;ck, damit wir die Beitr&auml;ge wie vereinbart abbuchen k&ouml;nnen.
                </p>
            </td>
        </tr>
    </table>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Das SEPA-Lastschriftmandat finden Sie als PDF im Anhang dieser E-Mail. Bitte drucken Sie es aus, unterschreiben Sie es und senden Sie es an uns zur&uuml;ck.
    </p>

    @if($gym->phone || $gym->email)
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
            <tr>
                <td style="padding: 15px 20px; background-color: #f8fafc; border-radius: 5px;">
                    <p style="margin: 0 0 5px; color: #2d3748; font-size: 14px; font-weight: bold;">
                        Fragen?
                    </p>
                    <p style="margin: 0; color: #718096; font-size: 14px;">
                        Kontaktieren Sie uns:
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
    @endif

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Mit freundlichen Gr&uuml;&szlig;en<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
