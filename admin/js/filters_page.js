jQuery(document).ready(function($) {
    const __ = wp.i18n.__;
    const textDomain = ssmptms_filters_page.text_domain;

    var modal = $('#ssmptms-filter-modal');
    var closeBtn = modal.find('.ssmptms-close');
    var form = $('#ssmptms-filter-form');
    var saveBtn = $('#ssmptms-save-filter');

    $('#filter_action').on('change', function() {
        if ($(this).val() === 'set_priority') {
            $('#filter_priority_row').show();
        } else {
            $('#filter_priority_row').hide();
        }
    });

    $('#ssmptms-add-filter').on('click', function() {
        $('#ssmptms-filter-modal-title').text(__('Add Filter Rule', textDomain));
        form[0].reset();
        $('#filter_id').val('');
        $('#filter_is_active').prop('checked', true);
        $('#filter_priority_row').hide();
        modal.show();
    });

    $(document).on('click', '.ssmptms-edit-filter', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var row = $(this).closest('tr');

        $('#ssmptms-filter-modal-title').text(__('Edit Filter Rule', textDomain));
        $('#filter_id').val(id);
        $('#filter_name').val(row.children('td:eq(0)').text());
        $('#filter_search_subject').val(row.children('td:eq(1)').find('code').text());
        $('#filter_search_body').val(row.children('td:eq(2)').find('code').text());
        $('#filter_search_recipient').val(row.children('td:eq(3)').find('code').text());
        $('#filter_priority').val(row.children('td:eq(5)').text());

        var actionValue = row.data('action');
        if (actionValue) {
            $('#filter_action').val(actionValue);
        }
        var priorityValue = row.data('priorityValue');
        if (typeof priorityValue !== 'undefined') {
            $('#filter_priority_value').val(priorityValue);
        }

        if ($('#filter_action').val() === 'set_priority') {
            $('#filter_priority_row').show();
        } else {
            $('#filter_priority_row').hide();
        }

        var isActive = row.find('.ssmptms-filter-status').hasClass('active');
        $('#filter_is_active').prop('checked', isActive);

        modal.show();
    });

    closeBtn.on('click', function() {
        modal.hide();
    });

    $(window).on('click', function(e) {
        if (e.target === modal[0]) {
            modal.hide();
        }
    });

    form.on('submit', function(e) {
        e.preventDefault();

        if (form.data('loading')) {
            return;
        }

        form.data('loading', true);
        window.ssmptmsUtils.setSubmitLoading(
            saveBtn,
            null,
            true,
            __('Saving...', textDomain)
        );
        form.find('input, textarea, select, button').prop('disabled', true);

        var formData = {
            action: 'ssmptms_filter_save',
            ajax_nonce: ssmptms_filters_page.nonces.save,
            filter_id: $('#filter_id').val(),
            name: $('#filter_name').val(),
            search_subject: $('#filter_search_subject').val(),
            search_body: $('#filter_search_body').val(),
            search_recipient: $('#filter_search_recipient').val(),
            filter_action: $('#filter_action').val(),
            priority_value: $('#filter_priority_value').val(),
            priority: $('#filter_priority').val(),
            is_active: $('#filter_is_active').prop('checked') ? 1 : 0
        };

        $.post(ajaxurl, formData)
            .done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || __('Error saving filter', textDomain));
                }
            })
            .fail(function() {
                alert(__('Request failed. Please try again.', textDomain));
            })
            .always(function() {
                form.data('loading', false);
                form.find('input, textarea, select, button').prop('disabled', false);
                window.ssmptmsUtils.setSubmitLoading(saveBtn, null, false, null);
            });
    });

    $(document).on('click', '.ssmptms-toggle-filter', function(e) {
        e.preventDefault();
        var $link = $(this);
        var id = $link.data('id');
        if (!confirm(__('Are you sure you want to toggle this filter?', textDomain))) return;
        if (!window.ssmptmsUtils.lockActionLink($link, __('Working...', textDomain))) return;

        $.post(ajaxurl, {
            action: 'ssmptms_filter_toggle',
            ajax_nonce: ssmptms_filters_page.nonces.toggle,
            filter_id: id
        }).done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                window.ssmptmsUtils.unlockActionLink($link);
            }
        }).fail(function() {
            window.ssmptmsUtils.unlockActionLink($link);
            alert(__('Request failed. Please try again.', textDomain));
        });
    });

    $(document).on('click', '.ssmptms-delete-filter', function(e) {
        e.preventDefault();
        var $link = $(this);
        var id = $link.data('id');
        if (!confirm(__('Are you sure you want to delete this filter rule?', textDomain))) return;
        if (!window.ssmptmsUtils.lockActionLink($link, __('Deleting...', textDomain))) return;

        $.post(ajaxurl, {
            action: 'ssmptms_filter_delete',
            ajax_nonce: ssmptms_filters_page.nonces.delete,
            filter_id: id
        }).done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                window.ssmptmsUtils.unlockActionLink($link);
            }
        }).fail(function() {
            window.ssmptmsUtils.unlockActionLink($link);
            alert(__('Request failed. Please try again.', textDomain));
        });
    });
});
