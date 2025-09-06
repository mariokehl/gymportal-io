@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 2])

    <div class="form-header">
        <h2>Persönliche Angaben</h2>
    </div>

    <form id="member-form" class="member-form">
        {{-- Persönliche Daten --}}
        <div class="form-section">
            <div class="form-row">
                <div class="form-group">
                    <label for="salutation">Anrede<span class="mandatory">*</span></label>
                    <select name="salutation" id="salutation" required>
                        <option value="Herr">Herr</option>
                        <option value="Frau">Frau</option>
                        <option value="Divers">Divers</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="first_name">Vorname<span class="mandatory">*</span></label>
                    <input type="text" name="first_name" id="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Nachname<span class="mandatory">*</span></label>
                    <input type="text" name="last_name" id="last_name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">E-Mail-Adresse<span class="mandatory">*</span></label>
                    <input type="email" name="email" id="email" required>
                </div>
                <div class="form-group">
                    <label for="email_confirmation">E-Mail-Adresse wiederholen<span class="mandatory">*</span></label>
                    <input type="email" name="email_confirmation" id="email_confirmation" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group flex-grow-0">
                    <label for="birth_date">Geburtsdatum<span class="mandatory">*</span></label>
                    <input type="date" name="birth_date" id="birth_date" max="{{ date('Y-m-d', strtotime('-18 years')) }}" required>
                </div>
            </div>
            <small>Du musst mindestens 18 Jahre alt sein, um online einen Vertrag abschließen zu können.</small>
        </div>

        {{-- Adresse --}}
        <div class="form-section">
            <h3>Adresse</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="address">Straße und Hausnummer<span class="mandatory">*</span></label>
                    <input type="text" name="address" id="address" required>
                </div>
                <div class="form-group">
                    <label for="address_addition">Adresszusatz</label>
                    <input type="text" name="address_addition" id="address_addition">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="postal_code">Postleitzahl<span class="mandatory">*</span></label>
                    <input type="text" name="postal_code" id="postal_code" required>
                </div>
                <div class="form-group">
                    <label for="city">Stadt<span class="mandatory">*</span></label>
                    <input type="text" name="city" id="city" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="country">Land<span class="mandatory">*</span></label>
                    <select name="country" id="country" required>
                        <option value="DE">Deutschland</option>
                        <option value="AT">Österreich</option>
                        <option value="CH">Schweiz</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Kontakt --}}
        <div class="form-section">
            <h3>Kontakt</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Mobilfunknummer<span class="mandatory">*</span></label>
                    <input type="tel" name="phone" id="phone" required>
                </div>
            </div>
        </div>

        {{-- Zahlungsart --}}
        @if(count($gymData['payment_methods']) > 0)
        <div class="form-section">
            <h3>Zahlungsart auswählen<span class="mandatory">*</span></h3>

            {{-- Hinweis-Container (wird per JavaScript gesteuert) --}}
            <div id="payment-method-info" class="payment-info" style="display: none;">
                <p class="info-text"></p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    @foreach($gymData['payment_methods'] as $method)
                        <label class="radio-label" data-payment-method="{{ $method['key'] }}" data-payment-type="{{ $method['type'] ?? 'standard' }}">
                            <input type="radio"
                                name="payment_method"
                                value="{{ $method['key'] }}"
                                data-method-type="{{ $method['type'] ?? 'standard' }}"
                                data-requires-mandate="{{ ($method['requires_mandate'] ?? false) ? 'true' : 'false' }}"
                                data-requires-iban="{{ in_array($method['key'], ['sepa_direct_debit', 'mollie_directdebit']) ? 'true' : 'false' }}"
                                {{ old('payment_method') == $method['key'] ? 'checked' : '' }}
                                required>
                            <div class="payment-method-content">
                                <div class="payment-method-name">{{ $method['name'] }}</div>
                                @if(!empty($method['description']))
                                    <div class="payment-method-description">{{ $method['description'] }}</div>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- IBAN-Eingabe und SEPA-Lastschrift (wird per JavaScript gesteuert) --}}
            <div id="iban-details-section" class="iban-details-section">
                <h4 class="iban-section-title">Bankverbindung</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label for="account_holder">Kontoinhaber<span class="mandatory">*</span></label>
                        <input type="text" name="account_holder" id="account_holder">
                        <small class="field-hint">Name des Kontoinhabers (kann vom Mitgliedsnamen abweichen)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="iban">IBAN<span class="mandatory">*</span></label>
                        <input type="text"
                               name="iban"
                               id="iban"
                               placeholder="DE89 3704 0044 0532 0130 00"
                               maxlength="34">
                        <small class="field-hint">Internationale Bankkontonummer</small>
                    </div>
                </div>

                <div class="sepa-mandate-agreement">
                    <div class="sepa-mandate-info">
                        <h4>SEPA-Lastschriftmandat</h4>
                        <p>Mit der Erteilung des SEPA-Lastschriftmandats ermächtigen Sie uns, fällige Beiträge von Ihrem angegebenen Konto mittels Lastschrift einzuziehen. Gleichzeitig weisen Sie Ihr Kreditinstitut an, die von uns auf Ihr Konto gezogenen Lastschriften einzulösen.</p>
                        <p class="highlight">Sie haben das Recht, innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages zu verlangen.</p>
                    </div>

                    <div class="sepa-acknowledgment">
                        <input type="checkbox"
                               name="sepa_mandate_acknowledged"
                               id="sepa_mandate_acknowledged"
                               value="1">
                        <label for="sepa_mandate_acknowledged">
                            Ich erteile hiermit das SEPA-Lastschriftmandat und stimme dem Einzug der Mitgliedschaftsbeiträge per Lastschrift zu.<span class="mandatory">*</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        @else
        {{-- Fallback wenn keine Zahlungsmethoden konfiguriert sind --}}
        <div class="form-section">
            <div class="alert alert-warning">
                <h4>Zahlungsmethoden werden konfiguriert</h4>
                <p>Die verfügbaren Zahlungsmethoden werden derzeit eingerichtet. Bitte versuchen Sie es in Kürze erneut oder kontaktieren Sie uns direkt.</p>
            </div>
        </div>
        @endif

        {{-- Gutschein --}}
        {{--
        <div class="form-section">
            <h3>Gutschein-Code</h3>
            <div class="form-group">
                <label for="voucher_code">Gib hier deinen Gutschein-Code ein</label>
                <input type="text" name="voucher_code" id="voucher_code">
            </div>
        </div>
        --}}

        {{-- Fitness-Ziele (optional) --}}
        @if($gymData['widget_settings']['features']['show_goals_selection'] ?? false)
        <div class="form-section">
            <h3>Welches sportliche Ziel willst du mit {{ $gymData['name'] }} erreichen?</h3>
            <div class="goals-buttons">
                <button type="button" class="goal-btn" data-goal="abnehmen">Abnehmen</button>
                <button type="button" class="goal-btn" data-goal="muskelaufbau">Muskelaufbau</button>
                <button type="button" class="goal-btn" data-goal="ausdauer">Ausdauer verbessern</button>
                <button type="button" class="goal-btn" data-goal="gesundheit">Gesundheit erhalten oder wiederherstellen</button>
                <button type="button" class="goal-btn" data-goal="reha">Rehabilitation & Wiederaufbau nach Verletzungen</button>
                <button type="button" class="goal-btn" data-goal="beweglichkeit">Beweglichkeit & Mobilität verbessern</button>
                <button type="button" class="goal-btn" data-goal="disziplin">Regelmäßigkeit & Disziplin aufbauen</button>
            </div>
        </div>
        @endif

        {{-- reCAPTCHA (optional) --}}
        @if($gymData['widget_settings']['integrations']['google_recaptcha'] ?? false)
        <div class="form-group">
            <div class="recaptcha-placeholder">
                <input type="checkbox" name="recaptcha" required>
                <span>Ich bin kein Roboter</span>
            </div>
        </div>
        @endif

        <button id="next-button" class="next-btn">Weiter</button>
    </form>
</div>
