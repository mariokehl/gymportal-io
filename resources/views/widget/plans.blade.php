@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 1])

    @php
        $billingCycles = [
            'monthly' => 'monatlich',
            'yearly' => 'jährlich',
            'quarterly' => 'quartalsweise',
            'biannual' => 'halbjährlich'
        ];
        $texts = $gymData['widget_settings']['texts'] ?? [];
        $widgetTitle = $texts['title'] ?? 'Wähle deinen Vertrag';

        // Entry price and phase ladder come from the shared service so the plan
        // card, the checkout and the billing side stay in agreement.
        $discountService = new \App\Services\MembershipPlanDiscountService;
        $priceFormatter = new NumberFormatter('de_DE', NumberFormatter::CURRENCY);
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
               data-duration="{{ $plan->commitment_months }}">
            <input type="radio" name="plan" value="{{ $plan->id }}" style="display: none;">

            {{--
                The card is a subgrid: every row below is a direct child so all
                cards share one set of rows. Name, description, badge, fee,
                add-ons, discount and price therefore line up across the grid
                regardless of how much text each plan carries.
            --}}
            <h3 class="plan-name">{{ $plan->name }}</h3>
            <p class="plan-description">{{ $plan->description }}</p>

            {{-- Laufzeit-Anzeige --}}
            <div class="plan-duration">
                @if($plan->commitment_months > 0)
                    <span class="duration-badge">
                        @if($plan->commitment_months == 1)
                            Monatlich kündbar
                        @else
                            {{ $plan->commitment_months }} Monate Laufzeit
                        @endif
                    </span>
                @endif
            </div>

            <div class="plan-features">
                @if($plan->setup_fee > 0)
                    <div class="feature-addon">Aktivierungsgebühr <span class="addon-price">{{ $priceFormatter->formatCurrency($plan->setup_fee, 'EUR') }}</span></div>
                @endif
            </div>

            {{-- Plan add-ons (billed once at the start of the contract term) --}}
            @php
                $includedAddons = $plan->addons->where('pivot.mode', 'included');
                $optionalAddons = $plan->addons->where('pivot.mode', 'optional');
                $planAddons = $includedAddons->merge($optionalAddons);
            @endphp
            {{-- Always rendered, so a plan without add-ons keeps the same rows. --}}
            <div class="plan-addons" data-addons-for="{{ $plan->id }}">
                @if($planAddons->isNotEmpty())
                <p class="plan-addons-title">Add-Ons</p>

                {{-- Included add-ons: part of the plan, shown as a free benefit (green). --}}
                @foreach($includedAddons as $addon)
                <label class="addon-item addon-included">
                    <div class="addon-row">
                        <input type="checkbox" class="addon-checkbox" name="addon" value="{{ $addon->id }}"
                               data-plan="{{ $plan->id }}" checked disabled hidden>
                        <div class="addon-text">
                            <div class="addon-name-line">
                                <span class="addon-name">{{ $addon->name }}</span>
                            </div>
                            @if($addon->description)
                                <p class="addon-description">{{ $addon->description }}</p>
                            @endif
                            <div class="addon-price-line">
                                {{-- Included add-ons are free: the regular price stays visible as the reference. --}}
                                <span class="addon-price-gift">{{ $priceFormatter->formatCurrency(0, 'EUR') }}</span>
                                <span class="addon-price-struck">statt {{ $priceFormatter->formatCurrency($addon->price, 'EUR') }}</span>
                                <span class="addon-price-sub">Inklusive</span>
                            </div>
                        </div>
                        <span class="addon-checkbox-box" aria-hidden="true">✓</span>
                        @if($addon->description)
                            <button type="button" class="addon-info-btn" aria-label="Beschreibung anzeigen" title="Beschreibung anzeigen">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M13 5 5 13"></path><path d="M13 9 9 13"></path><path d="M13 1 1 13"></path></svg>
                            </button>
                        @endif
                    </div>
                </label>
                @endforeach

                {{-- Optional add-ons: selectable surcharge. --}}
                @foreach($optionalAddons as $addon)
                <label class="addon-item addon-optional">
                    <div class="addon-row">
                        <input type="checkbox" class="addon-checkbox" name="addon" value="{{ $addon->id }}"
                               data-plan="{{ $plan->id }}" hidden>
                        <div class="addon-text">
                            <div class="addon-name-line">
                                <span class="addon-name">{{ $addon->name }}</span>
                            </div>
                            @if($addon->description)
                                <p class="addon-description">{{ $addon->description }}</p>
                            @endif
                            <div class="addon-price-line">
                                @if($addon->showsWeeklyPrice())
                                    {{--
                                        Weekly display: the computed weekly figure leads as a
                                        comparison, the monthly amount that is actually billed
                                        stays visible right below it.
                                    --}}
                                    <span class="addon-price">{{ $priceFormatter->formatCurrency($addon->weekly_price, 'EUR') }}</span>
                                    <span class="addon-price-note">wöchentlich</span>
                                    <span class="addon-price-sub">{{ $priceFormatter->formatCurrency($addon->price, 'EUR') }}/Monat · monatl. kündbar</span>
                                @else
                                    <span class="addon-price">{{ $priceFormatter->formatCurrency($addon->price, 'EUR') }}</span>
                                    <span class="addon-price-note">{{ $addon->isRecurring() ? 'monatlich' : 'einmalig' }}</span>
                                    <span class="addon-price-sub">{{ $addon->isRecurring() ? 'Monatlich kündbar' : 'Einmalig bei Vertragsabschluss' }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="addon-checkbox-box" aria-hidden="true">✓</span>
                        @if($addon->description)
                            <button type="button" class="addon-info-btn" aria-label="Beschreibung anzeigen" title="Beschreibung anzeigen">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M13 5 5 13"></path><path d="M13 9 9 13"></path><path d="M13 1 1 13"></path></svg>
                            </button>
                        @endif
                    </div>
                </label>
                @endforeach
                @endif
            </div>

            @php
                $entryPrice = $discountService->entryPriceFor($plan);
                $segments = $discountService->segmentsFor($plan);
            @endphp

            {{-- Own row, so the price stays aligned whether or not a plan is discounted. --}}
            <div class="price-discount">
                @if($entryPrice['has_discount'])
                    <span class="price-original">{{ $priceFormatter->formatCurrency($entryPrice['original_price'], 'EUR') }}</span>
                    <span class="price-discount-badge">&minus;{{ $entryPrice['discount_percent'] }}%</span>
                @endif
            </div>

            <div class="price-main">
                <span class="price-amount">{{ $priceFormatter->formatCurrency($entryPrice['price'], 'EUR') }}</span>
            </div>

            <div class="price-details">
                <span class="price-frequency">{{ $billingCycles[$plan->billing_cycle] ?? $plan->billing_cycle }}</span>
                {{-- Every follow-up phase, so the customer sees what happens after the promo. --}}
                @foreach(array_slice($segments, 1) as $segment)
                    <span class="price-after">ab dem {{ $segment['from'] }}. Monat: {{ $priceFormatter->formatCurrency($segment['price'], 'EUR') }}</span>
                @endforeach
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
