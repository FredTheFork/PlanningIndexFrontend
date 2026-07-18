/**
 * Planning Index — Multi-Step Trial Checkout Wizard
 * Premium, industry-grade free trial checkout experience
 * Version: 2.0.0
 */
(function($) {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    const CONFIG = {
        maxSelection: parseInt(window.pmpcTrialConfig?.maxSelection || 5, 10),
        minSelection: parseInt(window.pmpcTrialConfig?.minSelection || 1, 10),
        totalSteps: parseInt(window.pmpcTrialConfig?.totalSteps || 4, 10),
        trialDays: parseInt(window.pmpcTrialConfig?.trialDays || 14, 10),
        councils: window.pmpcTrialConfig?.councils || [],
        templates: window.pmpcTrialConfig?.templates || {},
        checkoutUrl: window.pmpcTrialConfig?.checkoutUrl || '',
        ajaxUrl: window.pmpcTrialConfig?.ajaxUrl || '',
        nonce: window.pmpcTrialConfig?.nonce || '',
        restUrl: window.pmpcTrialConfig?.restUrl || '/wp-json/pi/v1',
        restNonce: window.pmpcTrialConfig?.restNonce || '',
        isLoggedIn: !!window.pmpcTrialConfig?.isLoggedIn,
        userCurrentTemplate: window.pmpcTrialConfig?.userCurrentTemplate || 'professional',
        strings: {
            selectMinCouncils: window.pmpcTrialConfig?.strings?.selectMinCouncils || 'Please select at least 1 council to continue.',
            maxCouncils: window.pmpcTrialConfig?.strings?.maxCouncils || 'Maximum 5 councils during your free trial.',
            usernameRequired: window.pmpcTrialConfig?.strings?.usernameRequired || 'Please enter a username.',
            passwordRequired: window.pmpcTrialConfig?.strings?.passwordRequired || 'Please enter a password with at least 8 characters.',
            passwordMismatch: window.pmpcTrialConfig?.strings?.passwordMismatch || 'Passwords do not match.',
            emailRequired: window.pmpcTrialConfig?.strings?.emailRequired || 'Please enter a valid email address.',
            emailMismatch: window.pmpcTrialConfig?.strings?.emailMismatch || 'Email addresses do not match.',
            processing: window.pmpcTrialConfig?.strings?.processing || 'Starting your free trial...',
            continue: window.pmpcTrialConfig?.strings?.continue || 'Continue',
            startTrial: window.pmpcTrialConfig?.strings?.startTrial || 'Start 2 Week Free Trial',
            templateLoadError: window.pmpcTrialConfig?.strings?.templateLoadError || 'Unable to load templates. Using defaults.'
        }
    };

    // ============================================================
    // STATE MANAGEMENT
    // ============================================================
    const state = {
        currentStep: 1,
        selectedCouncils: [],
        selectedTemplate: CONFIG.userCurrentTemplate,
        formData: {},
        validationErrors: {},
        isProcessing: false,
        isWizardMode: false
    };

    // ============================================================
    // INITIALIZATION
    // ============================================================
    function init() {
        state.isWizardMode = $('.pmpc-wizard-checkout').length > 0 || $('.pmpc-trial-checkout').length > 0;
        
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

        initCouncilGrid();
        initCouncilSearch();
        initTemplateSelection();
        initBusinessToggle();
        initNavigation();
        initFormValidation();
        initFormPersistence();
        initKeyboardNavigation();

        updateStepDisplay();
        updateSelectedTags();
        updateCouncilCount();
        updateOrderSummary();

        window.addEventListener('popstate', handlePopState);

        // Add smooth entrance animation
        setTimeout(() => {
            $('.pmpc-wizard-checkout, .pmpc-trial-checkout').addClass('loaded');
        }, 100);

        injectDynamicStyles();

        console.log('[PMPC Trial] Wizard initialized', { step: state.currentStep, loggedIn: CONFIG.isLoggedIn });
    }

    // ============================================================
    // KEYBOARD NAVIGATION
    // ============================================================
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            if (e.key === 'Enter' && $(e.target).hasClass('pmpc-council-item')) {
                e.preventDefault();
                $(e.target).click();
            }
        });

        $('.pmpc-council-item').attr('tabindex', '0');
    }

    // ============================================================
    // STATE PERSISTENCE
    // ============================================================
    function saveState() {
        try {
            const stateToSave = {
                selectedCouncils: state.selectedCouncils,
                selectedTemplate: state.selectedTemplate,
                formData: state.formData,
                currentStep: state.currentStep
            };
            sessionStorage.setItem('pmpc_trial_checkout_state', JSON.stringify(stateToSave));
        } catch (e) {
            console.warn('[PMPC Trial] Could not save state:', e);
        }
    }

    function restoreState() {
        try {
            const savedState = sessionStorage.getItem('pmpc_trial_checkout_state');
            if (savedState) {
                const parsed = JSON.parse(savedState);
                state.selectedCouncils = parsed.selectedCouncils || [];
                state.selectedTemplate = parsed.selectedTemplate || CONFIG.userCurrentTemplate;
                state.formData = parsed.formData || {};
            }
        } catch (e) {
            console.warn('[PMPC Trial] Could not restore state:', e);
        }
    }

    function clearState() {
        try {
            sessionStorage.removeItem('pmpc_trial_checkout_state');
        } catch (e) {}
    }

    // ============================================================
    // COUNCIL GRID
    // ============================================================
    function initCouncilGrid() {
        const $grid = $('#pmpc_council_grid');
        const $hiddenSelect = $('#pmpc_councils');

        if (!$grid.length) return;

        $grid.empty();
        $hiddenSelect.empty();

        const sortedCouncils = [...CONFIG.councils].sort((a, b) => a.localeCompare(b));

        sortedCouncils.forEach((council, index) => {
            const isSelected = state.selectedCouncils.includes(council);
            const $item = $(`
                <div class="pmpc-council-item ${isSelected ? 'selected' : ''}" data-council="${escapeHtml(council)}" tabindex="0" style="animation-delay: ${index * 0.01}s">
                    <span class="pmpc-council-check"></span>
                    <span class="pmpc-council-name">${escapeHtml(council)}</span>
                </div>
            `);
            $grid.append($item);

            $hiddenSelect.append(`<option value="${escapeHtml(council)}" ${isSelected ? 'selected' : ''}>${escapeHtml(council)}</option>`);
        });

        $grid.on('click keypress', '.pmpc-council-item', function(e) {
            if (e.type === 'keypress' && e.key !== 'Enter') return;
            e.preventDefault();
            const council = $(this).data('council');
            toggleCouncil(council, $(this));
        });

        updateCouncilCount();
    }

    function toggleCouncil(council, $element) {
        const index = state.selectedCouncils.indexOf(council);

        if (index === -1) {
            // Check max selection
            if (state.selectedCouncils.length >= CONFIG.maxSelection) {
                showStepError(1, CONFIG.strings.maxCouncils);
                $element.addClass('shake');
                setTimeout(() => $element.removeClass('shake'), 500);
                return;
            }

            state.selectedCouncils.push(council);
            $element.addClass('selected');
            $(`#pmpc_councils option[value="${council}"]`).prop('selected', true);
            
            // Add selection animation
            $element.addClass('just-selected');
            setTimeout(() => $element.removeClass('just-selected'), 400);
        } else {
            state.selectedCouncils.splice(index, 1);
            $element.removeClass('selected');
            $(`#pmpc_councils option[value="${council}"]`).prop('selected', false);
        }

        updateCouncilCount();
        updateSelectedTags();
        updateOrderSummary();
        saveState();
        clearStepError(1);
    }

    function updateCouncilCount() {
        const count = state.selectedCouncils.length;
        const $countEl = $('#pmpc_selected_count');
        
        // Animate the count change
        $countEl.addClass('count-change');
        $countEl.text(count);
        setTimeout(() => $countEl.removeClass('count-change'), 200);
        
        const $counter = $('.pmpc-selection-counter');
        if (count >= CONFIG.minSelection && count <= CONFIG.maxSelection) {
            $counter.addClass('valid');
        } else {
            $counter.removeClass('valid');
        }
    }

    function updateSelectedTags() {
        const $container = $('#pmpc_selected_tags');
        const count = state.selectedCouncils.length;

        if (count === 0) {
            $container.html('<p class="pmpc-empty-selection">Click on councils above to select them</p>');
            return;
        }

        let html = '';
        state.selectedCouncils.forEach(council => {
            html += `
                <span class="pmpc-tag" data-council="${escapeHtml(council)}">
                    ${escapeHtml(council)}
                    <button type="button" class="pmpc-tag-remove" aria-label="Remove ${escapeHtml(council)}">&times;</button>
                </span>
            `;
        });
        $container.html(html);

        $container.find('.pmpc-tag-remove').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const council = $(this).parent().data('council');
            const $gridItem = $(`.pmpc-council-item[data-council="${council}"]`);
            toggleCouncil(council, $gridItem);
        });
    }

    // ============================================================
    // COUNCIL SEARCH
    // ============================================================
    function initCouncilSearch() {
        const $search = $('#pmpc_council_search');
        const $grid = $('#pmpc_council_grid');
        const $clearBtn = $('#pmpc_search_clear');

        if (!$search.length) return;

        let searchTimeout;
        $search.on('input', function() {
            clearTimeout(searchTimeout);
            const term = $(this).val().toLowerCase().trim();
            
            if ($clearBtn.length) {
                $clearBtn.toggle(term.length > 0);
            }

            searchTimeout = setTimeout(() => {
                filterCouncils(term);
            }, 100);
        });

        if ($clearBtn.length) {
            $clearBtn.on('click', function() {
                $search.val('').trigger('input');
                $search.focus();
            });
        }

        $search.on('keydown', function(e) {
            if (e.key === 'Escape') {
                $(this).val('').trigger('input');
            }
        });
    }

    function filterCouncils(term) {
        const $grid = $('#pmpc_council_grid');
        const $items = $grid.find('.pmpc-council-item');
        let visibleCount = 0;

        $items.each(function() {
            const name = ($(this).data('council') || $(this).text()).toString().toLowerCase();
            const matches = term === '' || name.includes(term);
            $(this).toggle(matches);
            if (matches) visibleCount++;
        });

        let $noResults = $grid.find('.pmpc-no-results');
        if (visibleCount === 0 && term !== '') {
            if (!$noResults.length) {
                $grid.append('<div class="pmpc-no-results">No councils found matching "<strong>' + escapeHtml(term) + '</strong>"</div>');
            }
        } else {
            $noResults.remove();
        }
    }

    // ============================================================
    // ORDER SUMMARY
    // ============================================================
    function updateOrderSummary() {
        const count = state.selectedCouncils.length;
        
        $('#pmpc_summary_councils').text(`${count} council${count !== 1 ? 's' : ''}`);
    }

    // ============================================================
    // TEMPLATE SELECTION
    // ============================================================
    let loadedTemplates = null;
    let templateDummyData = null;

    function initTemplateSelection() {
        const $grid = $('#pmpc_template_grid');
        const $loading = $('#pmpc_template_loading');
        
        if ($grid.length && $loading.length) {
            loadTemplatesFromAPI();
        }
    }

    function loadTemplatesFromAPI() {
        const $grid = $('#pmpc_template_grid');
        const $loading = $('#pmpc_template_loading');
        const $previewContainer = $('#pmpc_template_preview_container');
        
        $.ajax({
            url: CONFIG.restUrl + '/templates',
            method: 'GET',
            beforeSend: function(xhr) {
                if (CONFIG.restNonce) {
                    xhr.setRequestHeader('X-WP-Nonce', CONFIG.restNonce);
                }
            },
            success: function(response) {
                loadedTemplates = response.templates || {};
                templateDummyData = response.dummy_data || {};
                
                const userCurrent = CONFIG.userCurrentTemplate || response.current || 'professional';
                if (!state.selectedTemplate) {
                    state.selectedTemplate = userCurrent;
                }
                
                renderTemplateGrid(loadedTemplates);
                $loading.hide();
                $grid.show();
                $previewContainer.show();
                
                renderTemplatePreview(state.selectedTemplate);
            },
            error: function() {
                // Fallback to config templates
                loadedTemplates = CONFIG.templates;
                
                if (Object.keys(loadedTemplates).length > 0) {
                    renderTemplateGrid(loadedTemplates);
                    $loading.hide();
                    $grid.show();
                } else {
                    $loading.html('<p class="pmpc-error">' + CONFIG.strings.templateLoadError + '</p>');
                }
            }
        });
    }

    function renderTemplateGrid(templates) {
        const $grid = $('#pmpc_template_grid');
        $grid.empty();

        const iconMap = {
            basic: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
            professional: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
            modern: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
            classic: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
            minimal: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'
        };

        Object.keys(templates).forEach(function(key) {
            const tmpl = templates[key];
            const isSelected = key === state.selectedTemplate;
            const icon = iconMap[key] || iconMap.professional;
            
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

        $('#pmpc_default_template').val(state.selectedTemplate);

        $grid.find('.pmpc-template-option').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const templateKey = $this.data('template');

            $grid.find('.pmpc-template-option').removeClass('selected');
            $this.addClass('selected');

            state.selectedTemplate = templateKey;
            $('#pmpc_default_template').val(templateKey);
            saveState();
            clearStepError(2);

            renderTemplatePreview(templateKey);

            $this.addClass('pulse-select');
            setTimeout(() => $this.removeClass('pulse-select'), 300);
        });
    }

    function renderTemplatePreview(templateKey) {
        const $preview = $('#pmpc_template_preview');
        const $previewName = $('#pmpc_preview_template_name');
        
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
            
            $preview.html(`<div class="pmpc-preview-document-inner">${html}</div>`);
        } else {
            $preview.html(`
                <div class="pmpc-preview-placeholder">
                    <div class="pmpc-preview-mock">
                        <div class="pmpc-mock-header"></div>
                        <div class="pmpc-mock-line pmpc-mock-title"></div>
                        <div class="pmpc-mock-line"></div>
                        <div class="pmpc-mock-line pmpc-mock-short"></div>
                    </div>
                    <p class="pmpc-preview-note">Full preview available after signup in Settings</p>
                </div>
            `);
        }
    }

    // ============================================================
    // BUSINESS INFO TOGGLE
    // ============================================================
    function initBusinessToggle() {
        const $toggle = $('#pmpc_business_toggle');
        const $fields = $('#pmpc_business_fields');
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
    // NAVIGATION
    // ============================================================
    function initNavigation() {
        $(document).on('click', '#pmpc_btn_next', handleNext);
        $(document).on('click', '#pmpc_btn_back', handleBack);
    }

    function handleNext(e) {
        e.preventDefault();
        if (state.isProcessing) return;

        if (!validateCurrentStep()) {
            return;
        }

        syncDataForSubmission();

        state.isProcessing = true;
        const $btn = $('#pmpc_btn_next');
        $btn.prop('disabled', true).addClass('loading');
        $('#pmpro_processing_message').show();

        if (state.currentStep === CONFIG.totalSteps) {
            // Final submit
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

        // Skip account creation if already logged in
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
        // Fade out current, fade in new
        const $current = $('.pmpc-step-content:visible');
        const $next = $(`.pmpc-step-content[data-step="${state.currentStep}"]`);
        
        $current.fadeOut(200, function() {
            $next.fadeIn(300);
        });

        // Update progress indicators
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

        // Update buttons
        const $backBtn = $('#pmpc_btn_back');
        const $nextText = $('#pmpc_btn_next_text');

        $backBtn.css('visibility', state.currentStep === 1 ? 'hidden' : 'visible');

        if (state.currentStep === CONFIG.totalSteps) {
            $nextText.text(CONFIG.strings.startTrial);
        } else if (state.currentStep === 1) {
            $nextText.text('Continue to Templates');
        } else if (state.currentStep === 2) {
            $nextText.text(CONFIG.isLoggedIn ? 'Continue to Confirmation' : 'Continue to Account');
        } else {
            $nextText.text('Continue to Confirmation');
        }

        $('#pmpc_current_step').val(state.currentStep);
    }

    function scrollToWizard() {
        const $wizard = $('.pmpc-wizard-checkout, .pmpc-trial-checkout');
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
            case 1: return validateStep1();
            case 2: return validateStep2();
            case 3: return validateStep3();
            case 4: return validateStep4();
            default: return true;
        }
    }

    function validateStep1() {
        if (state.selectedCouncils.length < CONFIG.minSelection) {
            showStepError(1, CONFIG.strings.selectMinCouncils);
            $('#pmpc_council_grid').addClass('validation-error');
            setTimeout(() => $('#pmpc_council_grid').removeClass('validation-error'), 2000);
            scrollToWizard();
            return false;
        }
        if (state.selectedCouncils.length > CONFIG.maxSelection) {
            showStepError(1, CONFIG.strings.maxCouncils);
            return false;
        }
        return true;
    }

    function validateStep2() {
        const templateValue = $('#pmpc_default_template').val() || state.selectedTemplate;
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
        // No payment validation needed for trial
        return true;
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showStepError(step, msg) {
        const $error = $(`#pmpc_step${step}_error`);
        $error.text(msg).slideDown(200);
    }

    function clearStepError(step) {
        $(`#pmpc_step${step}_error`).slideUp(200).text('');
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
        const excluded = ['password', 'password2'];

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
        // Sync councils
        const $select = $('#pmpc_councils');
        $select.find('option').prop('selected', false);
        state.selectedCouncils.forEach(c => {
            $select.find(`option[value="${escapeHtml(c)}"]`).prop('selected', true);
        });

        // Sync price (always 0 for trial)
        $('#pmpc_calculated_price').val('0.00');

        // Sync template
        $('#pmpc_default_template').val(state.selectedTemplate);

        console.log('[PMPC Trial] Data synced', {
            councils: state.selectedCouncils.length,
            template: state.selectedTemplate
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

    // ============================================================
    // CSS INJECTION FOR DYNAMIC STYLES
    // ============================================================
    function injectDynamicStyles() {
        const styles = `
            <style>
                .pmpc-council-item.just-selected {
                    animation: selectPulse 0.4s ease-out;
                }
                
                .pmpc-council-item.shake {
                    animation: shake 0.5s ease-out;
                }
                
                @keyframes selectPulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
                
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-5px); }
                    40%, 80% { transform: translateX(5px); }
                }
                
                #pmpc_selected_count.count-change {
                    animation: countBounce 0.2s ease-out;
                }
                
                @keyframes countBounce {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.3); }
                    100% { transform: scale(1); }
                }
                
                #pmpc_council_grid.validation-error {
                    animation: shake 0.5s ease-out;
                    border-color: #ef4444 !important;
                }
                
                .pmpc-wizard-checkout,
                .pmpc-trial-checkout {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.5s ease, transform 0.5s ease;
                }
                
                .pmpc-wizard-checkout.loaded,
                .pmpc-trial-checkout.loaded {
                    opacity: 1;
                    transform: translateY(0);
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
