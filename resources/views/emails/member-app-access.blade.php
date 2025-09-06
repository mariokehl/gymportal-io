<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zugang zur Mitglieder-App</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0;
            font-size: 24px;
        }
        .content {
            margin: 30px 0;
        }
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
        }
        .button:hover {
            background: #4338ca;
        }
        .info-box {
            background: #f7f8fa;
            border-left: 4px solid #4f46e5;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h3 {
            margin-top: 0;
            color: #4f46e5;
            font-size: 16px;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
        }
        .warning {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning strong {
            color: #ef4444;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .code {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $gymName }}</h1>
            <p style="color: #6b7280; margin: 10px 0 0 0;">Mitglieder-App Zugang</p>
        </div>

        <div class="content">
            <p>Hallo {{ $memberName }},</p>

            <p>Sie haben einen Zugangslink zur Mitglieder-App angefordert. Mit diesem Link können Sie sich direkt in der App anmelden und auf alle Ihre Mitgliederfunktionen zugreifen.</p>

            <div class="button-container">
                <a href="{{ $loginUrl }}" class="button">Zur Mitglieder-App</a>
            </div>

            <div class="info-box">
                <h3>Was Sie in der App können:</h3>
                <ul>
                    <li>QR-Code für den Gym-Zugang anzeigen</li>
                    <li>Ihre Mitgliedschaft verwalten</li>
                    <li>Check-in Historie einsehen</li>
                    <li>Kontaktdaten aktualisieren</li>
                    <li>Zusätzliche Services nutzen</li>
                </ul>
            </div>

            <div class="warning">
                <strong>Wichtig:</strong> Dieser Link ist aus Sicherheitsgründen nur <strong>{{ $expiresIn }}</strong> gültig.
                Danach müssen Sie einen neuen Link anfordern.
            </div>

            <h3 style="margin-top: 30px;">App installieren (PWA)</h3>
            <p>Die Mitglieder-App kann als Progressive Web App (PWA) auf Ihrem Smartphone installiert werden:</p>

            <div style="margin: 20px 0;">
                <strong>iOS (iPhone/iPad):</strong>
                <ol>
                    <li>Öffnen Sie den Link in Safari</li>
                    <li>Tippen Sie auf das Teilen-Symbol (□ mit Pfeil nach oben)</li>
                    <li>Wählen Sie "Zum Home-Bildschirm"</li>
                    <li>Tippen Sie auf "Hinzufügen"</li>
                </ol>
            </div>

            <div style="margin: 20px 0;">
                <strong>Android:</strong>
                <ol>
                    <li>Öffnen Sie den Link in Chrome</li>
                    <li>Tippen Sie auf das Menü (3 Punkte)</li>
                    <li>Wählen Sie "App installieren" oder "Zum Startbildschirm hinzufügen"</li>
                    <li>Folgen Sie den Anweisungen</li>
                </ol>
            </div>

            <p style="margin-top: 30px;">Falls der Button nicht funktioniert, können Sie diesen Link kopieren:</p>
            <p class="code" style="word-break: break-all;">{{ $loginUrl }}</p>
        </div>

        <div class="footer">
            <p>Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht darauf.</p>
            <p>Bei Fragen wenden Sie sich bitte an unser Team.</p>
            <p style="margin-top: 20px;">© {{ date('Y') }} {{ $gymName }}. Alle Rechte vorbehalten.</p>
        </div>
    </div>
</body>
</html>
