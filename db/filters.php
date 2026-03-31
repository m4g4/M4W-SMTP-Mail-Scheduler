<?php
namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists(__NAMESPACE__ . '\\Filter_Rules', false)) {

    class Filter_Rules {
        private static $instance;
        private $table_name;

        public function __construct() {
            global $wpdb;
            $this->table_name = $wpdb->prefix . Constants::FILTER_DB_NAME;
        }

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function create_table() {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $this->table_name (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                search_subject TEXT DEFAULT NULL,
                search_body TEXT DEFAULT NULL,
                search_recipient TEXT DEFAULT NULL,
                action VARCHAR(50) NOT NULL DEFAULT 'bypass',
                priority_value INT DEFAULT 0,
                priority INT DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }

        public function table_exists() {
            global $wpdb;
            return $wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") === $this->table_name;
        }

        public function drop_table() {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS $this->table_name");
        }

        public function get_all() {
            global $wpdb;
            return $wpdb->get_results("SELECT * FROM $this->table_name ORDER BY priority DESC, created_at ASC");
        }

        public function get_active() {
            global $wpdb;
            return $wpdb->get_results("SELECT * FROM $this->table_name WHERE is_active = 1 ORDER BY priority DESC, created_at ASC");
        }

        public function get_by_id($id) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id));
        }

        public function add($data) {
            global $wpdb;
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'name' => sanitize_text_field($data['name']),
                    'search_subject' => isset($data['search_subject']) ? wp_unslash($data['search_subject']) : null,
                    'search_body' => isset($data['search_body']) ? wp_unslash($data['search_body']) : null,
                    'search_recipient' => isset($data['search_recipient']) ? wp_unslash($data['search_recipient']) : null,
                    'action' => sanitize_text_field($data['action']),
                    'priority_value' => isset($data['priority_value']) ? intval($data['priority_value']) : 0,
                    'priority' => isset($data['priority']) ? intval($data['priority']) : 0,
                    'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
            );
            return $result;
        }

        public function update($id, $data) {
            global $wpdb;
            return $wpdb->update(
                $this->table_name,
                [
                    'name' => sanitize_text_field($data['name']),
                    'search_subject' => isset($data['search_subject']) ? wp_unslash($data['search_subject']) : null,
                    'search_body' => isset($data['search_body']) ? wp_unslash($data['search_body']) : null,
                    'search_recipient' => isset($data['search_recipient']) ? wp_unslash($data['search_recipient']) : null,
                    'action' => sanitize_text_field($data['action']),
                    'priority_value' => isset($data['priority_value']) ? intval($data['priority_value']) : 0,
                    'priority' => isset($data['priority']) ? intval($data['priority']) : 0,
                    'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                ],
                ['id' => $id],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d'],
                ['%d']
            );
        }

        public function delete($id) {
            global $wpdb;
            return $wpdb->delete($this->table_name, ['id' => $id], ['%d']);
        }

        public static function get_actions() {
            return [
                'bypass' => __('Bypass Scheduling', Constants::DOMAIN),
                'do_not_send' => __('Do Not Send', Constants::DOMAIN),
                'set_priority' => __('Set Scheduler Priority', Constants::DOMAIN),
            ];
        }
    }
}
