@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 3])

    @php
        $billingCycles = [
            'monthly' => 'monatlich',
            'yearly' => 'jährlich',
            'quarterly' => 'quartalsweise',
            'biannual' => 'halbjährlich'
        ];
        $countryCodes = [
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz'
        ];
        $priceFormatter = new NumberFormatter('de_DE', NumberFormatter::CURRENCY);

        // The discount ladder is resolved server-side and frozen into the
        // session payload, so the summary shows exactly what will be charged.
        $discountSegments = $planData['discount_segments'] ?? [];
        $entryPrice = $planData['entry_price'] ?? null;
    @endphp

    <div class="checkout-content">
        <div class="membership-details">
            <div class="summary-heading">
                <h2>Vertrag im Überblick</h2>
                <a href="#" class="summary-edit-link" data-edit-step="plans">ändern</a>
            </div>

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

                // Fixed contract start takes precedence over the free period logic
                // as long as the configured date is still in the future.
                $forcedStart = null;
                if (($planData['start_date_mode'] ?? 'next_possible') === 'fixed' && !empty($planData['fixed_start_date'])) {
                    $fixedStart = \Carbon\Carbon::parse($planData['fixed_start_date'])->startOfDay();
                    if ($today->copy()->startOfDay()->lt($fixedStart)) {
                        $forcedStart = $fixedStart;
                    }
                }

                $startsFirstOfMonth = !$forcedStart
                    && ($gymData['contracts_start_first_of_month'] ?? false)
                    && $today->day !== 1;
                $freePeriodEnd = $startsFirstOfMonth ? $today->copy()->endOfMonth() : null;

                if ($forcedStart) {
                    $paidStart = $forcedStart;
                } elseif ($startsFirstOfMonth) {
                    $paidStart = $today->copy()->addMonth()->startOfMonth();
                } else {
                    $paidStart = $today;
                }
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
                <span class="label">Erstvertragslaufzeit:</span>
                <span class="value">{{ $planData['commitment_months'] ?? 12 }} Monate</span>
            </div>
            @php
                $commitmentMonths = $planData['commitment_months'] ?? 12;
                $autoRenewType = $planData['auto_renew_type'] ?? 'indefinite';

                // Notice period as configured on the plan, so the sentence never
                // promises a different one than the row further down states.
                $noticePeriod = $planData['cancellation_period'] ?? 30;
                $noticeUnit = $planData['cancellation_period_unit'] ?? 'days';
                $noticeText = $noticeUnit === 'months'
                    ? $noticePeriod.' '.($noticePeriod == 1 ? 'Monat' : 'Monate')
                    : $noticePeriod.' '.($noticePeriod == 1 ? 'Tag' : 'Tage');

                // Only a studio whose contracts always start on the first of a
                // month cancels to a month boundary; otherwise the period runs
                // from the individual contract date and naming one would be wrong.
                if ($gymData['contracts_start_first_of_month'] ?? false) {
                    $noticeText .= ' zum Monatsende';
                }
            @endphp
            <p class="renewal-note">
                Nach {{ $commitmentMonths }} {{ $commitmentMonths == 1 ? 'Monat' : 'Monaten' }}
                @if($autoRenewType === 'monthly')
                    läuft der Vertrag monatlich weiter.
                @else
                    geht der Vertrag in eine unbefristete Mitgliedschaft über.
                @endif
                Kündigungsfrist: {{ $noticeText }}.
            </p>
            @php $cycleText = $billingCycles[$planData['billing_cycle']] ?? $planData['billing_cycle']; @endphp
            @if(count($discountSegments) > 1)
                {{-- Discounted plan: every phase is listed with the months it covers. --}}
                @foreach($discountSegments as $segment)
                <div class="detail-row">
                    <span class="label">
                        @if($segment['to'] === null)
                            Mitgliedsbeitrag ab dem {{ $segment['from'] }}. Monat:
                        @else
                            Mitgliedsbeitrag Monat {{ $segment['from'] }}–{{ $segment['to'] }}:
                        @endif
                    </span>
                    <span class="value"><small class="value-cycle">{{ $cycleText }}</small> {{ $priceFormatter->formatCurrency($segment['price'], 'EUR') }}</span>
                </div>
                @endforeach
            @else
                <div class="detail-row">
                    <span class="label">Mitgliedsbeitrag:</span>
                    <span class="value"><small class="value-cycle">{{ $cycleText }}</small> {{ $priceFormatter->formatCurrency($planData['price'], 'EUR') }}</span>
                </div>
            @endif
            <div class="detail-row">
                <span class="label">Aktivierungsgebühr:</span>
                <span class="value"><small class="value-cycle">einmalig</small> {{ $priceFormatter->formatCurrency($planData['setup_fee'], 'EUR') }}</span>
            </div>
            @foreach($addons ?? [] as $addon)
            <div class="detail-row">
                <span class="label">{{ $addon['name'] }}:</span>
                @if($addon['mode'] === 'included')
                    <span class="value value-gift">
                        <span class="addon-gift-price">{{ $priceFormatter->formatCurrency(0, 'EUR') }}</span>
                        <span class="addon-gift-struck"><small class="value-cycle">{{ ($addon['is_recurring'] ?? false) ? 'monatlich' : 'einmalig' }}</small> {{ $priceFormatter->formatCurrency($addon['price'], 'EUR') }}</span>
                    </span>
                @else
                    <span class="value">+ <small class="value-cycle">{{ ($addon['is_recurring'] ?? false) ? 'monatlich' : 'einmalig' }}</small> {{ $priceFormatter->formatCurrency($addon['price'], 'EUR') }}</span>
                @endif
            </div>
            @endforeach
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
            @php
                // Total cost over the initial contract term: the plan total
                // (recurring contributions + activation fee) plus the cost of
                // every paid add-on the customer actually has to pay for.
                // Included add-ons are free and must not be added. Recurring
                // add-ons are charged every month, so they count once per month
                // of the initial term; one-time add-ons count once.
                //
                // A discounted plan carries its own contribution sum, which
                // already accounts for the phases; the activation fee is added
                // on top of it the same way the calculator does.
                $contractTotal = isset($planData['discounted_contract_total']) && count($discountSegments) > 1
                    ? $planData['discounted_contract_total'] + ($planData['setup_fee'] ?? 0)
                    : ($planData['membership_price']['total_price'] ?? 0);
                $addonsTotal = 0;
                foreach ($addons ?? [] as $addon) {
                    if (($addon['mode'] ?? null) === 'included') {
                        continue;
                    }

                    $addonsTotal += ($addon['is_recurring'] ?? false)
                        ? $addon['price'] * $commitmentMonths
                        : $addon['price'];
                }
                $totalOverTerm = $contractTotal + $addonsTotal;
            @endphp
            <div class="detail-row">
                <span class="label">Gesamtpreis Erstvertragslaufzeit:</span>
                <span class="value">{{ $priceFormatter->formatCurrency($totalOverTerm, 'EUR') }}</span>
            </div>
        </div>

        <div class="member-summary">
            <div class="summary-heading">
                <h2>Deine Daten</h2>
                <a href="#" class="summary-edit-link" data-edit-step="form">ändern</a>
            </div>

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
                    <span class="value">{{ $countryCodes[$formData['country'] ?? ''] ?? ($formData['country'] ?? '') }}</span>
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
                    {{-- Struck-through reference price plus badge, whenever the entry price is discounted. --}}
                    @if($entryPrice && $entryPrice['has_discount'])
                        <div class="price-discount">
                            <span class="price-original">{{ $priceFormatter->formatCurrency($entryPrice['original_price'], 'EUR') }}</span>
                            <span class="price-discount-badge">&minus;{{ $entryPrice['discount_percent'] }}%</span>
                        </div>
                    @endif
                    <div class="price-amount">{{ $priceFormatter->formatCurrency($entryPrice['price'] ?? $planData['price'], 'EUR') }}</div>
                    <span class="price-frequency">{{ $cycleText }}</span>
                    {{-- Every follow-up phase, so the customer sees what happens after the promo. --}}
                    @foreach(array_slice($discountSegments, 1) as $segment)
                        <span class="price-after">ab dem {{ $segment['from'] }}. Monat: {{ $priceFormatter->formatCurrency($segment['price'], 'EUR') }}</span>
                    @endforeach
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
