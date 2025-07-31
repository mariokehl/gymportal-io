<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $result['success'] ? 'Zahlung erfolgreich' : 'Zahlung fehlgeschlagen' }} - {{ $gymData['name'] ?? 'Fitness Studio' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: {{ $gymData['widget_settings']['colors']['background'] ?? '#f8fafc' }};
            font-family: {{ $gymData['widget_settings']['fonts']['family'] ?? 'Inter, sans-serif' }};
        }
        .primary-color {
            background-color: {{ $gymData['widget_settings']['colors']['primary'] ?? '#3b82f6' }};
        }
        .success-color {
            background-color: #10b981;
        }
        .error-color {
            background-color: #ef4444;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-6">

        @if($result['success'] && $result['status'] === 'paid')
            {{-- Erfolgreich bezahlt --}}
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 success-color rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Zahlung erfolgreich!</h1>
                <p class="text-gray-600 mb-6">{{ $result['message'] }}</p>

                @if(isset($result['member']))
                    <div class="bg-green-50 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-green-900 mb-2">Ihre Mitgliedschaft</h3>
                        <div class="text-sm text-green-700">
                            <p>Mitgliedsnummer: <span class="font-mono">{{ $result['member']['member_number'] }}</span></p>
                            <p>Status: <span class="font-semibold">{{ ucfirst($result['member']['status']) }}</span></p>
                        </div>
                    </div>
                @endif

                <div class="space-y-3">
                    <p class="text-sm text-gray-600">{{ $result['next_steps']['description'] ?? 'Sie erhalten eine Bestätigungs-E-Mail.' }}</p>

                    <button
                        onclick="window.close()"
                        class="w-full success-color text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-opacity">
                        Fenster schließen
                    </button>

                    @if($gymData)
                        <a
                            href="{{ $gymData['website'] ?? '#' }}"
                            target="_parent"
                            class="block w-full text-center border border-gray-300 text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                            Zur Website
                        </a>
                    @endif
                </div>
            </div>

        @elseif($result['success'] && $result['status'] === 'pending')
            {{-- Payment noch in Bearbeitung --}}
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-yellow-500 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Zahlung wird verarbeitet</h1>
                <p class="text-gray-600 mb-6">{{ $result['message'] }}</p>

                <div class="space-y-3">
                    @if($result['check_again'] ?? false)
                        <button
                            onclick="checkPaymentStatus()"
                            id="check-button"
                            class="w-full primary-color text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-opacity">
                            Status prüfen
                        </button>
                    @endif

                    <button
                        onclick="window.close()"
                        class="w-full border border-gray-300 text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        Fenster schließen
                    </button>
                </div>
            </div>

        @else
            {{-- Payment fehlgeschlagen oder abgebrochen --}}
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 error-color rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">
                    @if($result['status'] === 'canceled')
                        Zahlung abgebrochen
                    @elseif($result['status'] === 'expired')
                        Zahlung abgelaufen
                    @else
                        Zahlung fehlgeschlagen
                    @endif
                </h1>

                <p class="text-gray-600 mb-6">{{ $result['message'] }}</p>

                <div class="space-y-3">
                    @if($result['retry_possible'] ?? false)
                        <button
                            onclick="window.history.back()"
                            class="w-full primary-color text-white py-3 px-4 rounded-lg font-semibold hover:opacity-90 transition-opacity">
                            Erneut versuchen
                        </button>
                    @endif

                    <button
                        onclick="window.close()"
                        class="w-full border border-gray-300 text-gray-700 py-3 px-4 rounded-lg font-semibold hover:bg-gray-50 transition-colors">
                        Fenster schließen
                    </button>

                    @if($gymData)
                        <a
                            href="{{ $gymData['contact_url'] ?? 'mailto:' . ($gymData['email'] ?? '') }}"
                            target="_parent"
                            class="block w-full text-center text-sm text-blue-600 hover:text-blue-800 transition-colors">
                            Kontakt aufnehmen
                        </a>
                    @endif
                </div>
            </div>
        @endif

    </div>

    <script>
        // Auto-refresh für pending payments
        @if($result['success'] && $result['status'] === 'pending' && ($result['check_again'] ?? false))
            let checkInterval;

            function checkPaymentStatus() {
                const button = document.getElementById('check-button');
                button.disabled = true;
                button.textContent = 'Wird geprüft...';

                // Hier würde normalerweise ein AJAX-Call gemacht werden
                // Da wir aber schon auf der Result-Page sind, reload nach 3 Sekunden
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }

            // Auto-check alle 10 Sekunden
            checkInterval = setInterval(checkPaymentStatus, 10000);

            // Stop auto-check nach 5 Minuten
            setTimeout(() => {
                clearInterval(checkInterval);
            }, 300000);
        @endif

        // Postmessage an Parent-Window senden
        @if($result['success'] && $result['status'] === 'paid')
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'mollie_payment_success',
                    data: @json($result)
                }, '*');
            }
        @elseif(!$result['success'])
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'mollie_payment_failed',
                    data: @json($result)
                }, '*');
            }
        @endif
    </script>

</body>
</html>
