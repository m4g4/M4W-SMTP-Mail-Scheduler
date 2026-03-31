<?php
namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(__NAMESPACE__ . '\\Filters_Settings', false)) {

    class Filters_Settings {

        private static $instance;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct() {
            add_action('wp_ajax_ssmptms_filter_save', [$this, 'ajax_save_filter']);
            add_action('wp_ajax_ssmptms_filter_delete', [$this, 'ajax_delete_filter']);
            add_action('wp_ajax_ssmptms_filter_toggle', [$this, 'ajax_toggle_filter']);
        }

        public function render_tab() {
            $filters = Filter_Rules::get_instance()->get_all();
            $actions = Filter_Rules::get_actions();
            ?>
            <div class="wrap">
                <h2><?php echo esc_html__('Email Filters', Constants::DOMAIN); ?></h2>
                <p><?php echo esc_html__('Define rules to filter emails based on subject, body, or recipient. Matching emails will have the specified action applied.', Constants::DOMAIN); ?></p>
                
                <button type="button" class="button button-primary" id="ssmptms-add-filter">
                    <?php echo esc_html__('Add New Rule', Constants::DOMAIN); ?>
                </button>

                <div id="ssmptms-filter-modal" class="ssmptms-modal" style="display:none;">
                    <div class="ssmptms-modal-content">
                        <span class="ssmptms-close">&times;</span>
                        <h3 id="ssmptms-filter-modal-title"><?php echo esc_html__('Add Filter Rule', Constants::DOMAIN); ?></h3>
                        <form id="ssmptms-filter-form">
                            <input type="hidden" name="filter_id" id="filter_id" value="">
                            <table class="form-table">
                                <tr>
                                    <th><?php echo esc_html__('Name', Constants::DOMAIN); ?></th>
                                    <td>
                                        <input type="text" name="name" id="filter_name" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Search in Subject', Constants::DOMAIN); ?></th>
                                    <td>
                                        <textarea name="search_subject" id="filter_search_subject" class="large-text code" rows="2" placeholder="<?php echo esc_attr__('e.g., urgent, order confirmed', Constants::DOMAIN); ?>"></textarea>
                                        <p class="description"><?php echo esc_html__('Text or pattern to search in email subject.', Constants::DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Search in Body', Constants::DOMAIN); ?></th>
                                    <td>
                                        <textarea name="search_body" id="filter_search_body" class="large-text code" rows="2" placeholder="<?php echo esc_attr__('e.g., invoice, thank you for your order', Constants::DOMAIN); ?>"></textarea>
                                        <p class="description"><?php echo esc_html__('Text or pattern to search in email body.', Constants::DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Search in Recipient', Constants::DOMAIN); ?></th>
                                    <td>
                                        <textarea name="search_recipient" id="filter_search_recipient" class="large-text code" rows="2" placeholder="<?php echo esc_attr__('e.g., @gmail.com, newsletter@', Constants::DOMAIN); ?>"></textarea>
                                        <p class="description"><?php echo esc_html__('Text or pattern to search in recipient email addresses.', Constants::DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Action', Constants::DOMAIN); ?></th>
                                    <td>
                                        <select name="filter_action" id="filter_action">
                                            <?php foreach ($actions as $value => $label): ?>
                                                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="filter_priority_row" style="display:none;">
                                    <th><?php echo esc_html__('Scheduler Priority Value', Constants::DOMAIN); ?></th>
                                    <td>
                                        <input type="number" name="priority_value" id="filter_priority_value" value="0">
                                        <p class="description"><?php echo esc_html__('Set email priority (higher numbers = sent sooner).', Constants::DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Rule Priority', Constants::DOMAIN); ?></th>
                                    <td>
                                        <input type="number" name="priority" id="filter_priority" value="0" min="0">
                                        <p class="description"><?php echo esc_html__('Higher priority rules are evaluated first.', Constants::DOMAIN); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php echo esc_html__('Active', Constants::DOMAIN); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" id="filter_is_active" value="1" checked>
                                            <?php echo esc_html__('Enable this rule', Constants::DOMAIN); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary" id="ssmptms-save-filter">
                                    <?php echo esc_html__('Save Rule', Constants::DOMAIN); ?>
                                </button>
                            </p>
                        </form>
                    </div>
                </div>

                <br><br>

                <table class="widefat" id="ssmptms-filters-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Name', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Subject', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Body', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Recipient', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Action', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Priority', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Status', Constants::DOMAIN); ?></th>
                            <th><?php echo esc_html__('Actions', Constants::DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filters)): ?>
                            <?php foreach ($filters as $filter): ?>
                                <tr data-id="<?php echo esc_attr($filter->id); ?>" data-action="<?php echo esc_attr($filter->action); ?>" data-priority-value="<?php echo esc_attr($filter->priority_value); ?>">
                                    <td><?php echo esc_html($filter->name); ?></td>
                                    <td><code><?php echo esc_html($filter->search_subject ?? ''); ?></code></td>
                                    <td><code><?php echo esc_html($filter->search_body ?? ''); ?></code></td>
                                    <td><code><?php echo esc_html($filter->search_recipient ?? ''); ?></code></td>
                                    <td><?php echo esc_html($actions[$filter->action] ?? $filter->action); ?></td>
                                    <td><?php echo esc_html($filter->priority); ?></td>
                                    <td>
                                        <span class="ssmptms-filter-status <?php echo $filter->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $filter->is_active ? esc_html__('Active', Constants::DOMAIN) : esc_html__('Inactive', Constants::DOMAIN); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small ssmptms-edit-filter" data-id="<?php echo esc_attr($filter->id); ?>">
                                            <?php echo esc_html__('Edit', Constants::DOMAIN); ?>
                                        </button>
                                        <button type="button" class="button button-small ssmptms-toggle-filter" data-id="<?php echo esc_attr($filter->id); ?>">
                                            <?php echo $filter->is_active ? esc_html__('Disable', Constants::DOMAIN) : esc_html__('Enable', Constants::DOMAIN); ?>
                                        </button>
                                        <button type="button" class="button button-small button-link-delete ssmptms-delete-filter" data-id="<?php echo esc_attr($filter->id); ?>">
                                            <?php echo esc_html__('Delete', Constants::DOMAIN); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;"><?php echo esc_html__('No filter rules defined.', Constants::DOMAIN); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
            $this->enqueue_filter_scripts();
        }

        private function enqueue_filter_scripts() {
            ?>
            <style>
                .ssmptms-modal {
                    position: fixed;
                    z-index: 9999;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0,0,0,0.5);
                }
                .ssmptms-modal-content {
                    background-color: #fefefe;
                    margin: 5% auto;
                    padding: 20px;
                    border: 1px solid #888;
                    width: 500px;
                    max-width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                .ssmptms-close {
                    color: #aaa;
                    float: right;
                    font-size: 28px;
                    font-weight: bold;
                    cursor: pointer;
                }
                .ssmptms-close:hover { color: #000; }
                .ssmptms-filter-status.active { color: green; font-weight: bold; }
                .ssmptms-filter-status.inactive { color: red; }
            </style>
            <script>
            jQuery(document).ready(function($) {
                var modal = $('#ssmptms-filter-modal');
                var closeBtn = modal.find('.ssmptms-close');
                var form = $('#ssmptms-filter-form');

                $('#filter_action').on('change', function() {
                    if ($(this).val() === 'set_priority') {
                        $('#filter_priority_row').show();
                    } else {
                        $('#filter_priority_row').hide();
                    }
                });

                $('#ssmptms-add-filter').on('click', function() {
                    $('#ssmptms-filter-modal-title').text('<?php echo esc_js(__('Add Filter Rule', Constants::DOMAIN)); ?>');
                    form[0].reset();
                    $('#filter_id').val('');
                    $('#filter_is_active').prop('checked', true);
                    $('#filter_priority_row').hide();
                    modal.show();
                });

                $(document).on('click', '.ssmptms-edit-filter', function() {
                    var id = $(this).data('id');
                    var row = $(this).closest('tr');
                    
                    $('#ssmptms-filter-modal-title').text('<?php echo esc_js(__('Edit Filter Rule', Constants::DOMAIN)); ?>');
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
                    
                    var formData = {
                        action: 'ssmptms_filter_save',
                        ajax_nonce: '<?php echo wp_create_nonce('ssmptms-filter-save'); ?>',
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

                    $.post(ajaxurl, formData, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || 'Error saving filter');
                        }
                    });
                });

                $(document).on('click', '.ssmptms-toggle-filter', function() {
                    var id = $(this).data('id');
                    if (!confirm('<?php echo esc_js(__('Are you sure you want to toggle this filter?', Constants::DOMAIN)); ?>')) return;

                    $.post(ajaxurl, {
                        action: 'ssmptms_filter_toggle',
                        ajax_nonce: '<?php echo wp_create_nonce('ssmptms-filter-toggle'); ?>',
                        filter_id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                });

                $(document).on('click', '.ssmptms-delete-filter', function() {
                    var id = $(this).data('id');
                    if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this filter rule?', Constants::DOMAIN)); ?>')) return;

                    $.post(ajaxurl, {
                        action: 'ssmptms_filter_delete',
                        ajax_nonce: '<?php echo wp_create_nonce('ssmptms-filter-delete'); ?>',
                        filter_id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                });
            });
            </script>
            <?php
        }

        public function ajax_save_filter() {
            check_ajax_referer('ssmptms-filter-save', 'ajax_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }

            $filter_id = isset($_POST['filter_id']) ? intval($_POST['filter_id']) : 0;
            $data = [
                'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
                'search_subject' => isset($_POST['search_subject']) ? trim($_POST['search_subject']) : '',
                'search_body' => isset($_POST['search_body']) ? trim($_POST['search_body']) : '',
                'search_recipient' => isset($_POST['search_recipient']) ? trim($_POST['search_recipient']) : '',
                'action' => $this->get_filter_action($_POST),
                'priority_value' => isset($_POST['priority_value']) ? intval($_POST['priority_value']) : 0,
                'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
                'is_active' => isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1,
            ];

            if (empty($data['name'])) {
                wp_send_json_error(['message' => __('Name is required', Constants::DOMAIN)]);
            }

            if (!empty($data['search_subject']) && !$this->validate_pattern($data['search_subject'])) {
                wp_send_json_error(['message' => __('Invalid pattern in Subject field', Constants::DOMAIN)]);
            }
            if (!empty($data['search_body']) && !$this->validate_pattern($data['search_body'])) {
                wp_send_json_error(['message' => __('Invalid pattern in Body field', Constants::DOMAIN)]);
            }
            if (!empty($data['search_recipient']) && !$this->validate_pattern($data['search_recipient'])) {
                wp_send_json_error(['message' => __('Invalid pattern in Recipient field', Constants::DOMAIN)]);
            }

            if ($filter_id > 0) {
                Filter_Rules::get_instance()->update($filter_id, $data);
            } else {
                Filter_Rules::get_instance()->add($data);
            }

            wp_send_json_success();
        }

        private function validate_pattern($pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                return true;
            }
            if (preg_match('/^\/.*\/[a-z]*$/i', $pattern)) {
                return @preg_match($pattern, '') !== false;
            }
            return true;
        }

        private function get_filter_action($post_data) {
            if (isset($post_data['filter_action'])) {
                return sanitize_text_field($post_data['filter_action']);
            }
            if (isset($post_data['action'])) {
                $fallback = sanitize_text_field($post_data['action']);
                if (in_array($fallback, array_keys(Filter_Rules::get_actions()), true)) {
                    return $fallback;
                }
            }
            return 'bypass';
        }

        public function ajax_delete_filter() {
            check_ajax_referer('ssmptms-filter-delete', 'ajax_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }

            $filter_id = isset($_POST['filter_id']) ? intval($_POST['filter_id']) : 0;
            if ($filter_id > 0) {
                Filter_Rules::get_instance()->delete($filter_id);
            }

            wp_send_json_success();
        }

        public function ajax_toggle_filter() {
            check_ajax_referer('ssmptms-filter-toggle', 'ajax_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }

            $filter_id = isset($_POST['filter_id']) ? intval($_POST['filter_id']) : 0;
            if ($filter_id > 0) {
                $filter = Filter_Rules::get_instance()->get_by_id($filter_id);
                if ($filter) {
                    Filter_Rules::get_instance()->update($filter_id, [
                        'name' => $filter->name,
                        'search_subject' => $filter->search_subject,
                        'search_body' => $filter->search_body,
                        'search_recipient' => $filter->search_recipient,
                        'action' => $filter->action,
                        'priority_value' => $filter->priority_value,
                        'priority' => $filter->priority,
                        'is_active' => $filter->is_active ? 0 : 1,
                    ]);
                }
            }

            wp_send_json_success();
        }
    }
}

Filters_Settings::get_instance();
