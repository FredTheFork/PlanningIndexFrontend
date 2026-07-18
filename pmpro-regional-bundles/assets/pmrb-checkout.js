/**
 * Planning Index — Regional Bundles Multi-Step Checkout Wizard
 * Premium, industry-grade checkout experience
 * Version: 3.0.0 - Mirrored from Per-Council System
 */
(function($) {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    const CONFIG = {
        regions: window.pmrbConfig?.regions || {},
        prices: window.pmrbConfig?.prices || {},
        templates: window.pmrbConfig?.templates || {},
        totalSteps: parseInt(window.pmrbConfig?.totalSteps, 10) || 4,
        checkoutUrl: window.pmrbConfig?.checkoutUrl || '',
        ajaxUrl: window.pmrbConfig?.ajaxUrl || '',
        restUrl: window.pmrbConfig?.restUrl || '',
        restNonce: window.pmrbConfig?.restNonce || '',
        nonce: window.pmrbConfig?.nonce || '',
        isLoggedIn: window.pmrbConfig?.isLoggedIn || false,
        strings: {
            selectRegion: window.pmrbConfig?.strings?.selectRegion || 'Please select a regional bundle to continue.',
            usernameRequired: window.pmrbConfig?.strings?.usernameRequired || 'Please enter a username.',
            passwordRequired: window.pmrbConfig?.strings?.passwordRequired || 'Please enter a password with at least 8 characters.',
            passwordMismatch: window.pmrbConfig?.strings?.passwordMismatch || 'Passwords do not match.',
            emailRequired: window.pmrbConfig?.strings?.emailRequired || 'Please enter a valid email address.',
            emailMismatch: window.pmrbConfig?.strings?.emailMismatch || 'Email addresses do not match.',
            processing: window.pmrbConfig?.strings?.processing || 'Processing your subscription...',
            continue: window.pmrbConfig?.strings?.continue || 'Continue',
            completeSubscription: window.pmrbConfig?.strings?.completeSubscription || 'Complete Subscription',
            perMonth: window.pmrbConfig?.strings?.perMonth || '/month'
        }
    };

    // ============================================================
    // STATE MANAGEMENT
    // ============================================================
    const state = {
        currentStep: 1,
        selectedRegion: '',
        calculatedPrice: 0,
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
        state.isWizardMode = $('.pmrb-wizard-checkout').length > 0;
        
        if (state.isWizardMode) {
            initWizardMode();
        } else if ($('#pmrb_region_bundle').length) {
            initFallbackMode();
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

        initRegionGrid();
        initRegionSearch();
        initTemplateSelection();
        initBusinessToggle();
        initNavigation();
        initFormValidation();
        initFormPersistence();
        initKeyboardNavigation();

        updateStepDisplay();
        updatePriceDisplay();
        updateOrderSummary();
        updateSelectedRegionDisplay();

        window.addEventListener('popstate', handlePopState);

        // Add smooth entrance animation
        setTimeout(() => {
            $('.pmrb-wizard-checkout').addClass('loaded');
        }, 100);

        console.log('[PMRB] Premium Wizard initialized', { step: state.currentStep, loggedIn: CONFIG.isLoggedIn });
    }

    // ============================================================
    // KEYBOARD NAVIGATION
    // ============================================================
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            if (e.key === 'Enter' && $(e.target).hasClass('pmrb-region-item')) {
                e.preventDefault();
                $(e.target).click();
            }
        });

        $('.pmrb-region-item').attr('tabindex', '0');
    }

    // ============================================================
    // FALLBACK MODE
    // ============================================================
    function initFallbackMode() {
        restoreState();
        initRegionGrid();
        initRegionSearch();
        updatePriceDisplay();
    }

    // ============================================================
    // STATE PERSISTENCE
    // ============================================================
    function saveState() {
        try {
            const stateToSave = {
                selectedRegion: state.selectedRegion,
                calculatedPrice: state.calculatedPrice,
                selectedTemplate: state.selectedTemplate,
                formData: state.formData,
                currentStep: state.currentStep
            };
            sessionStorage.setItem('pmrb_checkout_state', JSON.stringify(stateToSave));
            sessionStorage.setItem('pmrb_region_selected', state.selectedRegion);
            sessionStorage.setItem('pmrb_calculated_price', state.calculatedPrice.toFixed(2));
        } catch (e) {
            console.warn('[PMRB] Could not save state:', e);
        }
    }

    function restoreState() {
        try {
            const savedState = sessionStorage.getItem('pmrb_checkout_state');
            if (savedState) {
                const parsed = JSON.parse(savedState);
                state.selectedRegion = parsed.selectedRegion || '';
                state.calculatedPrice = parsed.calculatedPrice || 0;
                state.selectedTemplate = parsed.selectedTemplate || 'professional';
                state.formData = parsed.formData || {};
            }

            // Also check hidden input
            const hiddenRegion = $('#pmrb_region_bundle').val();
            if (hiddenRegion && !state.selectedRegion) {
                state.selectedRegion = hiddenRegion;
                if (CONFIG.prices[hiddenRegion]) {
                    state.calculatedPrice = CONFIG.prices[hiddenRegion];
                }
            }
        } catch (e) {
            console.warn('[PMRB] Could not restore state:', e);
        }
    }

    function clearState() {
        try {
            sessionStorage.removeItem('pmrb_checkout_state');
            sessionStorage.removeItem('pmrb_region_selected');
            sessionStorage.removeItem('pmrb_calculated_price');
        } catch (e) {}
    }

    // ============================================================
    // REGION GRID
    // ============================================================
    function initRegionGrid() {
        const $grid = $('#pmrb_region_grid');
        if (!$grid.length) return;

        // Restore selection
        if (state.selectedRegion) {
            $grid.find(`.pmrb-region-item[data-region="${state.selectedRegion}"]`).addClass('selected');
        }

        // Handle region selection
        $grid.on('click keypress', '.pmrb-region-item', function(e) {
            if (e.type === 'keypress' && e.key !== 'Enter') return;
            
            // Don't select if clicking the toggle button
            if ($(e.target).closest('.pmrb-toggle').length) return;
            
            e.preventDefault();
            const region = $(this).data('region');
            selectRegion(region, $(this));
        });

        // Handle toggle councils
        $grid.on('click', '.pmrb-toggle', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            const $btn = $(this);
            const $regionItem = $btn.closest('.pmrb-region-item');
            const $councilList = $regionItem.find('.pmrb-council-list');
            const isOpen = $councilList.is(':visible');

            // Close all others
            $('.pmrb-council-list').not($councilList).slideUp(150);
            $('.pmrb-region-item').not($regionItem).removeClass('open');
            $('.pmrb-toggle').not($btn).removeClass('open').html(`
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                View Councils
            `);

            if (isOpen) {
                $councilList.slideUp(150);
                $btn.removeClass('open').html(`
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    View Councils
                `);
                $regionItem.removeClass('open');
            } else {
                $councilList.slideDown(150);
                $btn.addClass('open').html(`
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                    Hide Councils
                `);
                $regionItem.addClass('open');
            }
        });
    }

    function selectRegion(region, $element) {
        // Deselect previous
        $('.pmrb-region-item').removeClass('selected');
        
        // Select new
        $element.addClass('selected');
        state.selectedRegion = region;
        
        // Get price
        const price = CONFIG.prices[region] || 0;
        state.calculatedPrice = price;

        // Update hidden inputs
        $('#pmrb_region_bundle').val(region);
        $('#pmrb_calculated_price').val(price.toFixed(2));

        // Add selection animation
        $element.addClass('just-selected');
        setTimeout(() => $element.removeClass('just-selected'), 400);

        updatePriceDisplay();
        updateOrderSummary();
        updateSelectedRegionDisplay();
        saveState();
        clearStepError(1);

        console.log('[PMRB] Region selected:', region, 'Price:', price);
    }

    function updateSelectedRegionDisplay() {
        const $container = $('#pmrb_selected_region_display');
        const $summary = $('.pmrb-selected-summary');
        const $counter = $('.pmrb-selection-counter');

        if (!state.selectedRegion) {
            $container.html('<p class="pmrb-empty-selection">Click on a region above to select it</p>');
            $summary.removeClass('visible');
            $counter.removeClass('valid').find('strong').text('0');
            return;
        }

        $container.html(`
            <span class="pmrb-tag" data-region="${escapeHtml(state.selectedRegion)}">
                ${escapeHtml(state.selectedRegion)}
            </span>
        `);
        $summary.addClass('visible');
        $counter.addClass('valid').find('strong').text('1');
    }

    // ============================================================
    // REGION SEARCH
    // ============================================================
    function initRegionSearch() {
        const $search = $('#pmrb_region_search');
        const $grid = $('#pmrb_region_grid');
        const $clearBtn = $('#pmrb_search_clear');

        if (!$search.length) return;

        let searchTimeout;
        $search.on('input', function() {
            clearTimeout(searchTimeout);
            const term = $(this).val().toLowerCase().trim();
            
            if ($clearBtn.length) {
                $clearBtn.toggle(term.length > 0);
            }

            searchTimeout = setTimeout(() => {
                filterRegions(term);
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

    function filterRegions(term) {
        const $grid = $('#pmrb_region_grid');
        const $items = $grid.find('.pmrb-region-item');
        let visibleCount = 0;

        $items.each(function() {
            const $item = $(this);
            const regionName = $item.find('.pmrb-region-name').text().toLowerCase();
            const councilNames = $item.find('.pmrb-council-item').map(function() {
                return $(this).text().toLowerCase();
            }).get();

            const matchRegion = regionName.includes(term);
            const matchCouncil = councilNames.some(c => c.includes(term));

            const matches = term === '' || matchRegion || matchCouncil;
            $item.toggle(matches);
            if (matches) visibleCount++;
        });

        let $noResults = $grid.find('.pmrb-no-results');
        if (visibleCount === 0 && term !== '') {
            if (!$noResults.length) {
                $grid.append('<div class="pmrb-no-results">No regions found matching "<strong>' + escapeHtml(term) + '</strong>"</div>');
            }
        } else {
            $noResults.remove();
        }
    }

    // ============================================================
    // PRICE CALCULATIONS
    // ============================================================
    function updatePriceDisplay() {
        const $priceDisplay = $('#pmrb_price_display');
        const newPrice = `£${state.calculatedPrice.toFixed(2)}`;
        
        // Add animation class
        $priceDisplay.addClass('price-update');
        $priceDisplay.text(newPrice);
        setTimeout(() => $priceDisplay.removeClass('price-update'), 300);
        
        $('#pmrb_price_breakdown').text(state.selectedRegion || 'Select a region');
        $('#pmrb_calculated_price').val(state.calculatedPrice.toFixed(2));
        saveState();
    }

    function updateOrderSummary() {
        $('#pmrb_summary_region').text(state.selectedRegion || 'Not selected');
        $('#pmrb_summary_price').text(`£${state.calculatedPrice.toFixed(2)}/month`);
        $('#pmrb_summary_total').text(`£${state.calculatedPrice.toFixed(2)}`);
    }

    // ============================================================
    // TEMPLATE SELECTION (Dynamic Loading with Live Preview)
    // ============================================================
    let loadedTemplates = null;
    let templateDummyData = null;

    function initTemplateSelection() {
        const $grid = $('#pmrb_template_grid');
        const $loading = $('#pmrb_template_loading');
        
        if ($grid.length && $loading.length) {
            loadTemplatesFromAPI();
        } else {
            initStaticTemplateSelection();
        }
    }

    function initStaticTemplateSelection() {
        const $templates = $('.pmrb-template-option');
        if (!$templates.length) return;

        $templates.removeClass('selected');
        $(`.pmrb-template-option[data-template="${state.selectedTemplate}"]`).addClass('selected');

        $templates.on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const template = $this.data('template');

            $templates.removeClass('selected');
            $this.addClass('selected');

            state.selectedTemplate = template;
            $('#pmrb_default_template').val(template);
            saveState();
            clearStepError(2);
        });
    }

    function loadTemplatesFromAPI() {
        const $grid = $('#pmrb_template_grid');
        const $loading = $('#pmrb_template_loading');
        const $previewContainer = $('#pmrb_template_preview_container');
        
        const restUrl = CONFIG.restUrl || '/wp-json/pi/v1';
        
        $.ajax({
            url: restUrl + '/templates',
            method: 'GET',
            beforeSend: function(xhr) {
                if (CONFIG.restNonce) {
                    xhr.setRequestHeader('X-WP-Nonce', CONFIG.restNonce);
                }
            },
            success: function(response) {
                loadedTemplates = response.templates || {};
                templateDummyData = response.dummy_data || {};
                
                const userCurrent = window.pmrbConfig?.userCurrentTemplate || response.current || 'basic';
                if (!state.selectedTemplate || state.selectedTemplate === 'professional') {
                    state.selectedTemplate = userCurrent;
                }
                
                renderTemplateGrid(loadedTemplates);
                $loading.hide();
                $grid.show();
                $previewContainer.show();
                
                renderTemplatePreview(state.selectedTemplate);
                
                console.log('[PMRB] Templates loaded from API:', Object.keys(loadedTemplates));
            },
            error: function(xhr, status, error) {
                console.warn('[PMRB] Failed to load templates from API:', error);
                
                loadedTemplates = CONFIG.templates || {};
                templateDummyData = {};
                
                if (Object.keys(loadedTemplates).length > 0) {
                    renderTemplateGrid(loadedTemplates);
                    $loading.hide();
                    $grid.show();
                } else {
                    $loading.html('<p class="pmrb-error">Unable to load templates.</p>');
                }
            }
        });
    }

    function renderTemplateGrid(templates) {
        const $grid = $('#pmrb_template_grid');
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
                <label class="pmrb-template-option ${isSelected ? 'selected' : ''}" data-template="${escapeHtml(key)}">
                    <div class="pmrb-template-card">
                        <div class="pmrb-template-icon">
                            ${icon}
                        </div>
                        <div class="pmrb-template-info">
                            <strong>${escapeHtml(tmpl.name || key)}</strong>
                            <span>${escapeHtml(tmpl.description || '')}</span>
                        </div>
                        <div class="pmrb-template-check">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                    </div>
                </label>
            `);
            
            $grid.append($option);
        });

        $('#pmrb_default_template').val(state.selectedTemplate);

        $grid.find('.pmrb-template-option').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const templateKey = $this.data('template');

            $grid.find('.pmrb-template-option').removeClass('selected');
            $this.addClass('selected');

            state.selectedTemplate = templateKey;
            $('#pmrb_default_template').val(templateKey);
            saveState();
            clearStepError(2);

            renderTemplatePreview(templateKey);

            $this.addClass('pulse-select');
            setTimeout(() => $this.removeClass('pulse-select'), 300);
        });
    }

    function renderTemplatePreview(templateKey) {
        const $preview = $('#pmrb_template_preview');
        const $previewName = $('#pmrb_preview_template_name');
        
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
                <div class="pmrb-preview-document-inner">
                    ${html}
                </div>
            `);
        } else {
            $preview.html(`
                <div class="pmrb-preview-placeholder">
                    <div class="pmrb-preview-mock">
                        <div class="pmrb-mock-header"></div>
                        <div class="pmrb-mock-line pmrb-mock-title"></div>
                        <div class="pmrb-mock-line"></div>
                        <div class="pmrb-mock-line pmrb-mock-short"></div>
                        <div class="pmrb-mock-block"></div>
                        <div class="pmrb-mock-line"></div>
                        <div class="pmrb-mock-line pmrb-mock-short"></div>
                    </div>
                    <p class="pmrb-preview-note">Full preview available after checkout in Settings</p>
                </div>
            `);
        }
    }

    // ============================================================
    // BUSINESS INFO TOGGLE
    // ============================================================
    function initBusinessToggle() {
        const $toggle = $('#pmrb_business_toggle');
        const $fields = $('#pmrb_business_fields');
        const $icon = $toggle.find('.pmrb-toggle-icon');

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
        $(document).on('click', '#pmrb_btn_next', handleNext);
        $(document).on('click', '#pmrb_btn_back', handleBack);
    }

    function handleNext(e) {
        e.preventDefault();
        if (state.isProcessing) return;

        if (!validateCurrentStep()) {
            console.log('[PMRB] Validation failed on step', state.currentStep);
            return;
        }

        syncDataForSubmission();

        state.isProcessing = true;
        const $btn = $('#pmrb_btn_next');
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
        const $current = $('.pmrb-step-content:visible');
        const $next = $(`.pmrb-step-content[data-step="${state.currentStep}"]`);
        
        $current.fadeOut(200, function() {
            $next.fadeIn(300);
        });

        $('.pmrb-progress-step').each(function() {
            const stepNum = parseInt($(this).data('step'), 10);
            $(this).removeClass('active completed');
            if (stepNum === state.currentStep) {
                $(this).addClass('active');
            } else if (stepNum < state.currentStep) {
                $(this).addClass('completed');
            }
        });

        $('.pmrb-progress-line').each(function(index) {
            $(this).toggleClass('completed', index + 1 < state.currentStep);
        });

        const $backBtn = $('#pmrb_btn_back');
        const $nextText = $('#pmrb_btn_next_text');

        $backBtn.css('visibility', state.currentStep === 1 ? 'hidden' : 'visible');

        if (state.currentStep === CONFIG.totalSteps) {
            $nextText.text('Complete Subscription');
        } else if (state.currentStep === 1) {
            $nextText.text('Continue to Templates');
        } else if (state.currentStep === 2) {
            $nextText.text(CONFIG.isLoggedIn ? 'Continue to Payment' : 'Continue to Account');
        } else {
            $nextText.text('Continue to Payment');
        }

        $('#pmrb_current_step').val(state.currentStep);
    }

    function scrollToWizard() {
        const $wizard = $('.pmrb-wizard-checkout');
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
        if (!state.selectedRegion) {
            showStepError(1, CONFIG.strings.selectRegion);
            $('#pmrb_region_grid').addClass('validation-error');
            setTimeout(() => $('#pmrb_region_grid').removeClass('validation-error'), 2000);
            scrollToWizard();
            return false;
        }
        return true;
    }

    function validateStep2() {
        const templateValue = $('#pmrb_default_template').val() || state.selectedTemplate;
        if (!templateValue || templateValue.trim() === '') {
            showStepError(2, 'Please select a default template');
            return false;
        }
        state.selectedTemplate = templateValue;
        return true;
    }

    function validateStep3() {
        if (CONFIG.isLoggedIn || $('.pmrb-logged-in-notice').length) {
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
        if ($('.pmrb-billing-section').is(':visible')) {
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
        const $error = $(`#pmrb_step${step}_error`);
        $error.text(msg).slideDown(200);
    }

    function clearStepError(step) {
        $(`#pmrb_step${step}_error`).slideUp(200).text('');
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
        $('#pmrb_region_bundle').val(state.selectedRegion);
        $('#pmrb_calculated_price').val(state.calculatedPrice.toFixed(2));
        $('#pmrb_default_template').val(state.selectedTemplate);

        try {
            sessionStorage.setItem('pmrb_calculated_price', state.calculatedPrice.toFixed(2));
            sessionStorage.setItem('pmrb_region_selected', state.selectedRegion);
        } catch(e) {}

        console.log('[PMRB] Data synced', {
            region: state.selectedRegion,
            price: state.calculatedPrice.toFixed(2),
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

    $(document).on('click', '[data-gateway], .pmpro_btn_checkout, #pmpro_paypalexpress', function() {
        syncDataForSubmission();
    });

    // ============================================================
    // CSS INJECTION FOR DYNAMIC STYLES
    // ============================================================
    function injectDynamicStyles() {
        const styles = `
            <style>
                .pmrb-region-item.just-selected {
                    animation: selectPulse 0.4s ease-out;
                }
                
                @keyframes selectPulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
                
                #pmrb_price_display.price-update {
                    animation: priceFlash 0.3s ease-out;
                }
                
                @keyframes priceFlash {
                    0% { opacity: 0.5; transform: scale(0.95); }
                    100% { opacity: 1; transform: scale(1); }
                }
                
                #pmrb_region_grid.validation-error {
                    animation: shake 0.5s ease-out;
                    border-color: #ef4444 !important;
                }
                
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-5px); }
                    40%, 80% { transform: translateX(5px); }
                }
                
                .pmrb-wizard-checkout {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.5s ease, transform 0.5s ease;
                }
                
                .pmrb-wizard-checkout.loaded {
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
