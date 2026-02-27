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
            @php
                $today = now();
                $startsFirstOfMonth = ($gymData['contracts_start_first_of_month'] ?? false) && $today->day !== 1;
                $freePeriodEnd = $startsFirstOfMonth ? $today->copy()->endOfMonth() : null;
                $paidStart = $startsFirstOfMonth ? $today->copy()->addMonth()->startOfMonth() : $today;
            @endphp

            @if($startsFirstOfMonth)
            <div class="free-period-notice" style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: flex-start; gap: 10px;">
                    <svg style="width: 20px; height: 20px; color: #10b981; flex-shrink: 0; margin-top: 2px;" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <strong style="color: #065f46;">{{ $gymData['free_trial_membership_name'] ?? 'Gratis-Testzeitraum' }}</strong>
                        <p style="color: #047857; margin: 4px 0 0 0; font-size: 14px;">
                            Vom {{ $today->format('d.m.Y') }} bis {{ $freePeriodEnd->format('d.m.Y') }} trainierst du kostenlos!
                            Dein zahlungspflichtiger Vertrag beginnt am {{ $paidStart->format('d.m.Y') }}.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <div class="detail-row">
                <span class="label">Vertragsbeginn:</span>
                <span class="value">{{ $paidStart->format('d.m.Y') }}</span>
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
                <span class="label">Kündigungsfrist:</span>
                <span class="value">
                    @php
                        $cancellationPeriod = $planData['cancellation_period'] ?? 30;
                        $cancellationUnit = $planData['cancellation_period_unit'] ?? 'days';
                        if ($cancellationUnit === 'months') {
                            echo $cancellationPeriod . ' ' . ($cancellationPeriod == 1 ? 'Monat' : 'Monate');
                        } else {
                            echo $cancellationPeriod . ' ' . ($cancellationPeriod == 1 ? 'Tag' : 'Tage');
                        }
                    @endphp
                </span>
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
                    <p>Es gelten unsere
                        @if(!empty($gymData['legal_urls']['terms_and_conditions']))
                            <a href="{{ $gymData['legal_urls']['terms_and_conditions'] }}" target="_blank" rel="noopener">Allgemeinen Geschäftsbedingungen</a>.
                        @else
                            Allgemeinen Geschäftsbedingungen.
                        @endif
                        Bitte beachte auch die
                        @if(!empty($gymData['legal_urls']['cancellation_policy']))
                            <a href="{{ $gymData['legal_urls']['cancellation_policy'] }}" target="_blank" rel="noopener">Widerrufsbelehrung</a>
                        @else
                            Widerrufsbelehrung
                        @endif
                        sowie unsere
                        @if(!empty($gymData['legal_urls']['privacy_policy']))
                            <a href="{{ $gymData['legal_urls']['privacy_policy'] }}" target="_blank" rel="noopener">Datenschutzerklärung</a>.
                        @else
                            Datenschutzerklärung.
                        @endif
                        Alle angegebenen Preise inkl. Umsatzsteuer.</p>

                    {{-- <p><strong>Mach jetzt den ersten Schritt, dein Ziel Muskelaufbau zu erreichen.</strong></p> --}}
                </div>
            </div>
        </div>
    </div>

    <div class="purchase-section">
        <button class="purchase-btn">Zahlungspflichtig bestellen</button>
    </div>
</div>
