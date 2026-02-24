<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? 'Mitgliedschaftsvertrag' }}</title>
    <style>
        @page {
            margin: 2.5cm 2cm 2cm 2cm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
        }

        /* Header mit Logo */
        .contract-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .contract-header table {
            width: 100%;
        }
        .contract-header td {
            vertical-align: top;
        }
        .logo-cell {
            width: 120px;
        }
        .logo-cell img {
            max-width: 100px;
            max-height: 80px;
        }
        .gym-info {
            text-align: right;
            font-size: 9pt;
            color: #666;
        }
        .gym-name {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 4px;
        }

        /* Vertragstitel */
        .contract-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin: 20px 0 30px 0;
            color: #222;
        }

        /* Vertragsinhalt */
        .contract-body {
            margin-bottom: 40px;
        }
        .contract-body p {
            margin-bottom: 8px;
        }
        .contract-body h2 {
            font-size: 12pt;
            margin-top: 20px;
            margin-bottom: 8px;
        }
        .contract-body h3 {
            font-size: 11pt;
            margin-top: 15px;
            margin-bottom: 6px;
        }
        .contract-body table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .contract-body table td,
        .contract-body table th {
            padding: 6px 10px;
            border: 1px solid #ddd;
            font-size: 9pt;
        }
        .contract-body table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: left;
        }

        /* Unterschriftenbereich */
        .signature-area {
            margin-top: 60px;
            page-break-inside: avoid;
        }
        .signature-line {
            border-top: 1px solid #333;
            width: 250px;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 9pt;
            color: #666;
        }
        .signature-table {
            width: 100%;
        }
        .signature-table td {
            width: 50%;
            vertical-align: bottom;
        }

        /* Footer */
        .contract-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8pt;
            color: #999;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Header mit Logo und Gym-Daten -->
    <div class="contract-header">
        <table>
            <tr>
                <td class="logo-cell">
                    @if($logoDataUri)
                        <img src="{{ $logoDataUri }}" alt="{{ $gym->name }}">
                    @endif
                </td>
                <td class="gym-info">
                    <div class="gym-name">{{ $gym->name }}</div>
                    @if($gym->address)
                        {{ $gym->address }}<br>
                    @endif
                    @if($gym->postal_code || $gym->city)
                        {{ $gym->postal_code }} {{ $gym->city }}<br>
                    @endif
                    @if($gym->phone)
                        Tel: {{ $gym->phone }}<br>
                    @endif
                    @if($gym->email)
                        {{ $gym->email }}<br>
                    @endif
                    @if($gym->website)
                        {{ $gym->website }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Vertragstitel -->
    <div class="contract-title">
        {{ $title ?? 'Mitgliedschaftsvertrag' }}
    </div>

    <!-- Vertragsinhalt (vom Gym konfiguriert mit ersetzten Platzhaltern) -->
    <div class="contract-body">
        {!! $content !!}
    </div>

    <!-- Footer -->
    <div class="contract-footer">
        {{ $gym->name }} &middot; {{ $gym->address }}, {{ $gym->postal_code }} {{ $gym->city }}
        @if($gym->phone) &middot; Tel: {{ $gym->phone }} @endif
        @if($gym->email) &middot; {{ $gym->email }} @endif
    </div>
</body>
</html>
