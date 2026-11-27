<?php
namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Log_Table extends \WP_List_Table {
    private $per_page = 50;
    private array $request_args;

    public function __construct(?array $request_args = null) {
        $this->request_args = $request_args ?? wp_unslash($_GET);

        parent::__construct([
            'singular' => __('Email', Constants::DOMAIN),
            'plural'   => __('Emails', Constants::DOMAIN),
            'ajax'     => false,
        ]);
    }

    private function get_request_value(string $key): string {
        return isset($this->request_args[$key]) ? sanitize_text_field((string) $this->request_args[$key]) : '';
    }

    public function get_pagenum() {
        $pagenum = absint($this->get_request_value('paged'));

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    public function get_columns(): array {
        $columns = [
            'cb'              => '<input type="checkbox" />',
            'recipient_email' => __('Recipient', Constants::DOMAIN),
            'subject'         => __('Subject', Constants::DOMAIN),
            'profile'         => __('Profile', Constants::DOMAIN),
            'status'          => __('Status', Constants::DOMAIN),
            'scheduled_at'    => __('Scheduled At', Constants::DOMAIN),
            'last_attempt_at' => __('Last Attempt At', Constants::DOMAIN),
            'priority'        => __('Priority', Constants::DOMAIN),
            'actions'         => __('Actions', Constants::DOMAIN),
        ];

        return $columns;
    }

    public function get_sortable_columns(): array {
        return [
            'status'          => ['status', false],
            'priority'        => ['priority', false],
            'scheduled_at'    => ['scheduled_at', true],
            'last_attempt_at' => ['last_attempt_at', true],
            'recipient_email' => ['recipient_email', true],
            'subject'         => ['subject', false],
            'profile'         => ['profile_settings', false],
        ];
    }

    public function get_bulk_actions() {
        return [
            'retry'   => __('Retry', Constants::DOMAIN),
            'delete'  => __('Delete', Constants::DOMAIN),
            'front'   => __('Put to Front', Constants::DOMAIN),
        ];
    }

    protected function prepare_column_headers(): void {
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        // Filters
        $status_filter  = $this->get_request_value('status_filter');
        $profile_filter = $this->get_request_value('profile_filter');
        $search_query   = $this->get_request_value('s');

        $statuses = [
            'queued'     => __('Queued', Constants::DOMAIN),
            'processing' => __('Processing', Constants::DOMAIN),
            'sent'       => __('Sent', Constants::DOMAIN),
            'failed'     => __('Failed', Constants::DOMAIN),
            'filtered'   => __('Filtered', Constants::DOMAIN),
        ];

        echo '<div class="alignleft actions">';

        echo '<select name="status_filter" id="status_filter">';
        echo '<option value="">' . esc_html__('All Statuses', Constants::DOMAIN) . '</option>';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($status_filter, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Profile filter dropdown
        $profile_labels = Email_Queue::get_instance()->get_profile_labels();
        $profile_labels = array_unique(array_filter($profile_labels));

        echo '<select name="profile_filter" id="profile_filter">';
        echo '<option value="">' . esc_html__('All Profiles', Constants::DOMAIN) . '</option>';
        foreach ($profile_labels as $label) {
            echo '<option value="' . esc_attr($label) . '"' . selected($profile_filter, $label, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        // Search input
        echo '<input type="search" name="s" placeholder="' . esc_attr__('Search email or subject...', Constants::DOMAIN) . '" value="' . esc_attr($search_query) . '" />';

        // Submit button
        submit_button(__('Filter', Constants::DOMAIN), 'button', 'filter_action', false);

        echo '</div>';
    }


    public function prepare_items(): void {
        $this->prepare_column_headers();

        $search_query   = $this->get_request_value('s');
        $status_filter  = $this->get_request_value('status_filter');
        $profile_filter = $this->get_request_value('profile_filter');

        $orderby = $this->get_request_value('orderby') ?: 'scheduled_at';
        $allowed_orderby = array_keys($this->get_sortable_columns());
        $allowed_orderby[] = 'scheduled_at';

        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'scheduled_at';
        }

        $order = strtolower($this->get_request_value('order'));
        $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        $queue       = Email_Queue::get_instance();
        $total_items = $queue->get_emails_count($status_filter, $profile_filter, $search_query);
        $total_pages = max(1, (int) ceil($total_items / $this->per_page));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $this->per_page,
            'total_pages' => $total_pages,
        ]);

        $current_page = $this->get_pagenum();
        $this->request_args['paged'] = $current_page;
        $offset = ($current_page - 1) * $this->per_page;

        $result = $queue->get_emails($this->per_page, $offset, $orderby, $order, $status_filter, $profile_filter, $search_query, $total_items);

        $this->items = $result[0];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'recipient_email':
                $emails = maybe_unserialize($item->recipient_email);
                return esc_html(is_array($emails) ? implode(', ', $emails) : $emails);
            case 'subject':
            case 'scheduled_at':
            case 'last_attempt_at':
                return esc_html($item->$column_name);
            case 'profile':
                $profile = json_decode($item->profile_settings, true);
                return esc_html($profile['label'] ?? __('Unknown Profile', Constants::DOMAIN));
            case 'priority':
                $colors = ['0' => 'gray', '1' => 'blue', '2' => 'orange', '3' => 'red'];
                $color  = $colors[$item->priority] ?? 'black';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html($item->priority) . '</span>';
            case 'status':
                $status = Constants::get_status_text($item->status) ?? ucfirst($item->status);
                $status_colors = [
                    'queued'     => 'orange',
                    'processing' => 'orange',
                    'sent'       => 'green',
                    'failed'     => 'red',
                    'filtered'   => 'gray',
                ];
                $color = $status_colors[$item->status] ?? 'gray';
                return '<span style="color:' . esc_attr($color) . '">' . esc_html($status) . '</span>';
            case 'actions':
                return $this->row_actions($this->get_row_actions($item));
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="email_ids[]" value="%d" />',
            absint($item->email_id)
        );
    }

    private function get_action_url(array $args): string {
        $base = settings_page_url() . '&tab=log';
        $keys = ['s', 'status_filter', 'profile_filter', 'orderby', 'order', 'paged'];
        $preserve = array_intersect_key($this->request_args, array_flip($keys));

        return add_query_arg(array_merge($preserve, $args), $base);
    }

    public function get_row_actions($item): array {
        $actions = [];

        // Retry (only for failed)
        if ($item->status === 'failed') {
            $actions['retry'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->get_action_url([
                    'action'   => 'ssmptms_retry',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('ssmptms_retry_' . $item->email_id),
                ])),
                __('Retry', Constants::DOMAIN)
            );
        }

        // Remove
        $actions['remove'] = sprintf(
            '<a href="%s" style="color:red;">%s</a>',
            esc_url($this->get_action_url([
                'action'   => 'ssmptms_remove',
                'email_id' => $item->email_id,
                '_wpnonce' => wp_create_nonce('ssmptms_remove_' . $item->email_id),
            ])),
            __('Remove', Constants::DOMAIN)
        );

        // Put to front (queued only)
        if ($item->status === 'queued') {
            $actions['front'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->get_action_url([
                    'action'   => 'ssmptms_front',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('ssmptms_front_' . $item->email_id),
                ])),
                __('Put to Front', Constants::DOMAIN)
            );
        }

        // Send now (queued only)
        if ($item->status === 'queued') {
            $actions['send-now'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->get_action_url([
                    'action'   => 'ssmptms_send_now',
                    'email_id' => $item->email_id,
                    '_wpnonce' => wp_create_nonce('ssmptms_send_now_' . $item->email_id),
                ])),
                __('Send Now', Constants::DOMAIN)
            );
        }

        return $actions;
    }
}
?>
