@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 1])

    @php
        $billingCycles = [
            'monthly' => 'monatlich',
            'yearly' => 'jährlich',
            'quarterly' => 'quartalsweise'
        ];
        $texts = $gymData['widget_settings']['texts'] ?? [];
        $widgetTitle = $texts['title'] ?? 'Wähle deinen Vertrag';
    @endphp

    <h2 class="widget-title">{{ $widgetTitle }}</h2>

    {{-- Laufzeit-Auswahl --}}
    @if($gymData['widget_settings']['features']['show_duration_selector'] ?? false)
    <div class="duration-selector">
        <button class="duration-btn active" data-duration="12">12 Monate</button>
        <button class="duration-btn" data-duration="1">Monatlich</button>
    </div>
    @endif

    <div class="plans-grid">
        @foreach($plans as $plan)
        <label class="plan-card"
               data-plan="{{ $plan->id }}"
               data-duration="{{ $plan->commitment_months }}"
               style="display: {{ $plan->commitment_months == 12 ? 'block' : 'none' }}">
            <input type="radio" name="plan" value="{{ $plan->id }}" style="display: none;">

            <div class="plan-header">
                <h3 class="plan-name">{{ $plan->name }}</h3>
                <p class="plan-description">{{ $plan->description }}</p>

                {{-- Laufzeit-Anzeige --}}
                <div class="plan-duration">
                    <span class="duration-badge">
                        @if($plan->commitment_months == 1)
                            Monatlich kündbar
                        @else
                            {{ $plan->commitment_months }} Monate Laufzeit
                        @endif
                    </span>
                </div>
            </div>

            <div class="plan-features">
                <div class="feature-list">
                    {{--
                    <div class="feature">✔️ EGYM</div>
                    <div class="feature">✔️ Kostenlos Parken</div>
                    <div class="feature">✔️ Kostenlos Duschen</div>
                    <div class="feature">✔️ Freies WLAN</div>
                    --}}
                    @if($plan->setup_fee > 0)
                        <div class="feature-addon">Aktivierungsgebühr <span class="addon-price">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($plan->setup_fee, 'EUR') }}</span></div>
                    @endif
                </div>
            </div>

            <div class="plan-pricing">
                <div class="price-section">
                    {{-- <span class="price-label">Trainiere ab</span> --}}
                    <div class="price-main">
                        <span class="price-amount">{{ (new NumberFormatter('de_DE', NumberFormatter::CURRENCY))->formatCurrency($plan->price, 'EUR') }}</span>
                    </div>
                    <div class="price-details">
                        <span class="price-frequency">{{ $billingCycles[$plan->billing_cycle] ?? $plan->billing_cycle }}</span>
                        {{-- <span class="price-after">danach {{ number_format($plan->price, 2) }} € wöchentlich</span> --}}
                    </div>
                </div>
            </div>
        </label>
        @endforeach
    </div>

    {{-- Hinweis wenn keine Pläne für gewählte Laufzeit verfügbar --}}
    <div class="no-plans-message" style="display: none; text-align: center; padding: 20px; color: #6b7280;">
        <p>Für die gewählte Laufzeit sind keine Tarife verfügbar. Bitte wähle eine andere Laufzeit.</p>
    </div>

    <button id="next-button" class="next-btn" disabled>Weiter</button>
</div>
