/**
 * Planning Index — Multi-Step Checkout Wizard
 * Premium, industry-grade checkout experience
 * Version: 3.0.0 - Complete Redesign with Enhanced Animations
 */
(function($) {
    'use strict';

    // ============================================================
    // CONFIGURATION
    // ============================================================
    const CONFIG = {
        unitPrice: parseFloat(window.pmpcConfig?.unitPrice || window.pmpcVars?.unitPrice) || 3,
        minSelection: parseInt(window.pmpcConfig?.minSelection || window.pmpcVars?.minSelect, 10) || 3,
        totalSteps: parseInt(window.pmpcConfig?.totalSteps, 10) || 4,
        councils: window.pmpcConfig?.councils || [],
        templates: window.pmpcConfig?.templates || {},
        checkoutUrl: window.pmpcConfig?.checkoutUrl || '',
        ajaxUrl: window.pmpcConfig?.ajaxUrl || '',
        nonce: window.pmpcConfig?.nonce || '',
        isLoggedIn: window.pmpcConfig?.isLoggedIn || false,
        strings: {
            chooseAtLeast: window.pmpcVars?.strings?.chooseAtLeast || window.pmpcConfig?.strings?.selectMinCouncils || 'Please select at least 3 councils',
            selectMinCouncils: window.pmpcConfig?.strings?.selectMinCouncils || 'Please select at least 3 councils to continue.',
            usernameRequired: window.pmpcConfig?.strings?.usernameRequired || 'Please enter a username.',
            passwordRequired: window.pmpcConfig?.strings?.passwordRequired || 'Please enter a password with at least 8 characters.',
            passwordMismatch: window.pmpcConfig?.strings?.passwordMismatch || 'Passwords do not match.',
            emailRequired: window.pmpcConfig?.strings?.emailRequired || 'Please enter a valid email address.',
            emailMismatch: window.pmpcConfig?.strings?.emailMismatch || 'Email addresses do not match.',
            processing: window.pmpcConfig?.strings?.processing || 'Processing your subscription...',
            continue: window.pmpcConfig?.strings?.continue || 'Continue',
            completeSubscription: window.pmpcConfig?.strings?.completeSubscription || 'Complete Subscription',
            perMonth: window.pmpcConfig?.strings?.perMonth || '/month'
        }
    };

    // ============================================================
    // STATE MANAGEMENT
    // ============================================================
    const state = {
        currentStep: 1,
        selectedCouncils: [],
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
        state.isWizardMode = $('.pmpc-wizard-checkout').length > 0;
        
        if (state.isWizardMode) {
            initWizardMode();
        } else if ($('#pmpc_councils').length) {
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

        initCouncilGrid();
        initCouncilSearch();
        initTemplateSelection();
        initBusinessToggle();
        initNavigation();
        initFormValidation();
        initFormPersistence();
        initKeyboardNavigation();

        updateStepDisplay();
        updatePriceDisplay();
        updateOrderSummary();
        updateSelectedTags();

        window.addEventListener('popstate', handlePopState);

        // Add smooth entrance animation
        setTimeout(() => {
            $('.pmpc-wizard-checkout').addClass('loaded');
        }, 100);

        console.log('[PMPC] Premium Wizard initialized', { step: state.currentStep, loggedIn: CONFIG.isLoggedIn });
    }

    // ============================================================
    // KEYBOARD NAVIGATION
    // ============================================================
    function initKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            // Enter to proceed on buttons
            if (e.key === 'Enter' && $(e.target).hasClass('pmpc-council-item')) {
                e.preventDefault();
                $(e.target).click();
            }
        });

        // Make council items focusable
        $('.pmpc-council-item').attr('tabindex', '0');
    }

    // ============================================================
    // FALLBACK MODE
    // ============================================================
    function initFallbackMode() {
        const $select = $('#pmpc_councils');
        if (!$select.length) return;

        restoreState();

        if ($('#pmpc_council_grid').length) {
            initCouncilGrid();
            initCouncilSearch();
        } else {
            buildEnhancedSelectUI($select);
        }

        updatePriceDisplay();
        $('.pmpc-fallback-selector p').hide();
    }

    // ============================================================
    // BUILD ENHANCED SELECT UI (fallback)
    // ============================================================
    function buildEnhancedSelectUI($select) {
        $select.hide();

        const councils = [];
        $select.find('option').each(function() {
            const val = $(this).val();
            const name = $(this).text().trim();
            if (val) {
                councils.push({ id: val, name });
                if ($(this).is(':selected')) {
                    state.selectedCouncils.push(val);
                }
            }
        });

        councils.sort((a, b) => a.name.localeCompare(b.name));

        const $wrapper = $('<div class="pmpc-ui-wrapper"></div>');
        
        const $header = $(`
            <div class="pmpc-step-header">
                <div class="pmpc-step-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Step 1
                </div>
                <h3>Select Your Councils</h3>
                <p>Choose the councils where you want to find work. You'll receive planning applications from these areas instantly.</p>
            </div>
        `);

        const $body = $('<div class="pmpc-body"></div>');
        
        const $benefits = $(`
            <div class="pmpc-benefits">
                <div class="pmpc-benefit">
                    <div class="pmpc-benefit-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="pmpc-benefit-text">
                        <h4>Instant Access</h4>
                        <p>Start receiving applications immediately after checkout</p>
                    </div>
                </div>
                <div class="pmpc-benefit">
                    <div class="pmpc-benefit-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="pmpc-benefit-text">
                        <h4>Only £${CONFIG.unitPrice}/council</h4>
                        <p>Pay only for the areas you actually work in</p>
                    </div>
                </div>
                <div class="pmpc-benefit">
                    <div class="pmpc-benefit-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="pmpc-benefit-text">
                        <h4>Flexible Choice</h4>
                        <p>Select any ${CONFIG.minSelection}+ councils across the UK</p>
                    </div>
                </div>
            </div>
        `);

        const $searchWrap = $(`
            <div class="pmpc-search-wrap">
                <div class="pmpc-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <input type="text" class="pmpc-search" placeholder="Search councils by name...">
            </div>
        `);

        const $selectionHeader = $(`
            <div class="pmpc-selection-header">
                <span class="pmpc-selection-title">Available Councils</span>
                <span class="pmpc-selection-count"><strong>${state.selectedCouncils.length}</strong> of ${CONFIG.minSelection}+ selected</span>
            </div>
        `);

        const $list = $('<div class="pmpc-council-list"></div>');

        councils.forEach(c => {
            const isSelected = state.selectedCouncils.includes(c.id);
            const $item = $(`<div class="pmpc-council-item ${isSelected ? 'selected' : ''}" data-id="${escapeHtml(c.id)}" tabindex="0">${escapeHtml(c.name)}</div>`);
            $list.append($item);
        });

        const $selectedBox = $(`
            <div class="pmpc-selected-box">
                <h4>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Your Selected Councils
                </h4>
                <div class="pmpc-selected-list"></div>
            </div>
        `);

        const $error = $('<div id="pmpc_error" class="pmpc-error"></div>');

        const $priceSummary = $(`
            <div class="pmpc-price-summary">
                <div class="pmpc-price-info">
                    <span class="pmpc-price-label">Monthly Total</span>
                    <span class="pmpc-price-breakdown">${state.selectedCouncils.length} councils × £${CONFIG.unitPrice}</span>
                    <span class="pmpc-price-amount" id="pmpc_price_label">£${(state.selectedCouncils.length * CONFIG.unitPrice).toFixed(2)}</span>
                    <span class="pmpc-price-period">per month</span>
                </div>
                <div class="pmpc-cancel-text">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Cancel anytime
                </div>
            </div>
        `);

        $select.after($wrapper);
        $wrapper.append($header);
        $body.append($benefits, $searchWrap, $selectionHeader, $list, $selectedBox, $error, $priceSummary);
        $wrapper.append($body);

        initFallbackEventHandlers($wrapper, $select, $list, $selectedBox, $selectionHeader, $priceSummary, $error);
        renderFallbackSelectedList($list, $selectedBox);
    }

    function initFallbackEventHandlers($wrapper, $select, $list, $selectedBox, $selectionHeader, $priceSummary, $error) {
        $wrapper.find('.pmpc-search').on('input', function() {
            const term = $(this).val().toLowerCase();
            $list.find('.pmpc-council-item').each(function() {
                $(this).toggle($(this).text().toLowerCase().includes(term));
            });
        });

        $list.on('click keypress', '.pmpc-council-item', function(e) {
            if (e.type === 'keypress' && e.key !== 'Enter') return;
            
            const id = $(this).data('id');
            const index = state.selectedCouncils.indexOf(id);
            
            if (index === -1) {
                state.selectedCouncils.push(id);
                $(this).addClass('selected');
                // Pulse animation
                $(this).addClass('pulse-select');
                setTimeout(() => $(this).removeClass('pulse-select'), 300);
            } else {
                state.selectedCouncils.splice(index, 1);
                $(this).removeClass('selected');
            }
            
            updateFallbackHiddenSelect($select);
            renderFallbackSelectedList($list, $selectedBox);
            updateFallbackPriceDisplay($priceSummary, $selectionHeader);
            $error.text('');
            saveState();
        });

        $selectedBox.on('click', '.remove', function() {
            const id = $(this).parent().data('id');
            const index = state.selectedCouncils.indexOf(id);
            if (index > -1) {
                state.selectedCouncils.splice(index, 1);
            }
            $list.find(`[data-id="${id}"]`).removeClass('selected');
            updateFallbackHiddenSelect($select);
            renderFallbackSelectedList($list, $selectedBox);
            updateFallbackPriceDisplay($priceSummary, $selectionHeader);
            saveState();
        });

        $('form#pmpro_form').on('submit', function(e) {
            const count = state.selectedCouncils.length;

            if (count < CONFIG.minSelection) {
                e.preventDefault();
                $error.text(CONFIG.strings.chooseAtLeast);
                $('html, body').animate({ scrollTop: $wrapper.offset().top - 80 }, 300);
                return false;
            }

            updateFallbackHiddenSelect($select);
            return true;
        });
    }

    function updateFallbackHiddenSelect($select) {
        $select.find('option').prop('selected', false);
        state.selectedCouncils.forEach(id => {
            $select.find(`option[value="${id}"]`).prop('selected', true);
        });
        $select.trigger('change');
        
        const price = (state.selectedCouncils.length * CONFIG.unitPrice).toFixed(2);
        $('#pmpc_calculated_price').val(price);
        state.calculatedPrice = parseFloat(price);
    }

    function renderFallbackSelectedList($list, $selectedBox) {
        const $sel = $selectedBox.find('.pmpc-selected-list');
        $sel.empty();

        if (state.selectedCouncils.length === 0) {
            $sel.html('<p class="empty">Click on councils above to select them</p>');
            return;
        }

        state.selectedCouncils.forEach(id => {
            const $item = $list.find(`[data-id="${id}"]`);
            const name = $item.text();
            $sel.append(`<div class="pmpc-tag" data-id="${escapeHtml(id)}">${escapeHtml(name)}<span class="remove">×</span></div>`);
        });
    }

    function updateFallbackPriceDisplay($priceSummary, $selectionHeader) {
        const count = state.selectedCouncils.length;
        const price = (count * CONFIG.unitPrice).toFixed(2);
        
        $priceSummary.find('.pmpc-price-amount').text(`£${price}`);
        $priceSummary.find('.pmpc-price-breakdown').text(`${count} council${count !== 1 ? 's' : ''} × £${CONFIG.unitPrice}`);
        $selectionHeader.find('.pmpc-selection-count strong').text(count);
        
        $('#pmpc_calculated_price').val(price);
        state.calculatedPrice = parseFloat(price);
    }

    // ============================================================
    // STATE PERSISTENCE
    // ============================================================
    function saveState() {
        try {
            const stateToSave = {
                selectedCouncils: state.selectedCouncils,
                calculatedPrice: state.calculatedPrice,
                selectedTemplate: state.selectedTemplate,
                formData: state.formData,
                currentStep: state.currentStep
            };
            sessionStorage.setItem('pmpc_checkout_state', JSON.stringify(stateToSave));
            sessionStorage.setItem('pmpc_selected_councils', JSON.stringify(state.selectedCouncils));
            sessionStorage.setItem('pmpc_calculated_price', state.calculatedPrice.toFixed(2));
        } catch (e) {
            console.warn('[PMPC] Could not save state:', e);
        }
    }

    function restoreState() {
        try {
            const savedState = sessionStorage.getItem('pmpc_checkout_state');
            if (savedState) {
                const parsed = JSON.parse(savedState);
                state.selectedCouncils = parsed.selectedCouncils || [];
                state.calculatedPrice = parsed.calculatedPrice || 0;
                state.selectedTemplate = parsed.selectedTemplate || 'professional';
                state.formData = parsed.formData || {};
            }
        } catch (e) {
            console.warn('[PMPC] Could not restore state:', e);
        }
    }

    function clearState() {
        try {
            sessionStorage.removeItem('pmpc_checkout_state');
            sessionStorage.removeItem('pmpc_selected_councils');
            sessionStorage.removeItem('pmpc_calculated_price');
        } catch (e) {}
    }

    // ============================================================
    // COUNCIL GRID (wizard mode)
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
        updatePriceDisplay();
        updateOrderSummary();
        saveState();

        // Sync with server
        if (CONFIG.ajaxUrl && CONFIG.nonce) {
            fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'pmpc_update_session',
                    councils: JSON.stringify(state.selectedCouncils),
                    price: state.calculatedPrice.toFixed(2),
                    nonce: CONFIG.nonce
                })
            });
        }

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
        if (count >= CONFIG.minSelection) {
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
    // PRICE CALCULATIONS
    // ============================================================
    function updatePriceDisplay() {
        const count = state.selectedCouncils.length;
        state.calculatedPrice = count * CONFIG.unitPrice;

        const $priceDisplay = $('#pmpc_price_display');
        const newPrice = `£${state.calculatedPrice.toFixed(2)}`;
        
        // Add animation class
        $priceDisplay.addClass('price-update');
        $priceDisplay.text(newPrice);
        setTimeout(() => $priceDisplay.removeClass('price-update'), 300);
        
        $('#pmpc_price_breakdown').text(`${count} council${count !== 1 ? 's' : ''} × £${CONFIG.unitPrice}`);
        $('#pmpc_calculated_price').val(state.calculatedPrice.toFixed(2));
        $('#pmpc_price_label').text(newPrice);
        saveState();
    }

    function updateOrderSummary() {
        const count = state.selectedCouncils.length;
        
        $('#pmpc_summary_councils').text(`${count} council${count !== 1 ? 's' : ''}`);
        $('#pmpc_summary_price').text(`£${state.calculatedPrice.toFixed(2)}/month`);
        $('#pmpc_summary_total').text(`£${state.calculatedPrice.toFixed(2)}`);
        
        const $councilList = $('#pmpc_summary_council_list');
        if ($councilList.length && count > 0) {
            const displayCouncils = state.selectedCouncils.slice(0, 5);
            const remaining = count - 5;
            let listHtml = displayCouncils.map(c => `<span class="pmpc-summary-council">${escapeHtml(c)}</span>`).join('');
            if (remaining > 0) {
                listHtml += `<span class="pmpc-summary-more">+${remaining} more</span>`;
            }
            $councilList.html(listHtml);
        }
    }

    // ============================================================
    // TEMPLATE SELECTION (Dynamic Loading with Live Preview)
    // ============================================================
    let loadedTemplates = null;
    let templateDummyData = null;

    function initTemplateSelection() {
        // Check if we have the new dynamic template container
        const $grid = $('#pmpc_template_grid');
        const $loading = $('#pmpc_template_loading');
        
        if ($grid.length && $loading.length) {
            // New dynamic template system
            loadTemplatesFromAPI();
        } else {
            // Fallback to old static template selection
            initStaticTemplateSelection();
        }
    }

    function initStaticTemplateSelection() {
        const $templates = $('.pmpc-template-option');
        if (!$templates.length) return;

        $templates.removeClass('selected');
        $(`.pmpc-template-option[data-template="${state.selectedTemplate}"]`).addClass('selected');
        $(`input[name="pmpc_default_template"][value="${state.selectedTemplate}"]`).prop('checked', true);

        $templates.on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const template = $this.data('template');

            $templates.removeClass('selected');
            $this.addClass('selected');
            $this.find('input[type="radio"]').prop('checked', true);

            state.selectedTemplate = template;
            saveState();
            clearStepError(2);
        });
    }

    function loadTemplatesFromAPI() {
        const $grid = $('#pmpc_template_grid');
        const $loading = $('#pmpc_template_loading');
        const $previewContainer = $('#pmpc_template_preview_container');
        
        const restUrl = window.pmpcConfig?.restUrl || '/wp-json/pi/v1';
        
        $.ajax({
            url: restUrl + '/templates',
            method: 'GET',
            beforeSend: function(xhr) {
                const nonce = window.pmpcConfig?.restNonce;
                if (nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                }
            },
            success: function(response) {
                loadedTemplates = response.templates || {};
                templateDummyData = response.dummy_data || {};
                
                // Use user's current template or the one from state
                const userCurrent = window.pmpcConfig?.userCurrentTemplate || response.current || 'basic';
                if (!state.selectedTemplate || state.selectedTemplate === 'professional') {
                    state.selectedTemplate = userCurrent;
                }
                
                renderTemplateGrid(loadedTemplates);
                $loading.hide();
                $grid.show();
                $previewContainer.show();
                
                // Render initial preview
                renderTemplatePreview(state.selectedTemplate);
                
                console.log('[PMPC] Templates loaded from API:', Object.keys(loadedTemplates));
            },
            error: function(xhr, status, error) {
                console.warn('[PMPC] Failed to load templates from API:', error);
                
                // Fallback to config templates
                loadedTemplates = window.pmpcConfig?.templates || {};
                templateDummyData = {};
                
                if (Object.keys(loadedTemplates).length > 0) {
                    renderTemplateGrid(loadedTemplates);
                    $loading.hide();
                    $grid.show();
                } else {
                    $loading.html('<p class="pmpc-error">' + (CONFIG.strings.templateLoadError || 'Unable to load templates.') + '</p>');
                }
            }
        });
    }

    function renderTemplateGrid(templates) {
        const $grid = $('#pmpc_template_grid');
        $grid.empty();

        // Template icon mapping
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

        // Update hidden input with current selection
        $('#pmpc_default_template').val(state.selectedTemplate);

        // Bind click handlers
        $grid.find('.pmpc-template-option').on('click', function(e) {
            e.preventDefault();
            const $this = $(this);
            const templateKey = $this.data('template');

            // Update UI
            $grid.find('.pmpc-template-option').removeClass('selected');
            $this.addClass('selected');

            // Update state and hidden input
            state.selectedTemplate = templateKey;
            $('#pmpc_default_template').val(templateKey);
            saveState();
            clearStepError(2);

            // Update preview
            renderTemplatePreview(templateKey);

            // Add selection animation
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

        // Update preview name badge
        $previewName.text(template.name || templateKey);

        // If template has HTML, render it with dummy data
        if (template.html) {
            let html = template.html;
            
            // Replace placeholders with dummy data
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
            // Fallback preview for templates without HTML
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
            console.log('[PMPC] Validation failed on step', state.currentStep);
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
            // Skip step 3 when going back if logged in
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
            $nextText.text('Complete Subscription');
        } else if (state.currentStep === 1) {
            $nextText.text('Continue to Templates');
        } else if (state.currentStep === 2) {
            $nextText.text(CONFIG.isLoggedIn ? 'Continue to Payment' : 'Continue to Account');
        } else {
            $nextText.text('Continue to Payment');
        }

        $('#pmpc_current_step').val(state.currentStep);
    }

    function scrollToWizard() {
        const $wizard = $('.pmpc-wizard-checkout');
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
        // Real-time validation feedback
        $('input[required]').on('blur', function() {
            validateField($(this));
        });
    }

    function validateField($field) {
        const value = $field.val().trim();
        const type = $field.attr('type');
        const name = $field.attr('name');
        
        // Remove existing error
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
            // Highlight the grid
            $('#pmpc_council_grid').addClass('validation-error');
            setTimeout(() => $('#pmpc_council_grid').removeClass('validation-error'), 2000);
            scrollToWizard();
            return false;
        }
        return true;
    }

    function validateStep2() {
        // Check the hidden input value OR the state - templates use clickable cards, not radio inputs
        const templateValue = $('#pmpc_default_template').val() || state.selectedTemplate;
        if (!templateValue || templateValue.trim() === '') {
            showStepError(2, 'Please select a default template');
            return false;
        }
        // Ensure state is in sync
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

        // Clear previous errors
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
        const excluded = ['password', 'password2', 'CVV', 'AccountNumber', 'ExpirationMonth', 'ExpirationYear'];

        $('input, textarea, select').not(excluded.map(n => `[name="${n}"]`).join(',')).on('change input', function() {
            const name = $(this).attr('name');
            if (name && !excluded.includes(name)) {
                state.formData[name] = $(this).val();
                saveState();
            }
        });

        // Restore saved form data
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

        // Sync price
        const priceStr = state.calculatedPrice.toFixed(2);
        $('#pmpc_calculated_price').val(priceStr);

        // Sync template
        $(`input[name="pmpc_default_template"][value="${state.selectedTemplate}"]`).prop('checked', true);

        try {
            sessionStorage.setItem('pmpc_calculated_price', priceStr);
            sessionStorage.setItem('pmpc_selected_councils', JSON.stringify(state.selectedCouncils));
        } catch(e) {}

        console.log('[PMPC] Data synced', {
            councils: state.selectedCouncils.length,
            price: priceStr,
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
                .pmpc-council-item.just-selected {
                    animation: selectPulse 0.4s ease-out;
                }
                
                @keyframes selectPulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
                
                #pmpc_selected_count.count-change {
                    animation: countBounce 0.2s ease-out;
                }
                
                @keyframes countBounce {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.3); }
                    100% { transform: scale(1); }
                }
                
                #pmpc_price_display.price-update {
                    animation: priceFlash 0.3s ease-out;
                }
                
                @keyframes priceFlash {
                    0% { opacity: 0.5; transform: scale(0.95); }
                    100% { opacity: 1; transform: scale(1); }
                }
                
                #pmpc_council_grid.validation-error {
                    animation: shake 0.5s ease-out;
                    border-color: #ef4444 !important;
                }
                
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-5px); }
                    40%, 80% { transform: translateX(5px); }
                }
                
                .pmpc-wizard-checkout {
                    opacity: 0;
                    transform: translateY(20px);
                    transition: opacity 0.5s ease, transform 0.5s ease;
                }
                
                .pmpc-wizard-checkout.loaded {
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
