jQuery(function ($) {
    const __ = wp.i18n.__;
    const textDomain = ssmptms_admin_ajax_params.text_domain;
    const POLL_INTERVAL = 15000;
    let pollTimer = null;
    let lastLogTableHtml = $('#ssmptms-log-table-container').html()?.trim() || null;

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
                + (data.next_check ? ' • ' + __('Next check', textDomain) + ' ' + data.next_check : '')
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

    function refreshLogTable(data) {
        var $container = $('#ssmptms-log-table-container');

        if (!$container.length || typeof data.log_table !== 'string') {
            return;
        }

        if ($container.find(':focus').length) {
            return;
        }

        var html = data.log_table.trim();
        if (html === lastLogTableHtml) {
            return;
        }

        lastLogTableHtml = html;
        $container.html(html);
    }

    function fetchSystemStatus() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }

        var $statusBar = $('.ssmptms-status-bar');
        var $logContainer = $('#ssmptms-log-table-container');
        var request = {
            action: 'ssmptms-get-system-status',
            ajax_nonce: ssmptms_admin_ajax_params.system_status_nonce
        };

        if ($logContainer.length) {
            request.include_log_table = 1;
            request.log_table_nonce = ssmptms_admin_ajax_params.log_table_nonce;
            request.log_query_args = ssmptms_admin_ajax_params.log_query_args || {};
        }

        $statusBar.each(function() {
            var $container = $(this);
            $container.removeClass('not-initialized').addClass('initializing');
            $container.html('<div><span class="ssmptms-status-spinner"></span> ' + __('Loading...', textDomain) + '</div>');
        });

        $logContainer.addClass('ssmptms-log-table-refreshing');

        $.post(
            ssmptms_admin_ajax_params.ajax_url,
            request,
            function(response) {
                if (response.success) {
                    $('.ssmptms-status-bar').each(function() {
                        $(this).replaceWith(renderSystemStatus(response.data));
                    });
                    refreshLogTable(response.data);
                }
            }
        ).always(function() {
            $logContainer.removeClass('ssmptms-log-table-refreshing');
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
                    setTimeout(fetchSystemStatus, 300);
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
