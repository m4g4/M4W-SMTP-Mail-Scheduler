jQuery(function ($) {
    const __ = wp.i18n.__;
    const textDomain = ssmptms_admin_ajax_params.text_domain;
    const POLL_INTERVAL = 30000;
    let pollTimer = null;

    function renderSystemStatus(data) {
        var icon = '';
        var statusClass = '';
        switch (data.status) {
            case 'disabled':
                icon = '🛑';
                statusClass = 'ssmptms-status-bar-disabled';
                break;
            case 'scheduler_disabled':
                icon = '⏩';
                statusClass = 'ssmptms-status-bar-disabled';
                break;
            case 'running':
                icon = '✅';
                statusClass = 'ssmptms-status-bar-running';
                break;
            case 'not_running':
                icon = '🚫';
                statusClass = 'ssmptms-status-bar-not-running';
                break;
            case 'idle':
            default:
                icon = '⏸';
                statusClass = 'ssmptms-status-bar-idle';
                break;
        }

        var html = '<div class="ssmptms-status-bar" data-status="' + data.status + '">';
        html += '<span class="' + statusClass + '">' + icon + ' ' + data.label + '</span>';

        if (data.status === 'running' && typeof data.progress === 'number') {
            html += '<div class="ssmptms-progress-bar">';
            html += '<div class="ssmptms-progress" style="width:' + data.progress + '%;"></div>';
            html += '</div>';
            html += '<p class="description">'
                + data.queued + ' ' + __('emails queued', textDomain) + ' • ETA ' + data.eta + ' (' + data.duration + ')'
                + '</p>';
        } else if (data.status === 'not_running') {
            html += ' <a class="ssmptms-start-scheduler">▶️ ' + __('Start', textDomain) + '</a>';
            html += '<p class="description">' + data.description + '</p>';
        } else {
            html += '<p class="description">' + (data.description || '') + '</p>';
        }

        html += '</div>';
        return html;
    }

    function fetchSystemStatus() {
        var $statusBar = $('.ssmptms-status-bar');
        
        $statusBar.each(function() {
            var $container = $(this);
            $container.removeClass('not-initialized').addClass('initializing');
            $container.html('<div><span class="ssmptms-status-spinner"></span> ' + __('Loading...', textDomain) + '</div>');
        });

        $.post(
            ssmptms_admin_ajax_params.ajax_url,
            {
                action: 'ssmptms-get-system-status',
                ajax_nonce: ssmptms_admin_ajax_params.system_status_nonce
            },
            function(response) {
                if (response.success) {
                    $('.ssmptms-status-bar').each(function() {
                        $(this).replaceWith(renderSystemStatus(response.data));
                    });
                }
            }
        ).always(function() {
            pollTimer = setTimeout(fetchSystemStatus, POLL_INTERVAL);
        });
    }

    $(document).on('click', '.ssmptms-start-scheduler:not(.started)', function(e) {
        e.preventDefault();
        const $btn = jQuery(this);
        $btn.text(`⏳ ${__('Starting', textDomain)} ...`);
        $btn.addClass('started');

        jQuery.post(
            ssmptms_admin_ajax_params.ajax_url, { 
                'action': ssmptms_admin_ajax_params.start_action,
                'ajax_nonce': ssmptms_admin_ajax_params.ajax_nonce,
            }, function(response) {
                if (response.success) {
                    $btn.text(`✅ ${__('Started', textDomain)}`);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    console.error('Failed to start the scheduler!');
                }
        }).fail(() => {
            console.error('Failed to start the scheduler!');
        });
    });

    if (ssmptms_admin_ajax_params.system_status_nonce) {
        fetchSystemStatus();
    }
});