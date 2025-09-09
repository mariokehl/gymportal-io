<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Willkommen bei {{ $gym->name }}</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            @if($gym->logo_path)
                <img src="{{ asset('storage/' . $gym->logo_path) }}" alt="{{ $gym->name }}" class="gym-logo">
            @endif
            <h1>Willkommen bei {{ $gym->name }}!</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Liebe/r {{ $member->first_name }},</p>

            <p>herzlich willkommen bei <strong>{{ $gym->name }}</strong>! Wir freuen uns sehr, Sie als neues Mitglied in unserer Community begrüßen zu dürfen.</p>

            <h3>Ihre Mitgliedschaft ist ab sofort aktiv</h3>
            <p>Ihre Online-Anmeldung wurde erfolgreich verarbeitet. Sie können ab heute unser Fitnessstudio nutzen!</p>

            <p><strong>Zugang zum Studio:</strong> Den Zugang erhalten Sie ganz einfach über den QR-Code in Ihrem persönlichen Mitgliederbereich.</p>

            <h3>Ihr Mitgliederbereich</h3>
            <p>Loggen Sie sich jetzt in Ihren persönlichen Mitgliederbereich ein:</p>
            <p style="text-align: center;">
                <a href="https://members.gymportal.io/{{ $gym->slug }}" class="button">→ Zum Mitgliederbereich</a>
            </p>

            <p>Hier finden Sie:</p>
            <ul>
                <li>Ihren persönlichen QR-Code für den Studiozugang</li>
                <li>Ihre Vertrags- und Rechnungsdaten</li>
                <li>Buchungsmöglichkeiten für Kurse</li>
                <li>Aktuelle Informationen und Updates</li>
            </ul>

            <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung. Sie erreichen uns telefonisch unter <strong>{{ $gym->phone }}</strong> oder per E-Mail.</p>

            <p>Wir wünschen Ihnen viel Erfolg beim Training und freuen uns auf Ihren ersten Besuch!</p>

            <p>Sportliche Grüße<br>
            Ihr {{ $gym->name }} Team</p>

            <hr>
            <p style="font-size: 12px; color: #666;">Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht direkt auf diese E-Mail.</p>
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
