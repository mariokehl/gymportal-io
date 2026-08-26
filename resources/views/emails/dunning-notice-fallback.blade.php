@extends('emails.layouts.custom')

@section('title', $heading . ' - ' . $gym->name)

@section('content')
    <h1 style="margin-top: 0; color: #2d3748; font-size: 19px; font-weight: bold;">
        {{ $heading }}
    </h1>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Liebe/r {{ $member->first_name }},
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        @if ($level === 1)
            sicher ist es Ihnen entgangen: F&uuml;r Ihre Mitgliedschaft bei <strong>{{ $gym->name }}</strong> ist noch ein Betrag offen.
        @elseif ($level === 2)
            trotz unserer Zahlungserinnerung konnten wir bisher keinen Zahlungseingang feststellen. Wir mahnen den offenen Betrag hiermit an.
        @else
            leider ist der offene Betrag trotz Erinnerung und Mahnung weiterhin nicht ausgeglichen. Wir fordern Sie hiermit letztmalig zur Zahlung auf.
        @endif
    </p>

    {{-- Amount overview --}}
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
        <tr>
            <td style="padding: 15px 20px; background-color: #f8fafc; border-left: 4px solid #3490dc;">
                <table cellpadding="0" cellspacing="0" role="presentation" width="100%">
                    <tr>
                        <td style="padding: 3px 0; color: #718096; font-size: 14px;">Offener Betrag</td>
                        <td style="padding: 3px 0; color: #2d3748; font-size: 14px; font-weight: bold;" align="right">
                            {{ $dunningData['[Offener-Betrag]'] ?? '' }} EUR
                        </td>
                    </tr>
                    @if (! empty($dunningData['[Mahngebuehr]']) && $dunningData['[Mahngebuehr]'] !== '0,00')
                        <tr>
                            <td style="padding: 3px 0; color: #718096; font-size: 14px;">Mahngeb&uuml;hr</td>
                            <td style="padding: 3px 0; color: #2d3748; font-size: 14px; font-weight: bold;" align="right">
                                {{ $dunningData['[Mahngebuehr]'] }} EUR
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 0; color: #718096; font-size: 14px;">Gesamtbetrag</td>
                            <td style="padding: 3px 0; color: #2d3748; font-size: 14px; font-weight: bold;" align="right">
                                {{ $dunningData['[Gesamtbetrag]'] ?? '' }} EUR
                            </td>
                        </tr>
                    @endif
                    @if (! empty($dunningData['[Faelligkeitsdatum]']))
                        <tr>
                            <td style="padding: 3px 0; color: #718096; font-size: 14px;">F&auml;llig seit</td>
                            <td style="padding: 3px 0; color: #2d3748; font-size: 14px;" align="right">
                                {{ $dunningData['[Faelligkeitsdatum]'] }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if (! empty($dunningData['[Zahlungsfrist]']))
        <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
            @if ($level === 1)
                Wir bitten Sie, den Betrag bis zum <strong>{{ $dunningData['[Zahlungsfrist]'] }}</strong> auszugleichen.
                Falls sich die Zahlung bereits &uuml;berschnitten hat, betrachten Sie diese E-Mail bitte als gegenstandslos.
            @else
                Bitte begleichen Sie den Gesamtbetrag bis zum <strong>{{ $dunningData['[Zahlungsfrist]'] }}</strong>.
            @endif
        </p>
    @endif

    @if ($level === 3)
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 25px 0;">
            <tr>
                <td style="padding: 15px 20px; background-color: #fffff0; border: 1px solid #fefcbf; border-radius: 5px;">
                    <p style="margin: 0; color: #975a16; font-size: 14px;">
                        Geht bis dahin keine Zahlung ein, m&uuml;ssen wir die Forderung an unseren Inkassopartner
                        &uuml;bergeben. Dadurch entstehen weitere Kosten, die Sie zu tragen haben.
                    </p>
                </td>
            </tr>
        </table>
    @endif

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Ihre Zahlungsdaten k&ouml;nnen Sie jederzeit in Ihrem Mitgliederbereich pr&uuml;fen:<br>
        <strong><a href="{{ $dunningData['[Mitgliederbereich-Link]'] ?? '' }}">&rarr; Zum Mitgliederbereich</a></strong>
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        @if ($level === 3)
            Falls Sie die Forderung f&uuml;r unberechtigt halten oder eine Zahlungsvereinbarung treffen m&ouml;chten,
            melden Sie sich bitte umgehend unter <strong>{{ $gym->phone }}</strong>.
        @else
            Bei Fragen erreichen Sie uns unter <strong>{{ $gym->phone }}</strong>.
        @endif
    </p>

    <p style="color: #718096; font-size: 16px; line-height: 1.5em;">
        Sportliche Gr&uuml;&szlig;e<br>
        Ihr {{ $gym->name }} Team
    </p>
@endsection
