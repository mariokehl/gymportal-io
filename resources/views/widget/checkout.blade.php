@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 3])

    @php
        $billingCycles = [
            'monthly' => 'monatlich',
            'yearly' => 'jährlich',
            'quarterly' => 'quartalsweise'
        ];
        $countryCodes = [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CT' => 'Schweiz'
        ];
    @endphp

    <div class="checkout-content">
        <div class="membership-details">
            <h2>Vertrag im Überblick</h2>

            <div class="detail-row">
                <span class="label">Standort:</span>
                <span class="value">{{ $gymData['name'] ?? 'Unbekannt' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Vertrag:</span>
                <span class="value">{{ $planData['name'] ?? 'Unbekannt' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Vertragsbeginn:</span>
                <span class="value">{{ date('d.m.Y') }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Mindestvertragslaufzeit:</span>
                <span class="value">{{ $planData['commitment_months'] ?? 12 }} Monate</span>
            </div>
            <div class="detail-row">
                <span class="label">Mitgliedsbeitrag:</span>
                <span class="value">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($planData['price'], 'EUR') }} {{ $billingCycles[$planData['billing_cycle']] ?? $planData['billing_cycle'] }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Aktivierungsgebühr:</span>
                <span class="value">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($planData['setup_fee'], 'EUR') }} einmalig</span>
            </div>
            <div class="detail-row">
                <span class="label">Verlängerung:</span>
                <span class="value">{{ $planData['commitment_months'] ?? 12 }} Monate</span>
            </div>
            <div class="detail-row">
                <span class="label">Kündigungsfrist:</span>
                <span class="value">{{ $planData['cancellation_period_days'] ?? 30 }} Tage</span>
            </div>
            {{--
            <div class="detail-row">
                <span class="label">Gesamtpreis Mindestvertragslaufzeit:</span>
                <span class="value">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($planData['membership_price']['total_price'], 'EUR') }}</span>
            </div>
            --}}
        </div>

        <div class="member-summary">
            <h2>Deine Daten</h2>

            <div class="summary-section">
                <div class="detail-row">
                    <span class="label">Vorname:</span>
                    <span class="value">{{ $formData['first_name'] ?? 'Max' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Nachname:</span>
                    <span class="value">{{ $formData['last_name'] ?? 'Muster' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">E-Mail-Adresse:</span>
                    <span class="value">{{ $formData['email'] ?? 'max@example.com' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Straße und Hausnummer:</span>
                    <span class="value">{{ $formData['address'] ?? 'Musterstraße 1a' }}</span>
                </div>
                @if(!empty($formData['address_addition']))
                <div class="detail-row">
                    <span class="label">Adresszusatz:</span>
                    <span class="value">{{ $formData['address_addition'] }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="label">Postleitzahl:</span>
                    <span class="value">{{ $formData['postal_code'] ?? '22761' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Stadt:</span>
                    <span class="value">{{ $formData['city'] ?? 'Hamburg' }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Land:</span>
                    <span class="value">{{ $billingCycles[$formData['country']] ?? $formData['country'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Mobilnummer:</span>
                    <span class="value">{{ $formData['phone'] ?? '01634344342423' }}</span>
                </div>
            </div>
        </div>

        <div class="pricing-summary">
            <h2>Dein Mitgliedsbeitrag</h2>

            <div class="pricing-details">
                {{-- <p>Summe aus Mitgliedsbeitrag, Ermäßigungen, Zusatzleistungen</p> --}}

                {{--
                <div class="pricing-breakdown">
                    <div class="price-row">
                        <span class="label">die ersten 4 Wochen:</span>
                        <span class="value">0 € wöchentlich</span>
                    </div>
                    <div class="price-row">
                        <span class="label">danach bis Ende der Erstlaufzeit von 12 Monate:</span>
                        <span class="value">{{ number_format($planData['price'] ?? 13.90, 2) }} € wöchentlich</span>
                    </div>
                </div>
                --}}

                <div class="final-price">
                    {{-- <span class="label">Trainiere ab</span> --}}
                    <div class="price-amount">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($planData['price'], 'EUR') }}</div>
                    <span class="price-frequency">{{ $billingCycles[$planData['billing_cycle']] ?? $planData['billing_cycle'] }}</span>
                </div>

                <div class="registration-note">
                    <p>Es gelten unsere Allgemeinen Geschäftsbedingungen. Bitte beachte auch die Widerrufsbelehrung sowie unsere Datenschutzerklärung. Alle angegebenen Preise inkl. Umsatzsteuer.</p>

                    {{-- <p><strong>Mach jetzt den ersten Schritt, dein Ziel Muskelaufbau zu erreichen.</strong></p> --}}
                </div>
            </div>
        </div>
    </div>

    <div class="purchase-section">
        <button class="purchase-btn">Zahlungspflichtig bestellen</button>
    </div>
</div>
