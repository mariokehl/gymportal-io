<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eingangsbestätigung Ihres Widerrufs</title>
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
        h1, h2, h3 {
            color: #495057;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 40%;
            color: #6c757d;
        }
        .refund-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .refund-box h3 {
            color: #155724;
            margin-top: 0;
        }
        .notice-box {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
        }
        hr {
            border: none;
            border-top: 1px solid #e9ecef;
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
            <h1>{{ $gym->name }}</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <h2>Eingangsbestätigung Ihres Widerrufs</h2>

            <p>Sehr geehrte/r {{ $member->first_name }} {{ $member->last_name }},</p>

            <p>hiermit bestätigen wir den <strong>Eingang</strong> Ihres Widerrufs.</p>

            <h3>Angaben zum Widerruf</h3>

            <table class="info-table">
                <tr>
                    <td>Vertragsnummer</td>
                    <td>#{{ $membership->id }}</td>
                </tr>
                <tr>
                    <td>Tarif</td>
                    <td>{{ $membership->membershipPlan->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Eingangsdatum</td>
                    <td>{{ $withdrawalDate }}</td>
                </tr>
                <tr>
                    <td>Eingangszeit</td>
                    <td>{{ $withdrawalTime }} Uhr</td>
                </tr>
            </table>

            @if($refundAmount > 0)
                <div class="refund-box">
                    <h3>Erstattung</h3>
                    <p>Der Betrag von <strong>{{ number_format($refundAmount, 2, ',', '.') }} &euro;</strong> wird Ihnen innerhalb von 14 Tagen auf das ursprüngliche Zahlungsmittel erstattet.</p>
                </div>
            @endif

            <hr>

            <div class="notice-box">
                <strong>Hinweis:</strong> Diese Bestätigung dokumentiert ausschließlich den Eingang Ihrer Widerrufserklärung über unsere elektronische Widerrufsfunktion gemäß &sect; 356a BGB.
            </div>

            <p>Mit freundlichen Grüßen,</p>
            <p><strong>{{ $gym->name }}</strong></p>
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
        </div>
    </div>
</body>
</html>
