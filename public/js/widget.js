(function() {
    'use strict';

    // Widget-Klasse
    class GymportalWidget {
        constructor(config) {
            this.config = {
                containerId: "gymportal-widget",
                apiEndpoint: window.location.origin,
                cssUrl: null,
                debugMode: false,
                ...config,
            };

            this.container = null;
            this.shadowRoot = null;
            this.currentStep = 'plans';
            this.selectedPlan = null;
            this.formData = {};
            this.sessionId = this.generateSessionId();

            this.config.cssUrl = this.config.cssUrl || `${this.config.apiEndpoint}/embed/gymportal-widget.css`;
            this.log('Widget initialized with config:', this.config);
        }

        async init() {
            try {
                this.container = document.getElementById(this.config.containerId);
                if (!this.container) {
                    throw new Error(`Container nicht gefunden: ${this.config.containerId}`);
                }

                this.showLoading();
                this.shadowRoot = this.container.attachShadow({ mode: "open" });
                await this.loadStyles();
                this.trackEvent('view', 'plans');
                await this.render();
                this.bindGlobalEvents();

                this.log('Widget successfully initialized');

            } catch (error) {
                this.handleError('Initialization failed', error);
            }
        }

        showLoading() {
            this.container.innerHTML = `
                <div style="text-align: center; padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                    <div style="display: inline-block; width: 32px; height: 32px; border: 3px solid #f3f4f6; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <style>
                        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    </style>
                    <p style="margin-top: 16px; color: #6b7280; font-size: 14px;">Widget wird geladen...</p>
                </div>
            `;
        }

        async loadStyles() {
            try {
                const link = document.createElement("link");
                link.rel = "stylesheet";
                link.href = this.config.cssUrl;
                link.onerror = () => {
                    this.log('CSS could not be loaded, using inline styles');
                    this.injectInlineStyles();
                };
                this.shadowRoot.appendChild(link);

                await new Promise((resolve) => {
                    link.onload = resolve;
                    setTimeout(resolve, 1000);
                });

            } catch (error) {
                this.log('CSS loading failed, using inline styles');
                this.injectInlineStyles();
            }
        }

        injectInlineStyles() {
            const style = document.createElement('style');
            style.textContent = `
                .widget-container {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                }
                .plan-card {
                    border: 2px solid #e5e7eb;
                    border-radius: 12px;
                    padding: 24px;
                    cursor: pointer;
                    transition: all 0.3s;
                    margin-bottom: 16px;
                }
                .plan-card:hover, .plan-card.selected {
                    border-color: var(--primary-color, #3b82f6);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 20px rgba(59, 130, 246, 0.1);
                }
                .btn-primary {
                    background: var(--primary-color, #3b82f6);
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: all 0.2s;
                    width: 100%;
                }
                .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
                .btn-primary:disabled { background: #d1d5db; cursor: not-allowed; transform: none; }
                .btn-secondary {
                    background: #6b7280;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    transition: all 0.2s;
                }
                .btn-secondary:hover { background: #4b5563; }
                .form-input {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 16px;
                }
                .form-input:focus {
                    outline: none;
                    border-color: var(--primary-color, #3b82f6);
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                .error-message { color: #ef4444; font-size: 14px; margin-top: 8px; }
                .success-message { color: #10b981; font-size: 16px; font-weight: 600; }
                .checkout-summary {
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 20px 0;
                }
            `;
            this.shadowRoot.appendChild(style);
        }

        async render() {
            try {
                const response = await this.apiRequest(`/widget/markup/${this.currentStep}`);

                if (!response || !response.html) {
                    throw new Error('Invalid API response');
                }

                this.shadowRoot.innerHTML = "";
                await this.loadStyles();

                const wrapper = document.createElement("div");
                wrapper.innerHTML = response.html;
                this.shadowRoot.appendChild(wrapper);

                this.applyTheme();
                this.bindStepEvents();

                // Form-Daten wiederherstellen, wenn wir zum Form-Step zurückkehren
                if (this.currentStep === 'form' && Object.keys(this.formData).length > 0) {
                    this.restoreFormData();
                }

                this.log(`Rendered step: ${this.currentStep}`);

            } catch (error) {
                this.handleError(`Failed to render step: ${this.currentStep}`, error);
            }
        }

        applyTheme() {
            const container = this.shadowRoot?.querySelector('.widget-container');
            if (!container) {
                this.log('Container not found for theme application');
                return;
            }

            this.loadThemeFromJSON();

            if (container && this.config.theme) {
                container.style.setProperty('--primary-color', this.config.theme.primaryColor || '#3b82f6');
                container.style.setProperty('--secondary-color', this.config.theme.secondaryColor || '#f8fafc');
                container.style.setProperty('--text-color', this.config.theme.textColor || '#1f2937');
            }

            this.log('Theme applied successfully:', this.config.theme);
        }

        loadThemeFromJSON() {
            let themeConfig = {};

            // Versuche Theme aus JSON-Script-Tag zu laden
            const themeScript = this.shadowRoot.getElementById('gymportal-widget-theme');
            if (themeScript) {
                try {
                    themeConfig = JSON.parse(themeScript.textContent);
                    this.log('Theme loaded from JSON script:', themeConfig);
                } catch (error) {
                    this.log('Could not parse theme JSON:', error);
                }
            }

            // Basis-Farben mit Fallbacks
            const primaryColor = themeConfig.primaryColor || '#3b82f6';
            const secondaryColor = themeConfig.secondaryColor || '#f8fafc';
            const textColor = themeConfig.textColor || '#1f2937';

            // Vollständiges Theme-Objekt mit abgeleiteten Farben erstellen
            this.config.theme = {
                primaryColor: primaryColor,
                secondaryColor: secondaryColor,
                textColor: textColor,
            };

            return this.config.theme;
        }

        bindStepEvents() {
            switch (this.currentStep) {
                case 'plans':
                    this.setupPlanSelection();
                    break;
                case 'form':
                    this.setupFormEvents();
                    break;
                case 'checkout':
                    this.setupCheckout();
                    break;
            }
        }

        setupPlanSelection() {
            const plans = this.shadowRoot.querySelectorAll("label[data-plan]");
            const nextBtn = this.shadowRoot.getElementById("next-button");

            if (!nextBtn) {
                this.log('Next button not found in plans step');
                return;
            }

            // Vorausgewählten Plan wiederherstellen
            if (this.selectedPlan) {
                const selectedPlanInput = this.shadowRoot.querySelector(`input[value="${this.selectedPlan}"]`);
                if (selectedPlanInput) {
                    selectedPlanInput.checked = true;
                    selectedPlanInput.closest('label').classList.add('selected');
                    nextBtn.disabled = false;
                    nextBtn.classList.remove('disabled');
                }
            }

            plans.forEach((plan) => {
                plan.addEventListener("click", () => {
                    plans.forEach((p) => p.classList.remove("selected"));
                    plan.classList.add("selected");
                    const input = plan.querySelector("input");
                    if (input) {
                        input.checked = true;
                        this.selectedPlan = input.value;
                    }

                    nextBtn.disabled = false;
                    nextBtn.classList.remove('disabled');

                    this.trackEvent('plan_selected', 'plans', {
                        plan_id: this.selectedPlan,
                        plan_name: plan.querySelector('.plan-name')?.textContent
                    });
                });
            });

            nextBtn.addEventListener("click", async () => {
                const selected = this.shadowRoot.querySelector('input[name="plan"]:checked');

                if (!selected) {
                    this.showError("Bitte wähle einen Tarif aus.");
                    return;
                }

                this.selectedPlan = selected.value;
                this.trackEvent('form_started', 'plans', { plan_id: this.selectedPlan });

                await this.goToStep('form');
            });
        }

        setupFormEvents() {
            const form = this.shadowRoot.getElementById("member-form");
            const nextBtn = this.shadowRoot.getElementById("next-button");
            const backBtn = this.shadowRoot.querySelector(".back-btn");

            if (!form || !nextBtn) {
                this.log('Form elements not found');
                return;
            }

            // Back-Button - JavaScript-Navigation statt history.back()
            if (backBtn) {
                backBtn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    // Aktuelle Formulardaten vor dem Verlassen sammeln
                    this.saveCurrentFormData();
                    await this.goToStep('plans');
                });
            }

            // Echtzeit-Validierung
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', () => this.validateField(input));
                input.addEventListener('input', () => this.clearFieldError(input));
            });

            // E-Mail-Bestätigung validieren
            const emailConfirm = this.shadowRoot.getElementById('email_confirmation');
            if (emailConfirm) {
                emailConfirm.addEventListener('blur', () => this.validateEmailConfirmation());
            }

            // IBAN-Validierung und Formatierung
            const ibanInput = this.shadowRoot.getElementById('iban');
            if (ibanInput) {
                ibanInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\s/g, '').toUpperCase();
                    let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
                    e.target.value = formatted;
                });
                ibanInput.addEventListener('blur', () => this.validateIban());
            }

            // Fitness-Ziele Button-Events
            const goalButtons = this.shadowRoot.querySelectorAll('.goal-btn');
            goalButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    btn.classList.toggle('active');
                });
            });

            // Formular-Submit - NUR Daten sammeln und validieren, NICHT an API senden
            form.addEventListener("submit", async (e) => {
                e.preventDefault();
                await this.processFormData();
            });
        }

        setupCheckout() {
            const purchaseBtn = this.shadowRoot.getElementById("purchase-button") ||
                              this.shadowRoot.querySelector('[id*="purchase"]') ||
                              this.shadowRoot.querySelector('[id*="kaufen"]') ||
                              this.shadowRoot.querySelector('.purchase-btn');

            const backBtn = this.shadowRoot.querySelector(".back-btn");

            // Back-Button - zurück zum Formular
            if (backBtn) {
                backBtn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    await this.goToStep('form');
                });
            }

            // JETZT KAUFEN Button - hier passiert die Vertragserstellung
            if (purchaseBtn) {
                purchaseBtn.addEventListener("click", async () => {
                    await this.createContract();
                });
            } else {
                this.log('Purchase button not found in checkout step');
            }
        }

        bindGlobalEvents() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const modal = this.shadowRoot.querySelector('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                }
            });

            window.addEventListener('resize', () => {
                this.adjustLayout();
            });
        }

        async goToStep(step) {
            this.currentStep = step;
            this.showLoading();
            await this.render();
        }

        // Neue Methode: Aktuelle Formulardaten sammeln und zwischenspeichern
        saveCurrentFormData() {
            const form = this.shadowRoot.getElementById("member-form");
            if (!form) return;

            const formData = new FormData(form);

            // Alle Formularfelder durchgehen und in this.formData speichern
            for (let [key, value] of formData.entries()) {
                this.formData[key] = value;
            }

            // Checkboxen separat behandeln (da sie nur bei checked in FormData erscheinen)
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                this.formData[checkbox.name] = checkbox.checked;
            });

            // Fitness-Ziele sammeln
            const selectedGoals = this.shadowRoot.querySelectorAll('.goal-btn.active');
            if (selectedGoals.length > 0) {
                this.formData.fitness_goals = Array.from(selectedGoals).map(btn => btn.dataset.goal).join(',');
            }

            this.log('Current form data saved:', this.formData);
        }

        // Neue Methode: Gespeicherte Formulardaten wiederherstellen
        restoreFormData() {
            if (!this.formData || Object.keys(this.formData).length === 0) {
                this.log('No form data to restore');
                return;
            }

            this.log('Restoring form data:', this.formData);

            // Standard-Eingabefelder wiederherstellen
            Object.keys(this.formData).forEach(key => {
                const field = this.shadowRoot.getElementById(key) ||
                             this.shadowRoot.querySelector(`[name="${key}"]`);

                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = this.formData[key] === true || this.formData[key] === 'true';
                    } else {
                        field.value = this.formData[key];
                    }
                }
            });

            // Fitness-Ziele wiederherstellen
            if (this.formData.fitness_goals) {
                const goals = this.formData.fitness_goals.split(',');
                goals.forEach(goal => {
                    const goalBtn = this.shadowRoot.querySelector(`[data-goal="${goal}"]`);
                    if (goalBtn) {
                        goalBtn.classList.add('active');
                    }
                });
            }

            // IBAN formatieren, falls vorhanden
            const ibanField = this.shadowRoot.getElementById('iban');
            if (ibanField && this.formData.iban) {
                const iban = this.formData.iban.replace(/\s/g, '').toUpperCase();
                const formatted = iban.match(/.{1,4}/g)?.join(' ') || iban;
                ibanField.value = formatted;
            }

            this.log('Form data restored successfully');
        }

        // Formulardaten verarbeiten (ohne API-Call)
        async processFormData() {
            const nextBtn = this.shadowRoot.getElementById("next-button");

            try {
                if (nextBtn) {
                    nextBtn.disabled = true;
                    nextBtn.textContent = "Wird verarbeitet...";
                }

                // Formulardaten sammeln
                const formData = this.collectFormData();

                // Validierung
                const validation = this.validateFormData(formData);
                if (!validation.valid) {
                    this.showErrors(validation.errors);
                    return;
                }

                // Daten speichern für Checkout
                this.formData = formData;

                const response = await this.apiRequest("/widget/save-form-data", {
                    method: "POST",
                    body: JSON.stringify({
                        form_data: this.formData,
                        selected_plan: this.selectedPlan
                    }),
                });

                if (response && response.success) {
                    this.trackEvent('form_completed', 'form', { plan_id: this.selectedPlan });
                } else {
                    throw new Error(response?.message || 'Zwischenspeichern der Formulardaten fehlgeschlagen');
                }

                // Zu Checkout wechseln (ohne API-Call)
                await this.goToStep('checkout');

            } catch (error) {
                this.handleError('Form processing failed', error);
                this.trackEvent('form_validation_failed', 'form', {
                    error: error.message,
                    plan_id: this.selectedPlan
                });
            } finally {
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.textContent = "Weiter";
                }
            }
        }

        // Vertrag erstellen (nur beim "Jetzt kaufen" Button)
        async createContract() {
            const purchaseBtn = this.shadowRoot.getElementById("purchase-button") ||
                              this.shadowRoot.querySelector('[id*="purchase"]') ||
                              this.shadowRoot.querySelector('[id*="kaufen"]') ||
                              this.shadowRoot.querySelector('.purchase-btn');

            try {
                if (purchaseBtn) {
                    purchaseBtn.disabled = true;
                    purchaseBtn.textContent = "Wird verarbeitet...";
                }

                this.trackEvent('purchase_initiated', 'checkout', {
                    plan_id: this.selectedPlan
                });

                // Finale Validierung
                if (!this.formData || !this.selectedPlan) {
                    throw new Error('Formulardaten oder Plan fehlen');
                }

                // API-Aufruf für Vertragserstellung
                const response = await this.apiRequest("/widget/contracts", {
                    method: "POST",
                    body: JSON.stringify({
                        ...this.formData,
                        plan_id: this.selectedPlan,
                        session_id: this.sessionId
                    }),
                });

                if (response && response.success) {
                    this.trackEvent('registration_completed', 'checkout', {
                        member_id: response.member_id,
                        membership_id: response.membership_id,
                        plan_id: this.selectedPlan
                    });

                    // Erfolgs-UI anzeigen
                    this.showContractSuccess(response);
                } else {
                    throw new Error(response?.message || 'Vertragserstellung fehlgeschlagen');
                }

            } catch (error) {
                this.handleError('Contract creation failed', error);
                this.trackEvent('registration_failed', 'checkout', {
                    error: error.message,
                    plan_id: this.selectedPlan
                });

                // Button wieder aktivieren
                if (purchaseBtn) {
                    purchaseBtn.disabled = false;
                    purchaseBtn.textContent = "Zahlungspflichtig bestellen";
                }
            }
        }

        // Erfolgreiche Vertragserstellung anzeigen
        showContractSuccess(response) {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="confirmation-section" style="text-align: center; padding: 40px;">
                    <div style="color: #10b981; font-size: 48px; margin-bottom: 16px;">✅</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Registrierung erfolgreich!</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Vielen Dank${this.formData.first_name ? ', ' + this.formData.first_name : ''}!
                        Dein Vertrag wurde erfolgreich erstellt.
                    </p>
                    <div class="checkout-summary">
                        <h3>Deine Mitgliedschaftsdetails:</h3>
                        <p><strong>Mitgliedsnummer:</strong> ${response.member?.member_number || 'Wird per E-Mail zugesendet'}</p>
                        <p><strong>Plan:</strong> ${response.plan?.name || 'Gewählter Tarif'}</p>
                        <p><strong>Status:</strong> Aktiv</p>
                    </div>
                    <p style="font-size: 14px; color: #6b7280; margin-top: 20px;">
                        Du erhältst in Kürze eine Bestätigungs-E-Mail mit allen Details.
                    </p>
                    <button id="restart-button" class="restart-btn">
                        Neue Registrierung starten
                    </button>
                </div>
            `;

            this.trackEvent('success_page_viewed', 'success', {
                member_id: response.member_id
            });

            const restartBtn = this.shadowRoot.getElementById("restart-button");

            // Restart-Button
            if (restartBtn) {
                restartBtn.addEventListener("click", async () => {
                    this.selectedPlan = null;
                    this.formData = {};
                    await this.goToStep('plans');
                });
            }
        }

        collectFormData() {
            const form = this.shadowRoot.getElementById("member-form");
            if (!form) return {};

            const formData = new FormData(form);
            const data = {};

            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }

            // Checkboxen separat behandeln
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                data[checkbox.name] = checkbox.checked;
            });

            // Fitness-Ziele sammeln
            const selectedGoals = this.shadowRoot.querySelectorAll('.goal-btn.active');
            if (selectedGoals.length > 0) {
                data.fitness_goals = Array.from(selectedGoals).map(btn => btn.dataset.goal).join(',');
            }

            // Adresse zusammensetzen
            if (data.address && data.address_addition) {
                data.full_address = `${data.address} ${data.address_addition}`;
            }

            return data;
        }

        validateFormData(data) {
            const errors = [];

            const required = ['first_name', 'last_name', 'email'];
            required.forEach(field => {
                if (!data[field] || data[field].trim() === '') {
                    errors.push(`${this.getFieldLabel(field)} ist erforderlich`);
                }
            });

            if (data.email && !this.isValidEmail(data.email)) {
                errors.push('Bitte gib eine gültige E-Mail-Adresse ein');
            }

            if (data.email !== data.email_confirmation) {
                errors.push('E-Mail-Adressen stimmen nicht überein');
            }

            if (data.iban && !this.isValidIban(data.iban)) {
                errors.push('Bitte gib eine gültige IBAN ein');
            }

            return {
                valid: errors.length === 0,
                errors: errors
            };
        }

        getFieldLabel(field) {
            const labels = {
                first_name: 'Vorname',
                last_name: 'Nachname',
                email: 'E-Mail-Adresse',
                birth_date: 'Geburtsdatum',
                phone: 'Mobilfunknummer',
                address: 'Adresse',
                city: 'Stadt',
                postal_code: 'Postleitzahl',
                account_holder: 'Kontoinhaber'
            };
            return labels[field] || field;
        }

        isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        isValidIban(iban) {
            const cleaned = iban.replace(/\s/g, '').toUpperCase();
            return /^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(cleaned) && cleaned.length >= 15 && cleaned.length <= 34;
        }

        validateField(field) {
            const value = field.value.trim();
            const fieldName = field.name;

            this.clearFieldError(field);

            if (field.required && !value) {
                this.showFieldError(field, `${this.getFieldLabel(fieldName)} ist erforderlich`);
                return false;
            }

            if (fieldName === 'email' && value && !this.isValidEmail(value)) {
                this.showFieldError(field, 'Bitte gib eine gültige E-Mail-Adresse ein');
                return false;
            }

            return true;
        }

        validateEmailConfirmation() {
            const email = this.shadowRoot.getElementById('email')?.value;
            const emailConfirm = this.shadowRoot.getElementById('email_confirmation');

            if (emailConfirm && email !== emailConfirm.value) {
                this.showFieldError(emailConfirm, 'E-Mail-Adressen stimmen nicht überein');
                return false;
            }

            this.clearFieldError(emailConfirm);
            return true;
        }

        validateIban() {
            const ibanInput = this.shadowRoot.getElementById('iban');
            if (!ibanInput) return true;

            const iban = ibanInput.value;
            if (iban && !this.isValidIban(iban)) {
                this.showFieldError(ibanInput, 'Bitte gib eine gültige IBAN ein');
                return false;
            }

            this.clearFieldError(ibanInput);
            return true;
        }

        showFieldError(field, message) {
            this.clearFieldError(field);

            field.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;

            field.parentNode.appendChild(errorDiv);
        }

        clearFieldError(field) {
            if (!field) return;

            field.classList.remove('error');
            const errorMsg = field.parentNode?.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
            }
        }

        showErrors(errors) {
            const errorContainer = this.shadowRoot.querySelector('.error-container') || this.createErrorContainer();
            errorContainer.innerHTML = `
                <div class="error-list">
                    <h4>Bitte korrigiere folgende Fehler:</h4>
                    <ul>
                        ${errors.map(error => `<li>${error}</li>`).join('')}
                    </ul>
                </div>
            `;
            errorContainer.style.display = 'block';

            setTimeout(() => {
                errorContainer.style.display = 'none';
            }, 5000);
        }

        createErrorContainer() {
            const container = document.createElement('div');
            container.className = 'error-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #fee2e2;
                border: 1px solid #fecaca;
                color: #991b1b;
                padding: 16px;
                border-radius: 8px;
                max-width: 400px;
                z-index: 1000;
                display: none;
            `;

            this.shadowRoot.appendChild(container);
            return container;
        }

        showSuccess(message) {
            const successContainer = this.shadowRoot.querySelector('.success-container') || this.createSuccessContainer();
            successContainer.innerHTML = `<div class="success-message">${message}</div>`;
            successContainer.style.display = 'block';

            setTimeout(() => {
                successContainer.style.display = 'none';
            }, 3000);
        }

        createSuccessContainer() {
            const container = document.createElement('div');
            container.className = 'success-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #d1fae5;
                border: 1px solid #a7f3d0;
                color: #065f46;
                padding: 16px;
                border-radius: 8px;
                max-width: 400px;
                z-index: 1000;
                display: none;
            `;

            this.shadowRoot.appendChild(container);
            return container;
        }

        showError(message) {
            this.showErrors([message]);
        }

        adjustLayout() {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            const width = window.innerWidth;
            if (width < 768) {
                container.classList.add('mobile');
            } else {
                container.classList.remove('mobile');
            }
        }

        generateSessionId() {
            return 'widget_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        trackEvent(eventType, step, data = {}) {
            this.log(`Event: ${eventType}`, { step, data });

            this.apiRequest('/widget/analytics', {
                method: 'POST',
                body: JSON.stringify({
                    event_type: eventType,
                    step: step,
                    data: data,
                    session_id: this.sessionId,
                    timestamp: new Date().toISOString()
                })
            }).catch(error => {
                this.log('Analytics tracking failed:', error);
            });
        }

        async apiRequest(endpoint, options = {}) {
            const url = `${this.config.apiEndpoint}/api${endpoint}`;

            const defaultOptions = {
                method: 'GET',
                headers: {
                    "Content-Type": "application/json",
                    "X-API-Key": this.config.apiKey,
                    "X-Studio-ID": this.config.studioId,
                    "X-Widget-Session": this.sessionId,
                },
            };

            const finalOptions = { ...defaultOptions, ...options };

            try {
                this.log(`API Request: ${finalOptions.method} ${url}`);

                const response = await fetch(url, finalOptions);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                this.log(`API Response:`, data);

                return data;

            } catch (error) {
                this.log(`API Error:`, error);
                throw error;
            }
        }

        handleError(context, error) {
            this.log(`Error in ${context}:`, error);

            const userMessage = this.getUserErrorMessage(error);
            this.showError(userMessage);

            if (context.includes('Contract creation')) {
                // Bei Vertragserstellung-Fehlern nicht fallback UI zeigen
                return;
            }

            this.showFallbackUI(context, error);
        }

        getUserErrorMessage(error) {
            if (error.message.includes('fetch')) {
                return 'Verbindungsfehler. Bitte prüfe deine Internetverbindung und versuche es erneut.';
            }

            if (error.message.includes('401') || error.message.includes('403')) {
                return 'Authentifizierungsfehler. Bitte kontaktiere den Support.';
            }

            if (error.message.includes('404')) {
                return 'Service nicht gefunden. Bitte versuche es später erneut.';
            }

            if (error.message.includes('500')) {
                return 'Serverfehler. Bitte versuche es später erneut.';
            }

            return 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es erneut.';
        }

        showFallbackUI(context, error) {
            if (this.shadowRoot) {
                this.shadowRoot.innerHTML = `
                    <div style="text-align: center; padding: 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                        <div style="color: #ef4444; font-size: 48px; margin-bottom: 16px;">⚠️</div>
                        <h3 style="color: #374151; margin-bottom: 16px;">Widget konnte nicht geladen werden</h3>
                        <p style="color: #6b7280; margin-bottom: 24px;">${this.getUserErrorMessage(error)}</p>
                        <button onclick="location.reload()" style="background: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer;">
                            Seite neu laden
                        </button>
                    </div>
                `;
            }
        }

        log(...args) {
            if (this.config.debugMode) {
                console.log('[GymportalWidget]', ...args);
            }
        }
    }

    // Globale API
    window.GymportalWidget = {
        init: function(config) {
            try {
                const widget = new GymportalWidget(config);
                widget.init();
                return widget;
            } catch (error) {
                console.error('[GymportalWidget] Initialization failed:', error);
                return null;
            }
        },

        version: '1.0.0'
    };

    // Auto-Initialisierung wenn data-Attribute vorhanden
    document.addEventListener('DOMContentLoaded', function() {
        const autoInitElements = document.querySelectorAll('[data-gymportal-widget]');

        autoInitElements.forEach(element => {
            const config = {
                containerId: element.id,
                apiEndpoint: element.dataset.apiEndpoint,
                apiKey: element.dataset.apiKey,
                studioId: element.dataset.studioId,
                debugMode: element.dataset.debugMode === 'true'
            };

            window.GymportalWidget.init(config);
        });
    });

})();
