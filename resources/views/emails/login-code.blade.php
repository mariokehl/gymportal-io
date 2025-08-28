<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Anmeldecode für {{ $gym->name }}</title>
    <style>
        /* Email-safe CSS */
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: {{ $theme['text_color'] ?? '#333333' }};
            background-color: #f8fafc;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .header {
            background: linear-gradient(135deg, {{ $theme['primary_color'] ?? '#e11d48' }}, {{ $theme['accent_color'] ?? '#10b981' }});
            padding: 40px 30px;
            text-align: center;
            color: #ffffff;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .header p {
            margin: 8px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }

        .logo {
            max-height: 60px;
            width: auto;
            margin-bottom: 20px;
            display: block;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 18px;
            color: {{ $theme['text_color'] ?? '#1f2937' }};
            margin-bottom: 24px;
        }

        .code-section {
            text-align: center;
            margin: 40px 0;
        }

        .code-box {
            background: linear-gradient(135deg, {{ $theme['primary_color'] ?? '#e11d48' }}15, {{ $theme['accent_color'] ?? '#10b981' }}15);
            border: 3px solid {{ $theme['primary_color'] ?? '#e11d48' }};
            border-radius: 16px;
            padding: 30px;
            margin: 20px 0;
            display: inline-block;
        }

        .code-label {
            font-size: 14px;
            font-weight: 600;
            color: {{ $theme['secondary_color'] ?? '#64748b' }};
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .code {
            font-size: 42px;
            font-weight: 900;
            color: {{ $theme['primary_color'] ?? '#e11d48' }};
            letter-spacing: 8px;
            font-family: 'Courier New', 'Monaco', monospace;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .expiry-info {
            font-size: 14px;
            color: {{ $theme['secondary_color'] ?? '#64748b' }};
            margin-top: 12px;
        }

        .instructions {
            background-color: #f8fafc;
            border-left: 4px solid {{ $theme['primary_color'] ?? '#e11d48' }};
            padding: 20px 24px;
            margin: 32px 0;
            border-radius: 0 8px 8px 0;
        }

        .instructions h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
            color: {{ $theme['text_color'] ?? '#1f2937' }};
        }

        .instructions ol {
            margin: 0;
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 8px;
            font-size: 14px;
            color: #6b7280;
        }

        .warning {
            background-color: #fef3cd;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }

        .warning h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #92400e;
            display: flex;
            align-items: center;
        }

        .warning-icon {
            margin-right: 8px;
        }

        .warning ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .warning li {
            margin-bottom: 4px;
            font-size: 14px;
            color: #92400e;
        }

        .gym-info {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin: 32px 0;
        }

        .gym-info h3 {
            margin: 0 0 16px 0;
            font-size: 18px;
            color: {{ $theme['text_color'] ?? '#1f2937' }};
        }

        .contact-grid {
            display: table;
            width: 100%;
        }

        .contact-item {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }

        .contact-item h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            color: {{ $theme['secondary_color'] ?? '#64748b' }};
        }

        .contact-item p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }

        .footer {
            background-color: #f8fafc;
            padding: 32px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            margin: 0;
            font-size: 12px;
            color: #9ca3af;
        }

        .footer-links {
            margin: 16px 0 0 0;
        }

        .footer-links a {
            color: {{ $theme['primary_color'] ?? '#e11d48' }};
            text-decoration: none;
            margin: 0 12px;
            font-size: 12px;
        }

        /* Mobile Responsive */
        @media only screen and (max-width: 600px) {
            .header {
                padding: 30px 20px;
            }

            .content {
                padding: 30px 20px;
            }

            .code {
                font-size: 32px;
                letter-spacing: 4px;
            }

            .contact-item {
                display: block;
                width: 100%;
                margin-bottom: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            @if($gym->pwa_logo_url || $gym->logo_path)
                <img src="{{ $gym->pwa_logo_url ?: asset('storage/' . $gym->logo_path) }}"
                     alt="{{ $gym->name }}"
                     class="logo">
            @endif
            <h1>{{ $gym->name }}</h1>
            <p>Ihr Anmeldecode ist bereit</p>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="greeting">
                <strong>Hallo {{ $member->first_name }}!</strong>
            </div>

            <p>Sie haben einen Anmeldecode für den Zugang zum {{ $gym->name }} Mitglieder-Portal angefordert.</p>

            <!-- Code Section -->
            <div class="code-section">
                <div class="code-box">
                    <div class="code-label">Ihr Anmeldecode</div>
                    <div class="code">{{ $code }}</div>
                    <div class="expiry-info">
                        ⏰ Gültig für {{ $expiryTime }}
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h3>📱 So melden Sie sich an:</h3>
                <ol>
                    <li>Öffnen Sie die Mitglieder-App oder Website</li>
                    <li>Geben Sie diesen 6-stelligen Code ein</li>
                    <li>Klicken Sie auf "Anmelden"</li>
                </ol>
            </div>

            <!-- Security Warning -->
            <div class="warning">
                <h4>
                    <span class="warning-icon">🔒</span>
                    Sicherheitshinweise
                </h4>
                <ul>
                    <li>Dieser Code ist nur <strong>{{ $expiryMinutes }} Minuten</strong> gültig</li>
                    <li>Der Code kann nur <strong>einmal</strong> verwendet werden</li>
                    <li>Teilen Sie den Code <strong>niemals</strong> mit anderen</li>
                    <li>Falls Sie diesen Code nicht angefordert haben, ignorieren Sie diese E-Mail</li>
                </ul>
            </div>

            @if($gym->member_app_description)
            <p style="font-style: italic; color: #6b7280; margin: 24px 0;">
                {{ $gym->member_app_description }}
            </p>
            @endif

            <!-- Gym Info -->
            <div class="gym-info">
                <h3>📍 {{ $gym->name }}</h3>
                <div class="contact-grid">
                    <div class="contact-item">
                        <h4>Kontakt</h4>
                        @if($gym->phone)
                        <p>📞 {{ $gym->phone }}</p>
                        @endif
                        <p>✉️ {{ $gym->email }}</p>
                    </div>
                    <div class="contact-item">
                        <h4>Adresse</h4>
                        <p>{{ $gym->address }}</p>
                        <p>{{ $gym->postal_code }} {{ $gym->city }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ $gym->name }}</strong></p>
            @if($gym->website)
            <div class="footer-links">
                <a href="{{ $gym->website }}">Website</a>
                @if($gym->social_media && is_array($gym->social_media))
                    @foreach($gym->social_media as $platform => $url)
                        <a href="{{ $url }}">{{ ucfirst($platform) }}</a>
                    @endforeach
                @endif
            </div>
            @endif
            <p style="margin-top: 16px;">
                © {{ date('Y') }} {{ $gym->name }}. Alle Rechte vorbehalten.
            </p>
        </div>
    </div>
</body>
</html>
