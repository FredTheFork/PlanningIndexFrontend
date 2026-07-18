/**
 * Planning Index — Enterprise Multi-Step Checkout Wizard
 * Based on Per-Council Checkout but for Enterprise (Level 60)
 * Version: 1.0.0
 */
(function($) {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    const CONFIG = {
        totalSteps: parseInt(window.pmpeConfig?.totalSteps, 10) || 4,
        templates: window.pmpeConfig?.templates || {},
        checkoutUrl: window.pmpeConfig?.checkoutUrl || '',
        ajaxUrl: window.pmpeConfig?.ajaxUrl || '',
        nonce: window.pmpeConfig?.nonce || '',
        isLoggedIn: window.pmpeConfig?.isLoggedIn || false,
        enterprisePrice: parseFloat(window.pmpeConfig?.enterprisePrice) || 0,
        strings: {
            usernameRequired: window.pmpeConfig?.strings?.usernameRequired || 'Please enter a username.',
            passwordRequired: window.pmpeConfig?.strings?.passwordRequired || 'Please enter a password with at least 8 characters.',
            passwordMismatch: window.pmpeConfig?.strings?.passwordMismatch || 'Passwords do not match.',
            emailRequired: window.pmpeConfig?.strings?.emailRequired || 'Please enter a valid email address.',
            emailMismatch: window.pmpeConfig?.strings?.emailMismatch || 'Email addresses do not match.',
            processing: window.pmpeConfig?.strings?.processing || 'Processing your subscription...',
            continue: window.pmpeConfig?.strings?.continue || 'Continue',
            completeSubscription: window.pmpeConfig?.strings?.completeSubscription || 'Complete Enterprise Subscription',
            perMonth: window.pmpeConfig?.strings?.perMonth || '/month'
        }
    };

    // ============================================================
    // STATE MANAGEMENT
    // ============================================================
    const state = {
        currentStep: 1,
        selectedTemplate: 'professional',
        formData: {},
        validationErrors: {},
        isProcessing: false,
        isWizardMode: false
    };

    // ============================================================
    // INITIALIZATION
    // ============================================================
    function init() {
        state.isWizardMode = $('.pmpe-wizard-checkout').length > 0;
        
        if (state.isWizardMode) {
            initWizardMode();
        }
    }

    function initWizardMode() {
        const urlParams = new URLSearchParams(window.location.search);
        const stepParam = urlParams.get('step');
        state.currentStep = stepParam ? Math.max(1, Math.min(CONFIG.totalSteps, parseInt(stepParam, 10))) : 1;

        // Skip account step for logged-in users
        if (CONFIG.isLoggedIn && state.currentStep === 3) {
            state.currentStep = 4;
        }

        restoreState();

        initTemplateSelection();
        initBusinessToggle();
        initTeamToggle();
        initNavigation();
        initFormValidation();
        initFormPersistence();
        initKeyboardNavigation();

        updateStepDisplay();

        window.addEventListener('popstate', handlePopState);

        // Add smooth entrance animation
        setTimeout(() => {
            $('.pmpe-wizard-checkout').addClass('loaded');
        }, 100);

        console.log('[PMPE] Enterprise Wizard initialized', { step: state.currentStep, loggedIn: CONFIG.isLoggedIn });
    }

    // ============================================================
    // KEYBOARD NAVIGATION
    // ============================================================
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            if (e.key === 'Enter' && $(e.target).hasClass('pmpc-template-option')) {
                e.preventDefault();
                $(e.target).click();
            }
        });
    }

    // ============================================================
    // STATE PERSISTENCE
    // ============================================================
    function saveState() {
        try {
            const stateToSave = {
                selectedTemplate: state.selectedTemplate,
                formData: state.formData,
                currentStep: state.currentStep
            };
            sessionStorage.setItem('pmpe_checkout_state', JSON.stringify(stateToSave));
        } catch (e) {
            console.warn('[PMPE] Could not save state:', e);
        }
    }

    function restoreState() {
        try {
            const savedState = sessionStorage.getItem('pmpe_checkout_state');
            if (savedState) {
                const parsed = JSON.parse(savedState);
                state.selectedTemplate = parsed.selectedTemplate || 'professional';
                state.formData = parsed.formData || {};
            }
        } catch (e) {
            console.warn('[PMPE] Could not restore state:', e);
        }
    }

    function clearState() {
        try {
            sessionStorage.removeItem('pmpe_checkout_state');
        } catch (e) {}
    }

    // ============================================================
    // TEMPLATE SELECTION (Dynamic Loading with Live Preview)
    // ============================================================
    let loadedTemplates = null;
    let templateDummyData = null;

    function initTemplateSelection() {
        const $grid = $('#pmpe_template_grid');
        const $loading = $('#pmpe_template_loading');
        
        if ($grid.length && $loading.length) {
            loadTemplatesFromAPI();
        } else {
            initStaticTemplateSelection();
        }
    }

    function initStaticTemplateSelection() {
        const $templates = $('.pmpc-template-option');
        if (!$templates.length) return;

        $templates.removeClass('selected');
        $(`.pmpc-template-option[data-template="${state.selectedTemplate}"]`).addClass('selected');

        $templates.on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const template = $this.data('template');

            $templates.removeClass('selected');
            $this.addClass('selected');

            state.selectedTemplate = template;
            saveState();
            clearStepError(2);
        });
    }

    function loadTemplatesFromAPI() {
        const $grid = $('#pmpe_template_grid');
        const $loading = $('#pmpe_template_loading');
        const $previewContainer = $('#pmpe_template_preview_container');
        
        const restUrl = window.pmpeConfig?.restUrl || '/wp-json/pi/v1';
        
        $.ajax({
            url: restUrl + '/templates',
            method: 'GET',
            beforeSend: function(xhr) {
                const nonce = window.pmpeConfig?.restNonce;
                if (nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                }
            },
            success: function(response) {
                loadedTemplates = response.templates || {};
                templateDummyData = response.dummy_data || {};
                
                const userCurrent = window.pmpeConfig?.userCurrentTemplate || response.current || 'basic';
                if (!state.selectedTemplate || state.selectedTemplate === 'professional') {
                    state.selectedTemplate = userCurrent;
                }
                
                renderTemplateGrid(loadedTemplates);
                $loading.hide();
                $grid.show();
                $previewContainer.show();
                
                renderTemplatePreview(state.selectedTemplate);
                
                console.log('[PMPE] Templates loaded from API:', Object.keys(loadedTemplates));
            },
            error: function(xhr, status, error) {
                console.warn('[PMPE] Failed to load templates from API:', error);
                
                loadedTemplates = window.pmpeConfig?.templates || {};
                templateDummyData = {};
                
                if (Object.keys(loadedTemplates).length > 0) {
                    renderTemplateGrid(loadedTemplates);
                    $loading.hide();
                    $grid.show();
                } else {
                    $loading.html('<p class="pmpc-error">Unable to load templates.</p>');
                }
            }
        });
    }

    function renderTemplateGrid(templates) {
        const $grid = $('#pmpe_template_grid');
        $grid.empty();

        const iconMap = {
            basic: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
            professional: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            modern: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
            classic: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
            window: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></svg>',
            minimal: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'
        };

        Object.keys(templates).forEach(function(key) {
            const tmpl = templates[key];
            const isSelected = key === state.selectedTemplate;
            const icon = iconMap[key] || iconMap.basic;
            
            const $option = $(`
                <label class="pmpc-template-option ${isSelected ? 'selected' : ''}" data-template="${escapeHtml(key)}">
                    <div class="pmpc-template-card">
                        <div class="pmpc-template-icon">
                            ${icon}
                        </div>
                        <div class="pmpc-template-info">
                            <strong>${escapeHtml(tmpl.name || key)}</strong>
                            <span>${escapeHtml(tmpl.description || '')}</span>
                        </div>
                        <div class="pmpc-template-check">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </div>
                </label>
            `);
            
            $grid.append($option);
        });

        $('#pmpe_default_template').val(state.selectedTemplate);

        $grid.find('.pmpc-template-option').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const templateKey = $this.data('template');

            $grid.find('.pmpc-template-option').removeClass('selected');
            $this.addClass('selected');

            state.selectedTemplate = templateKey;
            $('#pmpe_default_template').val(templateKey);
            saveState();
            clearStepError(2);

            renderTemplatePreview(templateKey);

            $this.addClass('pulse-select');
            setTimeout(() => $this.removeClass('pulse-select'), 300);
        });
    }

    function renderTemplatePreview(templateKey) {
        const $preview = $('#pmpe_template_preview');
        const $previewName = $('#pmpe_preview_template_name');
        
        if (!$preview.length || !loadedTemplates) return;

        const template = loadedTemplates[templateKey];
        if (!template) return;

        $previewName.text(template.name || templateKey);

        if (template.html) {
            let html = template.html;
            
            if (templateDummyData) {
                Object.keys(templateDummyData).forEach(function(key) {
                    const regex = new RegExp('\\[' + key + '\\]', 'g');
                    html = html.replace(regex, escapeHtml(templateDummyData[key] || ''));
                });
            }
            
            $preview.html(`
                <div class="pmpc-preview-document-inner">
                    ${html}
                </div>
            `);
        } else {
            $preview.html(`
                <div class="pmpc-preview-placeholder">
                    <div class="pmpc-preview-mock">
                        <div class="pmpc-mock-header"></div>
                        <div class="pmpc-mock-line pmpc-mock-title"></div>
                        <div class="pmpc-mock-line"></div>
                        <div class="pmpc-mock-line pmpc-mock-short"></div>
                        <div class="pmpc-mock-block"></div>
                        <div class="pmpc-mock-line"></div>
                        <div class="pmpc-mock-line pmpc-mock-short"></div>
                    </div>
                    <p class="pmpc-preview-note">Full preview available after checkout in Settings</p>
                </div>
            `);
        }
    }

    // ============================================================
    // BUSINESS INFO TOGGLE
    // ============================================================
    function initBusinessToggle() {
        const $toggle = $('#pmpe_business_toggle');
        const $fields = $('#pmpe_business_fields');
        const $icon = $toggle.find('.pmpc-toggle-icon');

        if (!$toggle.length) return;

        $fields.hide();

        $toggle.on('click', function(e) {
            e.preventDefault();
            $fields.slideToggle(300);
            $icon.toggleClass('rotated');
        });
    }

    // ============================================================
    // TEAM SEATS TOGGLE
    // ============================================================
    function initTeamToggle() {
        const $toggle = $('#pmpe_team_toggle');
        const $fields = $('#pmpe_team_fields');
        const $icon = $toggle.find('.pmpc-toggle-icon');

        if (!$toggle.length) return;

        $fields.hide();

        $toggle.on('click', function(e) {
            e.preventDefault();
            $fields.slideToggle(300);
            $icon.toggleClass('open');
        });
    }

    // ============================================================
    // NAVIGATION
    // ============================================================
    function initNavigation() {
        $(document).on('click', '#pmpe_btn_next', handleNext);
        $(document).on('click', '#pmpe_btn_back', handleBack);
    }

    function handleNext(e) {
        e.preventDefault();
        if (state.isProcessing) return;

        if (!validateCurrentStep()) {
            console.log('[PMPE] Validation failed on step', state.currentStep);
            return;
        }

        syncDataForSubmission();

        state.isProcessing = true;
        const $btn = $('#pmpe_btn_next');
        $btn.prop('disabled', true).addClass('loading');
        $('#pmpro_processing_message').show();

        if (state.currentStep === CONFIG.totalSteps) {
            const form = document.getElementById('pmpro_form');
            if (!form.querySelector('input[name="javascriptok"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'javascriptok';
                input.value = '1';
                form.appendChild(input);
            }
            form.submit();
            return;
        }

        let nextStep = state.currentStep + 1;

        if (nextStep === 3 && CONFIG.isLoggedIn) {
            nextStep = 4;
        }

        goToStep(nextStep);
        state.isProcessing = false;
        $btn.prop('disabled', false).removeClass('loading');
        $('#pmpro_processing_message').hide();
    }

    function handleBack(e) {
        e.preventDefault();
        if (state.currentStep > 1) {
            let prevStep = state.currentStep - 1;
            if (prevStep === 3 && CONFIG.isLoggedIn) {
                prevStep = 2;
            }
            goToStep(prevStep);
        }
    }

    function goToStep(step) {
        step = Math.max(1, Math.min(CONFIG.totalSteps, step));
        state.currentStep = step;

        const url = new URL(window.location.href);
        url.searchParams.set('step', step);
        window.history.pushState({ step }, '', url);

        updateStepDisplay();
        saveState();
        scrollToWizard();
    }

    function handlePopState(event) {
        if (event.state && event.state.step) {
            state.currentStep = event.state.step;
            updateStepDisplay();
        }
    }

    function updateStepDisplay() {
        const $current = $('.pmpc-step-content:visible');
        const $next = $(`.pmpc-step-content[data-step="${state.currentStep}"]`);
        
        $current.fadeOut(200, function() {
            $next.fadeIn(300);
        });

        $('.pmpc-progress-step').each(function() {
            const stepNum = parseInt($(this).data('step'), 10);
            $(this).removeClass('active completed');
            if (stepNum === state.currentStep) {
                $(this).addClass('active');
            } else if (stepNum < state.currentStep) {
                $(this).addClass('completed');
            }
        });

        $('.pmpc-progress-line').each(function(index) {
            $(this).toggleClass('completed', index + 1 < state.currentStep);
        });

        const $backBtn = $('#pmpe_btn_back');
        const $nextText = $('#pmpe_btn_next_text');

        $backBtn.css('visibility', state.currentStep === 1 ? 'hidden' : 'visible');

        if (state.currentStep === CONFIG.totalSteps) {
            $nextText.text('Complete Enterprise Subscription');
        } else if (state.currentStep === 1) {
            $nextText.text('Continue to Templates');
        } else if (state.currentStep === 2) {
            $nextText.text(CONFIG.isLoggedIn ? 'Continue to Payment' : 'Continue to Account');
        } else {
            $nextText.text('Continue to Payment');
        }

        $('#pmpe_current_step').val(state.currentStep);
    }

    function scrollToWizard() {
        const $wizard = $('.pmpe-wizard-checkout');
        if ($wizard.length) {
            $('html, body').animate({
                scrollTop: $wizard.offset().top - 40
            }, 400, 'swing');
        }
    }

    // ============================================================
    // VALIDATION
    // ============================================================
    function initFormValidation() {
        $('input[required]').on('blur', function() {
            validateField($(this));
        });
    }

    function validateField($field) {
        const value = $field.val().trim();
        const type = $field.attr('type');
        
        $field.removeClass('error');
        $field.next('.error-message').remove();
        
        if (!value) {
            return false;
        }
        
        if (type === 'email' && !isValidEmail(value)) {
            showFieldError($field, 'Please enter a valid email address');
            return false;
        }
        
        return true;
    }

    function validateCurrentStep() {
        clearStepError(state.currentStep);

        switch (state.currentStep) {
            case 1: return true; // Enterprise step always passes
            case 2: return validateStep2();
            case 3: return validateStep3();
            case 4: return validateStep4();
            default: return true;
        }
    }

    function validateStep2() {
        const templateValue = $('#pmpe_default_template').val() || state.selectedTemplate;
        if (!templateValue || templateValue.trim() === '') {
            showStepError(2, 'Please select a default template');
            return false;
        }
        state.selectedTemplate = templateValue;
        return true;
    }

    function validateStep3() {
        if (CONFIG.isLoggedIn || $('.pmpc-logged-in-notice').length) {
            return true;
        }

        let valid = true;
        const $username = $('#username');
        const $password = $('#password');
        const $password2 = $('#password2');
        const $email = $('#bemail');
        const $confirmEmail = $('#bconfirmemail');

        $('.error-message').remove();
        $('input').removeClass('error');

        if (!$username.val().trim()) {
            showFieldError($username, 'Username is required');
            valid = false;
        }
        if ($password.val().length < 8) {
            showFieldError($password, 'Password must be at least 8 characters');
            valid = false;
        }
        if ($password.val() !== $password2.val()) {
            showFieldError($password2, 'Passwords do not match');
            valid = false;
        }
        if (!isValidEmail($email.val().trim())) {
            showFieldError($email, 'Valid email is required');
            valid = false;
        }
        if ($email.val().trim().toLowerCase() !== $confirmEmail.val().trim().toLowerCase()) {
            showFieldError($confirmEmail, 'Emails do not match');
            valid = false;
        }

        if (!valid) scrollToWizard();
        return valid;
    }

    function validateStep4() {
        if ($('.pmpc-billing-section').is(':visible')) {
            if (!$('#bfirstname').val().trim() || !$('#blastname').val().trim()) {
                showStepError(4, 'Please enter billing first and last name');
                return false;
            }
        }
        return true;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showStepError(step, msg) {
        const $error = $(`#pmpe_step${step}_error`);
        $error.text(msg).slideDown(200);
    }

    function clearStepError(step) {
        $(`#pmpe_step${step}_error`).slideUp(200).text('');
    }

    function showFieldError($field, msg) {
        $field.addClass('error');
        let $err = $field.next('.error-message');
        if (!$err.length) {
            $err = $('<span class="error-message"></span>').insertAfter($field);
        }
        $err.text(msg).show();
    }

    // ============================================================
    // FORM PERSISTENCE & FINAL SYNC
    // ============================================================
    function initFormPersistence() {
        const excluded = ['password', 'password2', 'CVV', 'AccountNumber', 'ExpirationMonth', 'ExpirationYear'];

        $('input, textarea, select').not(excluded.map(n => `[name="${n}"]`).join(',')).on('change input', function() {
            const name = $(this).attr('name');
            if (name && !excluded.includes(name)) {
                state.formData[name] = $(this).val();
                saveState();
            }
        });

        Object.keys(state.formData).forEach(name => {
            const $el = $(`[name="${name}"]`);
            if ($el.length && !excluded.includes(name)) {
                $el.val(state.formData[name]);
            }
        });
    }

    function syncDataForSubmission() {
        $('#pmpe_default_template').val(state.selectedTemplate);
        
        // SAVE TEAM SEATS
        const seats = $('input[name="pmpe_team_seats"]:checked').val() || '1';
        state.formData.pmpe_team_seats = seats;

        console.log('[PMPE] Data synced', {
            template: state.selectedTemplate,
            seats: seats
        });
    }

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return unsafe;
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // ============================================================
    // GLOBAL BINDINGS
    // ============================================================
    $(document).on('submit', '#pmpro_form', function(e) {
        syncDataForSubmission();
    });

    $(document).on('click', '[data-gateway], .pmpro_btn_checkout, #pmpro_paypalexpress', function() {
        syncDataForSubmission();
    });

    // ============================================================
    // CSS INJECTION FOR DYNAMIC STYLES
    // ============================================================
    function injectDynamicStyles() {
        const styles = `
            <style>
                #pmpe_selected_count.count-change {
                    animation: countBounce 0.2s ease-out;
                }
                
                @keyframes countBounce {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.3); }
                    100% { transform: scale(1); }
                }
                
                .pmpe-wizard-checkout {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.5s ease, transform 0.5s ease;
                }
                
                .pmpe-wizard-checkout.loaded {
                    opacity: 1;
                    transform: translateY(0);
                }
                
                .pulse-select {
                    animation: pulseSelect 0.3s ease-out;
                }
                
                @keyframes pulseSelect {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
            </style>
        `;
        $('head').append(styles);
    }

    // ============================================================
    // START
    // ============================================================
    $(document).ready(function() {
        injectDynamicStyles();
        init();
    });

})(jQuery);
