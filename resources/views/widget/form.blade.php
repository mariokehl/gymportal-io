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

            {{-- SEPA-Lastschriftmandat Informationsbereich --}}
            <div id="sepa-mandate-section" class="sepa-mandate-section">
                <div class="sepa-mandate-title">
                    📋 SEPA-Lastschriftmandat Information
                </div>

                <div class="sepa-mandate-info">
                    <h4>Was ist ein SEPA-Lastschriftmandat?</h4>
                    <p>
                        Mit einem SEPA-Lastschriftmandat erteilen Sie uns die Berechtigung,
                        fällige Beträge von Ihrem Bankkonto einzuziehen. Dies erfolgt automatisch
                        zu den vereinbarten Terminen.
                    </p>

                    <h4>Ihre Rechte:</h4>
                    <p>
                        • Sie können das Mandat jederzeit widerrufen<br>
                        • Lastschriften können innerhalb von 8 Wochen ohne Angabe von Gründen zurückgebucht werden<br>
                        • Sie werden vor jedem Einzug informiert (Vorabankündigung)
                    </p>

                    <p class="highlight">
                        <strong>Wichtig:</strong> Das offizielle SEPA-Lastschriftmandat wird Ihnen nach der
                        Registrierung zur Unterschrift vorgelegt. Die Online-Registrierung ist noch
                        nicht rechtskräftig - erst die handschriftliche Unterschrift macht das Mandat gültig.
                    </p>
                </div>

                <div class="sepa-acknowledgment">
                    <input type="checkbox"
                           id="sepa_mandate_acknowledged"
                           name="sepa_mandate_acknowledged"
                           value="1"
                           data-required-for="sepa_direct_debit">
                    <label for="sepa_mandate_acknowledged">
                        Ich habe die Informationen zum SEPA-Lastschriftmandat gelesen und verstanden.
                        Mir ist bewusst, dass das offizielle Mandat nach der Registrierung noch
                        schriftlich zu unterzeichnen ist.<span class="mandatory">*</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- JavaScript für dynamische Hinweise und SEPA-Mandate --}}
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
            const infoContainer = document.getElementById('payment-method-info');
            const infoText = infoContainer?.querySelector('.info-text');
            const sepaMandateSection = document.getElementById('sepa-mandate-section');
            const sepaMandateCheckbox = document.getElementById('sepa_mandate_acknowledged');

            if (!infoContainer || !infoText) return;

            function updatePaymentInfo(selectedMethod) {
                const selectedRadio = document.querySelector(`input[name="payment_method"][value="${selectedMethod}"]`);
                const methodType = selectedRadio?.getAttribute('data-method-type');
                const requiresMandate = selectedRadio?.getAttribute('data-requires-mandate') === 'true';

                // SEPA-Mandat-Bereich ein-/ausblenden
                if (requiresMandate) {
                    sepaMandateSection?.classList.add('show');
                    if (sepaMandateCheckbox) {
                        sepaMandateCheckbox.required = true;
                    }
                } else {
                    sepaMandateSection?.classList.remove('show');
                    if (sepaMandateCheckbox) {
                        sepaMandateCheckbox.required = false;
                        sepaMandateCheckbox.checked = false;
                    }
                }

                // Zahlungsart-Info anzeigen
                if (methodType === 'mollie') {
                    infoText.textContent = 'Nach der Registrierung werden Sie zur sicheren Zahlungsabwicklung weitergeleitet, um Ihre Zahlungsdaten einzugeben.';
                    infoContainer.style.display = 'block';
                    infoContainer.className = 'payment-info mollie-info';
                } else if (methodType === 'standard') {
                    const methodKey = selectedMethod;
                    let message = '';

                    switch(methodKey) {
                        case 'banktransfer':
                            message = 'Sie erhalten nach der Registrierung die Bankverbindung für die Überweisung.';
                            break;
                        case 'cash':
                            message = 'Die Zahlung erfolgt direkt vor Ort im Studio.';
                            break;
                        case 'invoice':
                            message = 'Sie erhalten eine Rechnung, die Sie per Überweisung begleichen können.';
                            break;
                        case 'standingorder':
                            message = 'Bitte richten Sie einen Dauerauftrag mit den Ihnen mitgeteilten Daten ein.';
                            break;
                        case 'sepa_direct_debit':
                            message = 'Das SEPA-Lastschriftmandat wird Ihnen nach der Registrierung zur Unterschrift vorgelegt.';
                            infoContainer.className = 'payment-info sepa-info';
                            break;
                        default:
                            message = 'Weitere Informationen zur Zahlungsabwicklung erhalten Sie nach der Registrierung.';
                    }

                    infoText.textContent = message;
                    infoContainer.style.display = 'block';
                    if (methodKey !== 'sepa_direct_debit') {
                        infoContainer.className = 'payment-info standard-info';
                    }
                } else {
                    infoContainer.style.display = 'none';
                }
            }

            // Event-Listener für Zahlungsmethoden-Auswahl
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        updatePaymentInfo(this.value);
                    }
                });

                // Bereits ausgewählte Methode beim Laden prüfen
                if (radio.checked) {
                    updatePaymentInfo(radio.value);
                }
            });

            // Form-Validation für SEPA-Mandat
            const form = document.getElementById('member-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
                    const requiresMandate = selectedPaymentMethod?.getAttribute('data-requires-mandate') === 'true';

                    if (requiresMandate && sepaMandateCheckbox && !sepaMandateCheckbox.checked) {
                        e.preventDefault();
                        alert('Bitte bestätigen Sie, dass Sie die SEPA-Lastschriftmandat-Informationen gelesen haben.');
                        sepaMandateCheckbox.focus();
                        return false;
                    }
                });
            }
        });
        </script>
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
