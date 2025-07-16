@include('widget.partials.theme')

<div class="widget-container">
    @include('widget.partials.progress-bar', ['currentStep' => 2])

    <div class="form-header">
        <button class="back-btn"></button>
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
                <div class="form-group">
                    {{--
                    <label for="landline">Festnetznummer</label>
                    <input type="tel" name="landline" id="landline">
                    --}}
                </div>
            </div>
        </div>

        {{-- Kontoverbindung --}}
        <div class="form-section">
            <h3>Kontoverbindung</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="account_holder">Kontoinhaber<span class="mandatory">*</span></label>
                    <input type="text" name="account_holder" id="account_holder" required>
                </div>
                <div class="form-group">
                    <label for="iban">IBAN<span class="mandatory">*</span></label>
                    <input type="text" name="iban" id="iban" required>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="sepa_mandate" required>
                    <span class="checkmark"></span>
                    Ich ermächtige {{ $gymData['name'] }}, Zahlungen von meinem Konto mittels Lastschrift einzuziehen.
                    Zugleich weise ich mein Kreditinstitut an, die von {{ $gymData['name'] }} auf mein Konto gezogenen
                    Lastschriften einzulösen.
                </label>
            </div>
        </div>

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

        {{-- Fitness-Ziele --}}
        {{--
        <div class="form-section">
            <h3>Welches sportliche Ziel willst du mit XXX erreichen?</h3>
            <div class="goals-buttons">
                <button type="button" class="goal-btn" data-goal="abnehmen">Abnehmen</button>
                <button type="button" class="goal-btn" data-goal="ausdauer">Ausdauer</button>
                <button type="button" class="goal-btn" data-goal="ausgleich">Ausgleich / Stressabbau</button>
                <button type="button" class="goal-btn" data-goal="beweglichkeit">Beweglichkeit</button>
                <button type="button" class="goal-btn" data-goal="gesundheit">Gesundheit</button>
                <button type="button" class="goal-btn" data-goal="koerperdefinition">Körperdefinition</button>
                <button type="button" class="goal-btn active" data-goal="muskelaufbau">Muskelaufbau</button>
            </div>
        </div>
        --}}

        {{-- reCAPTCHA --}}
        {{--
        <div class="form-group">
            <div class="recaptcha-placeholder">
                <input type="checkbox" name="recaptcha" required>
                <span>Ich bin kein Roboter</span>
            </div>
        </div>
        --}}

        <button id="next-button" class="next-btn">Weiter</button>
    </form>
</div>
