<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zahlung fehlgeschlagen - {{ $gym->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .gym-logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        .email-body {
            padding: 30px;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .alert-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .alert-box h4 {
            color: #856404;
            margin-top: 0;
        }
        .info-box {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            @if($gym->logo_path)
                <img src="{{ Storage::disk('public')->url($gym->logo_path) }}" alt="{{ $gym->name }}" class="gym-logo">
            @endif
            <h1>Zahlung fehlgeschlagen</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Liebe/r {{ $member->first_name }},</p>

            <p>leider konnten wir Ihre letzte Zahlung nicht erfolgreich verarbeiten.</p>

            <div class="alert-box">
                <h4>Wichtiger Hinweis: Zugang vorübergehend gesperrt</h4>
                <p>Aufgrund der fehlgeschlagenen Zahlung wurde Ihr Zugang zu <strong>{{ $gym->name }}</strong> vorübergehend gesperrt. Sie können unsere Einrichtungen derzeit nicht nutzen.</p>
            </div>

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

            <p style="text-align: center;">
                <a href="https://members.gymportal.io/{{ $gym->slug }}" class="button">Zum Mitgliederbereich</a>
            </p>

            <div class="info-box">
                <p><strong>Benötigen Sie Hilfe?</strong></p>
                <p>Unser Team steht Ihnen gerne zur Verfügung. Kontaktieren Sie uns:</p>
                @if($gym->phone)
                    <p>Telefon: <strong>{{ $gym->phone }}</strong></p>
                @endif
                @if($gym->email)
                    <p>E-Mail: <strong>{{ $gym->email }}</strong></p>
                @endif
            </div>

            <p>Sobald die Zahlung erfolgreich verarbeitet wurde, wird Ihr Zugang automatisch wieder freigeschaltet.</p>

            <p>Mit freundlichen Grüßen<br>
            Ihr {{ $gym->name }} Team</p>

            <hr>
            <p style="font-size: 12px; color: #666;">Diese E-Mail wurde automatisch generiert. Bei Fragen antworten Sie nicht direkt auf diese E-Mail, sondern nutzen Sie die oben genannten Kontaktmöglichkeiten.</p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p><strong>{{ $gym->name }}</strong></p>
            @if($gym->address)
                <p>{{ $gym->address }}<br>
                {{ $gym->postal_code }} {{ $gym->city }}</p>
            @endif
            @if($gym->phone)
                <p>Telefon: {{ $gym->phone }}</p>
            @endif
            @if($gym->email)
                <p>E-Mail: {{ $gym->email }}</p>
            @endif
            @if($gym->website)
                <p>Website: <a href="{{ $gym->website }}">{{ $gym->website }}</a></p>
            @endif
        </div>
    </div>
</body>
</html>
