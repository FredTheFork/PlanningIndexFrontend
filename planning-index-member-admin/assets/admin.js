(function($) {
    'use strict';

    var currentUserId = null;

    // Export CSV
    $('#pima-export-csv').on('click', function() {
        $('#pima-export-form').submit();
    });

    // Open modal
    $(document).on('click', '.pima-edit-btn', function() {
        var userId = $(this).data('user-id');
        currentUserId = userId;
        openModal(userId);
    });

    // Close modal
    $(document).on('click', '.pima-modal-close', function() {
        closeModal();
    });

    // Close on backdrop click
    $(document).on('click', '.pima-modal', function(e) {
        if ($(e.target).is('.pima-modal')) {
            closeModal();
        }
    });

    // Save member
    $('#pima-save-btn').on('click', function() {
        saveMember();
    });

    function openModal(userId) {
        $('#pima-save-message').removeClass('success error').text('');
        $('#pima-modal').show();
        $('#pima-modal .pima-modal-content').addClass('pima-loading');

        $.ajax({
            url: pimaAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pima_get_member',
                nonce: pimaAjax.nonce,
                user_id: userId
            },
            success: function(response) {
                $('#pima-modal .pima-modal-content').removeClass('pima-loading');
                if (response.success) {
                    populateModal(response.data);
                } else {
                    $('#pima-save-message').addClass('error').text(response.data || 'Error loading member.');
                }
            },
            error: function() {
                $('#pima-modal .pima-modal-content').removeClass('pima-loading');
                $('#pima-save-message').addClass('error').text('Server error.');
            }
        });
    }

    function closeModal() {
        $('#pima-modal').hide();
        currentUserId = null;
    }

    function populateModal(data) {
        $('#pima-edit-user-id').val(data.user_id);
        $('#pima-edit-user-display').text(data.display_name + ' (' + data.user_login + ') — ' + data.user_email);
        $('#pima-edit-level-display').text(data.level_name + ' — ' + data.membership_status);
        $('#pima-edit-type-display').text(data.product_type + (data.region_bundle ? ' — ' + data.region_bundle : ''));
        $('#pima-edit-status').val(data.membership_status);
        $('#pima-edit-price').val(data.price);

        // Business info
        var bi = data.business_info || {};
        $('#pima-edit-company').val(bi.company_name || '');
        $('#pima-edit-bemail').val(bi.email || '');
        $('#pima-edit-phone').val(bi.phone || '');
        $('#pima-edit-address').val(bi.company_address || '');
        $('#pima-edit-website').val(bi.website || '');
        $('#pima-edit-vat').val(bi.vat_number || '');

        // Templates
        var $tpl = $('#pima-edit-template').empty();
        $.each(data.all_templates, function(key, name) {
            var opt = $('<option></option>').val(key).text(name);
            if (key === data.template) opt.prop('selected', true);
            $tpl.append(opt);
        });

        // Councils
        var $councils = $('#pima-edit-councils').empty();
        var selected = data.councils_selected || [];
        $.each(data.all_councils, function(i, council) {
            var opt = $('<option></option>').val(council).text(council);
            if ($.inArray(council, selected) !== -1) {
                opt.prop('selected', true);
            }
            $councils.append(opt);
        });
    }

    function saveMember() {
        if (!currentUserId) return;

        var councils = [];
        $('#pima-edit-councils option:selected').each(function() {
            councils.push($(this).val());
        });

        var data = {
            action: 'pima_save_member',
            nonce: pimaAjax.nonce,
            user_id: currentUserId,
            membership_status: $('#pima-edit-status').val(),
            councils: councils,
            template: $('#pima-edit-template').val(),
            price: $('#pima-edit-price').val(),
            business_info: {
                company_name: $('#pima-edit-company').val(),
                email: $('#pima-edit-bemail').val(),
                phone: $('#pima-edit-phone').val(),
                company_address: $('#pima-edit-address').val(),
                website: $('#pima-edit-website').val(),
                vat_number: $('#pima-edit-vat').val()
            }
        };

        $('#pima-save-message').removeClass('success error').text('Saving...');
        $('#pima-save-btn').prop('disabled', true);

        $.ajax({
            url: pimaAjax.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                $('#pima-save-btn').prop('disabled', false);
                if (response.success) {
                    $('#pima-save-message').addClass('success').text('Saved! Reloading page...');
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    $('#pima-save-message').addClass('error').text(response.data || 'Save failed.');
                }
            },
            error: function() {
                $('#pima-save-btn').prop('disabled', false);
                $('#pima-save-message').addClass('error').text('Server error during save.');
            }
        });
    }

})(jQuery);
