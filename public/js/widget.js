(function() {
    'use strict';

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
            this.mollieWindow = null;

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
                .iban-details-section {
                    margin-top: 20px;
                    padding: 20px;
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    display: none;
                }
                .iban-details-section.show { display: block; }
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

            const themeScript = this.shadowRoot.getElementById('gymportal-widget-theme');
            if (themeScript) {
                try {
                    themeConfig = JSON.parse(themeScript.textContent);
                    this.log('Theme loaded from JSON script:', themeConfig);
                } catch (error) {
                    this.log('Could not parse theme JSON:', error);
                }
            }

            const primaryColor = themeConfig.primaryColor || '#3b82f6';
            const secondaryColor = themeConfig.secondaryColor || '#f8fafc';
            const textColor = themeConfig.textColor || '#1f2937';

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
            const durationButtons = this.shadowRoot.querySelectorAll(".duration-btn");
            const noPlansMessage = this.shadowRoot.querySelector(".no-plans-message");
            const durationSelector = this.shadowRoot.querySelector(".duration-selector");

            if (!nextBtn) {
                this.log('Next button not found in plans step');
                return;
            }

            // Duration filter functionality - only if duration selector is present
            if (durationSelector && durationButtons.length > 0) {
                this.setupDurationFilter(durationButtons, plans, noPlansMessage, nextBtn);

                // Initial filter to 12 months (already active by default)
                this.filterPlansByDuration(12, plans, noPlansMessage, nextBtn);
            } else {
                // If no duration selector, show all plans
                plans.forEach((plan) => {
                    plan.style.display = 'flex';
                });
            }

            // Existing plan selection logic...
            if (this.selectedPlan) {
                const selectedPlanInput = this.shadowRoot.querySelector(`input[value="${this.selectedPlan}"]`);
                if (selectedPlanInput) {
                    const selectedPlanCard = selectedPlanInput.closest('label');
                    // Check if the selected plan is visible with current duration filter
                    if (selectedPlanCard && selectedPlanCard.style.display !== 'none') {
                        selectedPlanInput.checked = true;
                        selectedPlanCard.classList.add('selected');
                        nextBtn.disabled = false;
                        nextBtn.classList.remove('disabled');
                    } else {
                        // If selected plan is not visible, reset selection
                        this.selectedPlan = null;
                        nextBtn.disabled = true;
                        nextBtn.classList.add('disabled');
                    }
                }
            }

            plans.forEach((plan) => {
                plan.addEventListener("click", () => {
                    // Only allow selection if plan is visible
                    if (plan.style.display === 'none') return;

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
                        plan_name: plan.querySelector('.plan-name')?.textContent,
                        duration: plan.dataset.duration
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

        setupDurationFilter(durationButtons, plans, noPlansMessage, nextBtn) {
            durationButtons.forEach((button) => {
                button.addEventListener("click", (e) => {
                    e.preventDefault();

                    // Update button states
                    durationButtons.forEach((btn) => btn.classList.remove("active"));
                    button.classList.add("active");

                    const selectedDuration = parseInt(button.dataset.duration);

                    // Filter plans and reset selection
                    this.filterPlansByDuration(selectedDuration, plans, noPlansMessage, nextBtn);

                    // Track duration filter usage
                    this.trackEvent('duration_filter_selected', 'plans', {
                        selected_duration: selectedDuration,
                        available_plans: this.getVisiblePlansCount(plans)
                    });
                });
            });
        }

        filterPlansByDuration(selectedDuration, plans, noPlansMessage, nextBtn) {
            let visiblePlansCount = 0;
            let hasSelectedPlan = false;

            plans.forEach((plan) => {
                const planDuration = parseInt(plan.dataset.duration);

                if (planDuration === selectedDuration) {
                    plan.style.display = 'flex';
                    visiblePlansCount++;

                    // Check if this plan was previously selected
                    const input = plan.querySelector('input[name="plan"]');
                    if (input && input.checked) {
                        hasSelectedPlan = true;
                    }
                } else {
                    plan.style.display = 'none';

                    // Uncheck hidden plans
                    const input = plan.querySelector('input[name="plan"]');
                    if (input && input.checked) {
                        input.checked = false;
                        plan.classList.remove('selected');
                    }
                }
            });

            // Show/hide no plans message
            if (noPlansMessage) {
                if (visiblePlansCount === 0) {
                    noPlansMessage.style.display = 'block';
                } else {
                    noPlansMessage.style.display = 'none';
                }
            }

            // Reset next button if no plan selected or selected plan is hidden
            if (!hasSelectedPlan) {
                this.selectedPlan = null;
                nextBtn.disabled = true;
                nextBtn.classList.add('disabled');
            }

            this.log(`Filtered plans by duration ${selectedDuration}: ${visiblePlansCount} visible`);
        }

        getVisiblePlansCount(plans) {
            let count = 0;
            plans.forEach((plan) => {
                if (plan.style.display !== 'none') {
                    count++;
                }
            });
            return count;
        }

        setupFormEvents() {
            const form = this.shadowRoot.getElementById("member-form");
            const nextBtn = this.shadowRoot.getElementById("next-button");
            const backBtn = this.shadowRoot.querySelector(".back-btn");

            if (!form || !nextBtn) {
                this.log('Form elements not found');
                return;
            }

            if (backBtn) {
                backBtn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    this.saveCurrentFormData();
                    await this.goToStep('plans');
                });
            }

            this.setupPaymentMethodHandlers();
            this.setupIbanInputHandlers();

            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                // Add trimming on blur for text-based inputs
                input.addEventListener('blur', () => {
                    this.trimInputValue(input);
                    this.validateField(input);
                });
                input.addEventListener('input', () => this.clearFieldError(input));
            });

            const emailConfirm = this.shadowRoot.getElementById('email_confirmation');
            if (emailConfirm) {
                emailConfirm.addEventListener('blur', () => {
                    this.trimInputValue(emailConfirm);
                    this.validateEmailConfirmation();
                });
            }

            const goalButtons = this.shadowRoot.querySelectorAll('.goal-btn');
            goalButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    btn.classList.toggle('active');
                });
            });

            form.addEventListener("submit", async (e) => {
                e.preventDefault();
                await this.processFormData();
            });
        }

        /**
         * Trims the value of input fields (except for certain types that shouldn't be trimmed)
         * @param {HTMLElement} input - The input element to trim
         */
        trimInputValue(input) {
            if (!input || !input.value) return;

            // Don't trim certain input types
            const nonTrimmableTypes = ['password', 'checkbox', 'radio', 'file', 'range', 'color'];
            if (nonTrimmableTypes.includes(input.type)) return;

            // Don't trim select elements
            if (input.tagName.toLowerCase() === 'select') return;

            // Special handling for IBAN (already formatted)
            if (input.id === 'iban' || input.name === 'iban') {
                // IBAN has special formatting, just trim leading/trailing spaces
                input.value = input.value.trim();
                return;
            }

            // Trim the value for all other text inputs
            const trimmedValue = input.value.trim();
            if (input.value !== trimmedValue) {
                input.value = trimmedValue;
                this.log(`Trimmed input ${input.name || input.id}: "${input.value}"`);
            }
        }

        setupIbanInputHandlers() {
            const ibanInput = this.shadowRoot.getElementById('iban');
            if (ibanInput) {
                ibanInput.addEventListener('input', (e) => {
                    let value = e.target.value.replace(/\s/g, '').toUpperCase();
                    let formatted = value.replace(/(.{4})/g, '$1 ').trim();
                    e.target.value = formatted;
                    this.clearFieldError(e.target);
                });

                ibanInput.addEventListener('blur', () => {
                    this.trimInputValue(ibanInput);
                    this.validateIban();
                });
            }
        }

        setupPaymentMethodHandlers() {
            const paymentRadios = this.shadowRoot.querySelectorAll('input[name="payment_method"]');
            const infoContainer = this.shadowRoot.getElementById('payment-method-info');
            const infoText = infoContainer?.querySelector('.info-text');
            const sepaMandateSection = this.shadowRoot.getElementById('sepa-mandate-section');
            const sepaMandateCheckbox = this.shadowRoot.getElementById('sepa_mandate_acknowledged');
            const ibanDetailsSection = this.shadowRoot.getElementById('iban-details-section');
            const accountHolderInput = this.shadowRoot.getElementById('account_holder');
            const ibanInput = this.shadowRoot.getElementById('iban');

            if (!paymentRadios.length) {
                this.log('No payment method radios found');
                return;
            }

            const updatePaymentInfo = (selectedMethod) => {
                const selectedRadio = this.shadowRoot.querySelector(`input[name="payment_method"][value="${selectedMethod}"]`);
                const methodType = selectedRadio?.getAttribute('data-method-type');
                const requiresMandate = selectedRadio?.getAttribute('data-requires-mandate') === 'true';
                const requiresIban = selectedRadio?.getAttribute('data-requires-iban') === 'true';

                this.updateRadioLabelStyling(selectedMethod);

                if (ibanDetailsSection) {
                    if (requiresIban) {
                        ibanDetailsSection.classList.add('show');
                        if (accountHolderInput) accountHolderInput.required = true;
                        if (ibanInput) ibanInput.required = true;
                        if (sepaMandateCheckbox) sepaMandateCheckbox.required = true;
                    } else {
                        ibanDetailsSection.classList.remove('show');
                        if (accountHolderInput) {
                            accountHolderInput.required = false;
                            accountHolderInput.value = '';
                        }
                        if (ibanInput) {
                            ibanInput.required = false;
                            ibanInput.value = '';
                        }
                        if (sepaMandateCheckbox) {
                            sepaMandateCheckbox.required = false;
                            sepaMandateCheckbox.checked = false;
                        }
                    }
                }

                if (sepaMandateSection && !requiresIban) {
                    if (requiresMandate) {
                        sepaMandateSection.classList.add('show');
                        if (sepaMandateCheckbox) {
                            sepaMandateCheckbox.required = true;
                        }
                    } else {
                        sepaMandateSection.classList.remove('show');
                        if (sepaMandateCheckbox) {
                            sepaMandateCheckbox.required = false;
                            sepaMandateCheckbox.checked = false;
                        }
                    }
                }

                if (!infoContainer || !infoText) return;

                if (methodType === 'mollie' && !requiresIban) {
                    infoText.textContent = 'Nach der Registrierung werden Sie zur sicheren Zahlungsabwicklung weitergeleitet, um Ihre Zahlungsdaten einzugeben.';
                    infoContainer.style.display = 'block';
                    infoContainer.className = 'payment-info mollie-info';

                    this.trackEvent('payment_method_info_shown', 'form', {
                        method: selectedMethod,
                        type: 'mollie'
                    });
                } else if (requiresIban) {
                    if (selectedMethod === 'mollie_directdebit') {
                        infoText.textContent = 'Die Zahlung erfolgt per SEPA-Lastschrift direkt von Ihrem Bankkonto. Eine Weiterleitung an einen externen Zahlungsdienstleister ist nicht erforderlich.';
                        infoContainer.className = 'payment-info sepa-info';
                    } else {
                        infoText.textContent = 'Die Zahlung erfolgt per SEPA-Lastschrift. Bitte geben Sie Ihre Bankverbindung ein.';
                        infoContainer.className = 'payment-info sepa-info';
                    }
                    infoContainer.style.display = 'block';

                    this.trackEvent('payment_method_info_shown', 'form', {
                        method: selectedMethod,
                        type: 'sepa',
                        requires_iban: true
                    });
                } else if (methodType === 'standard') {
                    const message = this.getStandardPaymentMethodMessage(selectedMethod);
                    infoText.textContent = message;
                    infoContainer.style.display = 'block';
                    infoContainer.className = 'payment-info standard-info';

                    this.trackEvent('payment_method_info_shown', 'form', {
                        method: selectedMethod,
                        type: 'standard',
                        requires_mandate: requiresMandate
                    });
                } else {
                    infoContainer.style.display = 'none';
                }
            };

            paymentRadios.forEach(radio => {
                const label = radio.closest('.radio-label');

                if (label) {
                    label.addEventListener('click', (e) => {
                        if (e.target === radio) return;

                        radio.checked = true;
                        updatePaymentInfo(radio.value);

                        this.trackEvent('payment_method_selected', 'form', {
                            method: radio.value,
                            type: radio.getAttribute('data-method-type'),
                            requires_mandate: radio.getAttribute('data-requires-mandate') === 'true',
                            requires_iban: radio.getAttribute('data-requires-iban') === 'true'
                        });
                    });
                }

                radio.addEventListener('change', () => {
                    if (radio.checked) {
                        updatePaymentInfo(radio.value);

                        this.trackEvent('payment_method_selected', 'form', {
                            method: radio.value,
                            type: radio.getAttribute('data-method-type'),
                            requires_mandate: radio.getAttribute('data-requires-mandate') === 'true',
                            requires_iban: radio.getAttribute('data-requires-iban') === 'true'
                        });
                    }
                });

                if (radio.checked) {
                    updatePaymentInfo(radio.value);
                }
            });

            if (sepaMandateCheckbox) {
                sepaMandateCheckbox.addEventListener('change', () => {
                    this.trackEvent('sepa_mandate_acknowledged', 'form', {
                        acknowledged: sepaMandateCheckbox.checked
                    });
                });
            }
        }

        getStandardPaymentMethodMessage(methodKey) {
            const messages = {
                'banktransfer': 'Sie erhalten nach der Registrierung die Bankverbindung für die Überweisung.',
                'cash': 'Die Zahlung erfolgt direkt vor Ort im Studio.',
                'invoice': 'Sie erhalten eine Rechnung, die Sie per Überweisung begleichen können.',
                'standingorder': 'Bitte richten Sie einen Dauerauftrag mit den Ihnen mitgeteilten Daten ein.',
                'sepa_direct_debit': 'Das SEPA-Lastschriftmandat wird Ihnen nach der Registrierung zur Unterschrift vorgelegt.'
            };

            return messages[methodKey] || 'Weitere Informationen zur Zahlungsabwicklung erhalten Sie nach der Registrierung.';
        }

        updateRadioLabelStyling(selectedValue) {
            const allLabels = this.shadowRoot.querySelectorAll('.radio-label');
            const selectedRadio = this.shadowRoot.querySelector(`input[name="payment_method"][value="${selectedValue}"]`);
            const selectedLabel = selectedRadio?.closest('.radio-label');

            allLabels.forEach(label => {
                label.classList.remove('selected');
            });

            if (selectedLabel) {
                selectedLabel.classList.add('selected');
            }
        }

        setupCheckout() {
            const purchaseBtn = this.shadowRoot.getElementById("purchase-button") ||
                              this.shadowRoot.querySelector('[id*="purchase"]') ||
                              this.shadowRoot.querySelector('[id*="kaufen"]') ||
                              this.shadowRoot.querySelector('.purchase-btn');

            const backBtn = this.shadowRoot.querySelector(".back-btn");

            if (backBtn) {
                backBtn.addEventListener("click", async (e) => {
                    e.preventDefault();
                    await this.goToStep('form');
                });
            }

            if (purchaseBtn) {
                purchaseBtn.addEventListener("click", async () => {
                    await this.initiatePaymentProcess();
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

        async initiatePaymentProcess() {
            const purchaseBtn = this.shadowRoot.getElementById("purchase-button") ||
                            this.shadowRoot.querySelector('[id*="purchase"]') ||
                            this.shadowRoot.querySelector('[id*="kaufen"]') ||
                            this.shadowRoot.querySelector('.purchase-btn');

            try {
                if (purchaseBtn) {
                    purchaseBtn.disabled = true;
                    purchaseBtn.textContent = "Wird verarbeitet...";
                }

                this.trackEvent('payment_initiation_started', 'checkout', {
                    plan_id: this.selectedPlan
                });

                if (!this.formData || !this.selectedPlan) {
                    throw new Error('Formulardaten oder Plan fehlen');
                }

                const selectedPaymentRadio = this.shadowRoot.querySelector(`input[name="payment_method"][value="${this.formData.payment_method}"]`);
                const methodType = selectedPaymentRadio?.getAttribute('data-method-type');
                const requiresIban = selectedPaymentRadio?.getAttribute('data-requires-iban') === 'true';

                if (methodType === 'mollie' && !requiresIban) {
                    this.mollieWindow = this.openMollieWindow('about:blank');

                    if (!this.mollieWindow) {
                        this.showPopupBlockerWarning();
                        return;
                    }

                    this.showMollieLoading();
                }

                const response = await this.apiRequest("/widget/contracts", {
                    method: "POST",
                    body: JSON.stringify({
                        ...this.formData,
                        plan_id: this.selectedPlan,
                        session_id: this.sessionId
                    }),
                });

                if (response && response.success) {
                    if (response.requires_payment &&
                        response.payment_provider === 'mollie' &&
                        response.checkout_url &&
                        !requiresIban) {
                        this.handleMollieCheckout(response);
                    } else {
                        if (this.mollieWindow && !this.mollieWindow.closed) {
                            this.mollieWindow.close();
                            this.mollieWindow = null;
                        }

                        this.showContractSuccess(response);
                    }
                } else {
                    throw new Error(response?.message || 'Vertragserstellung fehlgeschlagen');
                }

            } catch (error) {
                if (this.mollieWindow && !this.mollieWindow.closed) {
                    this.mollieWindow.close();
                    this.mollieWindow = null;
                }

                this.handleError('Payment initiation failed', error);
                this.trackEvent('payment_initiation_failed', 'checkout', {
                    error: error.message,
                    plan_id: this.selectedPlan
                });

                if (purchaseBtn) {
                    purchaseBtn.disabled = false;
                    purchaseBtn.textContent = "Zahlungspflichtig bestellen";
                }
            }
        }

        handleMollieCheckout(contractResponse) {
            this.log('Continuing Mollie checkout process with existing window', contractResponse);

            if (!this.mollieWindow || this.mollieWindow.closed) {
                this.log('Original Mollie window was closed, opening new one');
                this.mollieWindow = this.openMollieWindow(contractResponse.checkout_url);

                if (!this.mollieWindow) {
                    this.showRedirectFallback(contractResponse.checkout_url);
                    return;
                }
            } else {
                this.mollieWindow.location.href = contractResponse.checkout_url;
            }

            this.monitorPaymentStatus(this.mollieWindow, contractResponse);
            this.showPaymentInProgress();
        }

        openMollieWindow(checkoutUrl) {
            const newTab = window.open(
                checkoutUrl,
                '_blank'
            );

            if (!newTab || newTab.closed) {
                this.log('Failed to open Mollie window - popup blocked?');
                return null;
            }

            newTab.focus();
            return newTab;
        }

        showMollieLoading() {
            if (!this.mollieWindow || this.mollieWindow.closed) return;

            try {
                this.mollieWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Zahlung wird vorbereitet...</title>
                        <style>
                            body {
                                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                                text-align: center;
                                padding: 50px;
                                background: #f8fafc;
                                margin: 0;
                            }
                            .spinner {
                                display: inline-block;
                                width: 40px;
                                height: 40px;
                                border: 4px solid #e2e8f0;
                                border-top: 4px solid #3b82f6;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            h2 { color: #1f2937; margin-bottom: 20px; }
                            p { color: #6b7280; }
                        </style>
                    </head>
                    <body>
                        <h2>Zahlung wird vorbereitet</h2>
                        <div class="spinner"></div>
                        <p>Sie werden in Kürze zur sicheren Zahlungsabwicklung weitergeleitet...</p>
                    </body>
                    </html>
                `);
                this.mollieWindow.document.close();
            } catch (error) {
                this.log('Could not write to Mollie window:', error);
            }
        }

        showPopupBlockerWarning() {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="popup-blocker-warning" style="text-align: center; padding: 40px;">
                    <div style="color: #f59e0b; font-size: 48px; margin-bottom: 16px;">🚫</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Popup-Blocker erkannt</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Ihr Browser blockiert das Zahlungsfenster. Bitte erlauben Sie Popups für diese Website
                        und versuchen Sie es erneut.
                    </p>
                    <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <p style="font-size: 14px; color: #6b7280; margin: 0;">
                            💡 <strong>Tipp:</strong> Klicken Sie auf das Popup-Symbol in Ihrer Adressleiste
                            und erlauben Sie Popups für diese Website.
                        </p>
                    </div>
                    <div style="margin: 20px 0;">
                        <button id="retry-popup-btn" class="btn-primary" style="margin-right: 10px;">
                            Erneut versuchen
                        </button>
                        <button id="redirect-alternative-btn" class="btn-secondary">
                            Alternative: Weiterleitung nutzen
                        </button>
                    </div>
                </div>
            `;

            const retryBtn = this.shadowRoot.getElementById('retry-popup-btn');
            const redirectBtn = this.shadowRoot.getElementById('redirect-alternative-btn');

            if (retryBtn) {
                retryBtn.addEventListener('click', () => {
                    this.initiatePaymentProcess();
                });
            }

            if (redirectBtn) {
                redirectBtn.addEventListener('click', async () => {
                    try {
                        const response = await this.apiRequest("/widget/contracts", {
                            method: "POST",
                            body: JSON.stringify({
                                ...this.formData,
                                plan_id: this.selectedPlan,
                                session_id: this.sessionId
                            }),
                        });

                        if (response?.checkout_url) {
                            window.location.href = response.checkout_url;
                        } else {
                            throw new Error('Keine Checkout-URL erhalten');
                        }
                    } catch (error) {
                        this.handleError('Redirect fallback failed', error);
                    }
                });
            }
        }

        monitorPaymentStatus(tab, contractResponse) {
            const messageHandler = (event) => {
                const allowedOrigins = [
                    window.location.origin,
                    this.config.apiEndpoint
                ];

                if (!allowedOrigins.includes(event.origin)) {
                    this.log('Ignored message from unauthorized origin:', event.origin);
                    return;
                }

                this.log('Received payment message:', event.data);

                if (event.data.type === 'MOLLIE_PAYMENT_RESULT') {
                    window.removeEventListener('message', messageHandler);

                    if (tab && !tab.closed) {
                        tab.close();
                    }

                    this.handlePaymentResult(event.data, contractResponse);
                }
            };

            window.addEventListener('message', messageHandler);

            let pollCount = 0;
            const maxPolls = 180;

            const pollStatus = setInterval(async () => {
                pollCount++;

                if (!tab || tab.closed || pollCount >= maxPolls) {
                    clearInterval(pollStatus);
                    window.removeEventListener('message', messageHandler);

                    if (pollCount >= maxPolls) {
                        this.handlePaymentTimeout(contractResponse);
                    } else {
                        await this.checkFinalPaymentStatus(contractResponse);
                    }
                    return;
                }

                if (pollCount % 4 === 0) {
                    try {
                        const statusResponse = await this.apiRequest("/widget/mollie/check-status", {
                            method: "POST",
                            body: JSON.stringify({
                                ...this.formData,
                                payment_id: contractResponse.payment_id,
                            }),
                        });

                        if (statusResponse.status === 'paid') {
                            clearInterval(pollStatus);
                            window.removeEventListener('message', messageHandler);

                            if (tab && !tab.closed) {
                                tab.close();
                            }

                            this.handlePaymentSuccess(contractResponse);
                        } else if (statusResponse.status === 'failed' || statusResponse.status === 'canceled') {
                            clearInterval(pollStatus);
                            window.removeEventListener('message', messageHandler);

                            if (tab && !tab.closed) {
                                tab.close();
                            }

                            this.handlePaymentFailure(statusResponse, contractResponse);
                        }
                    } catch (error) {
                        this.log('Payment status check failed:', error);
                    }
                }
            }, 5000);
        }

        showRedirectFallback(checkoutUrl) {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="redirect-fallback-section" style="text-align: center; padding: 40px;">
                    <div style="color: #f59e0b; font-size: 48px; margin-bottom: 16px;">🔄</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Weiterleitung zur Zahlung</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Sie werden zur sicheren Zahlungsabwicklung weitergeleitet.
                    </p>
                    <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <p style="font-size: 14px; color: #6b7280; margin: 0;">
                            ⚠️ <strong>Wichtig:</strong> Verwenden Sie die "Zurück"-Taste Ihres Browsers,
                            um nach der Zahlung zu diesem Fenster zurückzukehren.
                        </p>
                    </div>
                    <button id="redirect-to-payment" class="btn-primary" style="margin-right: 10px;">
                        Zur Zahlung
                    </button>
                    <button id="cancel-payment" class="btn-secondary">
                        Abbrechen
                    </button>
                </div>
            `;

            const redirectBtn = this.shadowRoot.getElementById('redirect-to-payment');
            const cancelBtn = this.shadowRoot.getElementById('cancel-payment');

            if (redirectBtn) {
                redirectBtn.addEventListener('click', () => {
                    window.location.href = checkoutUrl;
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', async () => {
                    await this.goToStep('checkout');
                });
            }
        }

        handlePaymentResult(paymentData, contractResponse) {
            this.log('Processing payment result:', paymentData);

            if (paymentData.status === 'paid') {
                this.handlePaymentSuccess(contractResponse);
            } else if (paymentData.status === 'failed' || paymentData.status === 'canceled') {
                this.handlePaymentFailure(paymentData, contractResponse);
            } else {
                this.checkFinalPaymentStatus(contractResponse);
            }
        }

        handlePaymentSuccess(contractResponse) {
            this.trackEvent('payment_completed', 'payment', {
                session_id: contractResponse.session_id,
                member_id: contractResponse.member_id
            });

            this.showContractSuccess({
                ...contractResponse,
                payment_completed: true
            });
        }

        handlePaymentFailure(paymentData, contractResponse) {
            this.trackEvent('payment_failed', 'payment', {
                session_id: contractResponse.session_id,
                error: paymentData.error || 'Payment failed'
            });

            this.showPaymentError(paymentData);
        }

        handlePaymentTimeout(contractResponse) {
            this.trackEvent('payment_timeout', 'payment', {
                session_id: contractResponse.session_id
            });

            this.showPaymentTimeout(contractResponse);
        }

        async checkFinalPaymentStatus(contractResponse) {
            try {
                const statusResponse = await this.apiRequest("/widget/mollie/check-status", {
                    method: "POST",
                    body: JSON.stringify({
                        ...this.formData,
                        payment_id: contractResponse.payment_id,
                    }),
                });

                if (statusResponse.status === 'paid') {
                    this.handlePaymentSuccess(contractResponse);
                } else if (statusResponse.status === 'failed' || statusResponse.status === 'canceled') {
                    this.handlePaymentFailure(statusResponse, contractResponse);
                } else {
                    this.showPaymentPending(contractResponse);
                }
            } catch (error) {
                this.log('Final payment status check failed:', error);
                this.showPaymentPending(contractResponse);
            }
        }

        showPaymentInProgress() {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="payment-progress-section" style="text-align: center; padding: 40px;">
                    <div style="color: #3b82f6; font-size: 48px; margin-bottom: 16px;">💳</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Zahlung wird verarbeitet</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Bitte schließen Sie das Zahlungsfenster nicht, bis der Vorgang abgeschlossen ist.
                    </p>
                    <div style="display: inline-block; width: 32px; height: 32px; border: 3px solid #f3f4f6; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <style>
                        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    </style>
                    <div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <p style="font-size: 14px; color: #6b7280; margin: 0;">
                            💡 <strong>Tipp:</strong> Das Zahlungsfenster wurde bereits geöffnet.
                            Falls Sie es nicht sehen, prüfen Sie Ihre Popup-Blocker-Einstellungen.
                        </p>
                    </div>
                    <button id="check-payment-status" class="btn-secondary">
                        Status prüfen
                    </button>
                </div>
            `;

            const checkBtn = this.shadowRoot.getElementById('check-payment-status');
            if (checkBtn) {
                checkBtn.addEventListener('click', async () => {
                    await this.checkFinalPaymentStatus(this.lastContractResponse);
                });
            }
        }

        showPaymentError(paymentData) {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            const errorMessage = paymentData.error || 'Die Zahlung konnte nicht verarbeitet werden.';

            container.innerHTML = `
                <div class="payment-error-section" style="text-align: center; padding: 40px;">
                    <div style="color: #ef4444; font-size: 48px; margin-bottom: 16px;">❌</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Zahlung fehlgeschlagen</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">${errorMessage}</p>
                    <div style="margin: 20px 0;">
                        <button id="retry-payment-btn" class="btn-primary" style="margin-right: 10px;">
                            Zahlung wiederholen
                        </button>
                        <button id="back-to-checkout-btn" class="btn-secondary">
                            Zurück zum Checkout
                        </button>
                    </div>
                </div>
            `;

            const retryBtn = this.shadowRoot.getElementById('retry-payment-btn');
            const backBtn = this.shadowRoot.getElementById('back-to-checkout-btn');

            if (retryBtn) {
                retryBtn.addEventListener('click', () => {
                    this.initiatePaymentProcess();
                });
            }

            if (backBtn) {
                backBtn.addEventListener('click', async () => {
                    await this.goToStep('checkout');
                });
            }
        }

        showPaymentTimeout(contractResponse) {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="payment-timeout-section" style="text-align: center; padding: 40px;">
                    <div style="color: #f59e0b; font-size: 48px; margin-bottom: 16px;">⏰</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Zeitüberschreitung</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Die Zahlungsüberwachung wurde beendet. Bitte prüfen Sie den aktuellen Status.
                    </p>
                    <div style="margin: 20px 0;">
                        <button id="check-final-status-btn" class="btn-primary" style="margin-right: 10px;">
                            Status prüfen
                        </button>
                        <button id="restart-payment-btn" class="btn-secondary">
                            Neue Zahlung starten
                        </button>
                    </div>
                </div>
            `;

            const checkBtn = this.shadowRoot.getElementById('check-final-status-btn');
            const restartBtn = this.shadowRoot.getElementById('restart-payment-btn');

            if (checkBtn) {
                checkBtn.addEventListener('click', async () => {
                    await this.checkFinalPaymentStatus(contractResponse);
                });
            }

            if (restartBtn) {
                restartBtn.addEventListener('click', async () => {
                    await this.goToStep('checkout');
                });
            }
        }

        showPaymentPending(contractResponse) {
            const container = this.shadowRoot.querySelector('.widget-container');
            if (!container) return;

            container.innerHTML = `
                <div class="payment-pending-section" style="text-align: center; padding: 40px;">
                    <div style="color: #f59e0b; font-size: 48px; margin-bottom: 16px;">⏳</div>
                    <h2 style="color: #1f2937; margin-bottom: 16px;">Zahlung wird verarbeitet</h2>
                    <p style="color: #6b7280; margin-bottom: 24px;">
                        Ihre Zahlung wird noch verarbeitet. Sie erhalten eine E-Mail-Bestätigung,
                        sobald die Zahlung eingegangen ist.
                    </p>
                    <div class="checkout-summary">
                        <h3>Ihre Registrierung:</h3>
                        <p><strong>Session:</strong> ${contractResponse.session_id}</p>
                        <p><strong>Status:</strong> Wartet auf Zahlungsbestätigung</p>
                    </div>
                    <button id="refresh-status-btn" class="btn-primary" style="margin-top: 20px;">
                        Status aktualisieren
                    </button>
                </div>
            `;

            const refreshBtn = this.shadowRoot.getElementById('refresh-status-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', async () => {
                    await this.checkFinalPaymentStatus(contractResponse);
                });
            }
        }

        saveCurrentFormData() {
            const form = this.shadowRoot.getElementById("member-form");
            if (!form) return;

            const formData = new FormData(form);

            for (let [key, value] of formData.entries()) {
                // Trim the value when saving form data
                this.formData[key] = typeof value === 'string' ? value.trim() : value;
            }

            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                this.formData[checkbox.name] = checkbox.checked;
            });

            const radioGroups = {};
            const radios = form.querySelectorAll('input[type="radio"]');
            radios.forEach(radio => {
                if (!radioGroups[radio.name]) {
                    radioGroups[radio.name] = [];
                }
                radioGroups[radio.name].push(radio);
            });

            Object.keys(radioGroups).forEach(groupName => {
                const selectedRadio = radioGroups[groupName].find(radio => radio.checked);
                if (selectedRadio) {
                    this.formData[groupName] = selectedRadio.value;
                }
            });

            const selectedGoals = this.shadowRoot.querySelectorAll('.goal-btn.active');
            if (selectedGoals.length > 0) {
                this.formData.fitness_goals = Array.from(selectedGoals).map(btn => btn.dataset.goal).join(',');
            }

            this.log('Current form data saved:', this.formData);
        }

        restoreFormData() {
            if (!this.formData || Object.keys(this.formData).length === 0) {
                this.log('No form data to restore');
                return;
            }

            this.log('Restoring form data:', this.formData);

            Object.keys(this.formData).forEach(key => {
                const field = this.shadowRoot.getElementById(key) ||
                            this.shadowRoot.querySelector(`[name="${key}"]`);

                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = this.formData[key] === true || this.formData[key] === 'true';
                    } else if (field.type === 'radio') {
                        const radioButton = this.shadowRoot.querySelector(`input[name="${key}"][value="${this.formData[key]}"]`);
                        if (radioButton) {
                            radioButton.checked = true;
                            this.updateRadioLabelStyling(this.formData[key]);
                        }
                    } else {
                        field.value = this.formData[key];
                    }
                }
            });

            if (this.formData.payment_method) {
                const paymentRadio = this.shadowRoot.querySelector(`input[name="payment_method"][value="${this.formData.payment_method}"]`);
                if (paymentRadio) {
                    paymentRadio.checked = true;
                    this.updateRadioLabelStyling(this.formData.payment_method);

                    const requiresIban = paymentRadio.getAttribute('data-requires-iban') === 'true';
                    const ibanDetailsSection = this.shadowRoot.getElementById('iban-details-section');
                    const accountHolderInput = this.shadowRoot.getElementById('account_holder');
                    const ibanInput = this.shadowRoot.getElementById('iban');
                    const sepaMandateCheckbox = this.shadowRoot.getElementById('sepa_mandate_acknowledged');

                    if (requiresIban && ibanDetailsSection) {
                        ibanDetailsSection.classList.add('show');
                        if (accountHolderInput) {
                            accountHolderInput.required = true;
                            accountHolderInput.value = this.formData.account_holder || '';
                        }
                        if (ibanInput) {
                            ibanInput.required = true;
                            ibanInput.value = this.formData.iban || '';
                        }
                        if (sepaMandateCheckbox) {
                            sepaMandateCheckbox.required = true;
                            sepaMandateCheckbox.checked = this.formData.sepa_mandate_acknowledged || false;
                        }
                    }

                    const methodType = paymentRadio.getAttribute('data-method-type');
                    const infoContainer = this.shadowRoot.getElementById('payment-method-info');
                    const infoText = infoContainer?.querySelector('.info-text');

                    if (infoContainer && infoText) {
                        if (requiresIban) {
                            if (this.formData.payment_method === 'mollie_directdebit') {
                                infoText.textContent = 'Die Zahlung erfolgt per SEPA-Lastschrift direkt von Ihrem Bankkonto. Eine Weiterleitung an einen externen Zahlungsdienstleister ist nicht erforderlich.';
                                infoContainer.className = 'payment-info sepa-info';
                            } else {
                                infoText.textContent = 'Die Zahlung erfolgt per SEPA-Lastschrift. Bitte geben Sie Ihre Bankverbindung ein.';
                                infoContainer.className = 'payment-info sepa-info';
                            }
                            infoContainer.style.display = 'block';
                        } else if (methodType === 'mollie') {
                            infoText.textContent = 'Nach der Registrierung werden Sie zur sicheren Zahlungsabwicklung weitergeleitet, um Ihre Zahlungsdaten einzugeben.';
                            infoContainer.style.display = 'block';
                            infoContainer.className = 'payment-info mollie-info';
                        } else if (methodType === 'standard') {
                            const message = this.getStandardPaymentMethodMessage(this.formData.payment_method);
                            infoText.textContent = message;
                            infoContainer.style.display = 'block';

                            if (this.formData.payment_method === 'sepa_direct_debit') {
                                infoContainer.className = 'payment-info sepa-info';
                            } else {
                                infoContainer.className = 'payment-info standard-info';
                            }
                        }
                    }
                }
            }

            if (this.formData.fitness_goals) {
                const goals = this.formData.fitness_goals.split(',');
                goals.forEach(goal => {
                    const goalBtn = this.shadowRoot.querySelector(`[data-goal="${goal}"]`);
                    if (goalBtn) {
                        goalBtn.classList.add('active');
                    }
                });
            }

            this.log('Form data restored successfully');
        }

        async processFormData() {
            const nextBtn = this.shadowRoot.getElementById("next-button");

            try {
                if (nextBtn) {
                    nextBtn.disabled = true;
                    nextBtn.textContent = "Wird verarbeitet...";
                }

                const formData = this.collectFormData();

                const validation = this.validateFormData(formData);
                if (!validation.valid) {
                    this.showErrors(validation.errors);
                    return;
                }

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

            if (restartBtn) {
                restartBtn.addEventListener("click", async () => {
                    this.selectedPlan = null;
                    this.formData = {};
                    this.mollieWindow = null;
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
                // Trim string values when collecting form data
                data[key] = typeof value === 'string' ? value.trim() : value;
            }

            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                data[checkbox.name] = checkbox.checked;
            });

            const selectedGoals = this.shadowRoot.querySelectorAll('.goal-btn.active');
            if (selectedGoals.length > 0) {
                data.fitness_goals = Array.from(selectedGoals).map(btn => btn.dataset.goal).join(',');
            }

            const selectedPaymentMethod = this.shadowRoot.querySelector('input[name="payment_method"]:checked');
            if (selectedPaymentMethod) {
                data.payment_method = selectedPaymentMethod.value;
                data.payment_method_type = selectedPaymentMethod.getAttribute('data-method-type');
                data.requires_mandate = selectedPaymentMethod.getAttribute('data-requires-mandate') === 'true';
                data.requires_iban = selectedPaymentMethod.getAttribute('data-requires-iban') === 'true';
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

            if (!data.payment_method) {
                errors.push('Bitte wählen Sie eine Zahlungsmethode aus');
            }

            if (data.requires_iban) {
                if (!data.account_holder || data.account_holder.trim() === '') {
                    errors.push('Kontoinhaber ist erforderlich');
                }

                if (!data.iban || data.iban.trim() === '') {
                    errors.push('IBAN ist erforderlich');
                } else if (!this.isValidIban(data.iban)) {
                    errors.push('Bitte geben Sie eine gültige IBAN ein');
                }

                if (!data.sepa_mandate_acknowledged) {
                    errors.push('Bitte bestätigen Sie das SEPA-Lastschriftmandat');
                }
            }

            const selectedPaymentRadio = this.shadowRoot.querySelector(`input[name="payment_method"][value="${data.payment_method}"]`);
            const requiresMandate = selectedPaymentRadio?.getAttribute('data-requires-mandate') === 'true';
            const requiresIban = selectedPaymentRadio?.getAttribute('data-requires-iban') === 'true';

            if (requiresMandate && !requiresIban && !data.sepa_mandate_acknowledged) {
                errors.push('Bitte bestätigen Sie, dass Sie die SEPA-Lastschriftmandat-Informationen gelesen haben');
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
                account_holder: 'Kontoinhaber',
                iban: 'IBAN'
            };
            return labels[field] || field;
        }

        isValidEmail(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        }

        isValidIban(iban) {
            const cleanedIban = iban.replace(/\s/g, '').toUpperCase();

            if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(cleanedIban)) {
                return false;
            }

            if (cleanedIban.length < 15 || cleanedIban.length > 34) {
                return false;
            }

            const rearranged = cleanedIban.slice(4) + cleanedIban.slice(0, 4);

            let numericString = '';
            for (let i = 0; i < rearranged.length; i++) {
                const char = rearranged[i];
                if (/[A-Z]/.test(char)) {
                    numericString += (char.charCodeAt(0) - 55).toString();
                } else {
                    numericString += char;
                }
            }

            let remainder = 0;
            for (let i = 0; i < numericString.length; i++) {
                remainder = (remainder * 10 + parseInt(numericString[i])) % 97;
            }

            return remainder === 1;
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

            if (fieldName === 'iban' && value && !this.isValidIban(value)) {
                this.showFieldError(field, 'Bitte gib eine gültige IBAN ein');
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
                    // KEINE Authorization mit Bearer Token
                },
                // Wichtig: keine Credentials senden
                credentials: 'omit'
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

            if (context.includes('Payment initiation')) {
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
