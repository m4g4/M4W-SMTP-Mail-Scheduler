<?php
/**
 * Plugin Name: M4W SMTP Mail Scheduler
 * Description: Intercepts WordPress emails, queues them, and sends via SMTP with retry logic and logging.
 * Version:     2.0.0
 * Author:      m4g4
 * License:     GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: m4w-smtp-mail-scheduler
 * Domain Path: /languages
 * 
 * Requires at least: 5.6
 * Tested up to: 6.8
 * Requires PHP: 8.2
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define('SSMPTMS_PLUGIN',              plugin_basename(__FILE__));
define('SSMPTMS_PLUGIN_LANG_PATH',    plugin_dir_path( __FILE__ ) . 'languages');

// TESTING MODE
define('SSMPTMS_TESTING_MODE', 0);

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

require_once __DIR__ . '/globals.php';
require_once __DIR__ . '/db/index.php';
require_once __DIR__ . '/install.php';
require_once __DIR__ . '/includes/index.php';
require_once __DIR__ . '/admin/index.php';

if (!function_exists('ssmptms_get_email_stats_by_subject')) {
    function ssmptms_get_email_stats_by_subject($subject) {
        global $wpdb;

        $counts = array(
            'queued' => 0,
            'failed' => 0,
            'sent'   => 0,
        );

        $subject = is_scalar($subject) ? trim((string) $subject) : '';

        if ('' === $subject) {
            return $counts;
        }

        $table = $wpdb->prefix . Ssmptms\Constants::QUEUE_DB_NAME;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS total FROM {$table} WHERE subject = %s AND testing = 0 GROUP BY status",
                $subject
            ),
            ARRAY_A
        );

        foreach ((array) $rows as $row) {
            $status = (string) $row['status'];
            $total  = (int) $row['total'];

            if ('sent' === $status || 'failed' === $status) {
                $counts[$status] = $total;
            } elseif ('queued' === $status || 'processing' === $status) {
                $counts['queued'] += $total;
            }
        }

        return $counts;
    }
}

register_activation_hook(__FILE__, 'ssmptms_activation');
register_deactivation_hook( __FILE__, 'ssmptms_deactivation' );

add_action('plugins_loaded', 'ssmptms_textdomain');
function ssmptms_textdomain() {
    load_plugin_textdomain(Ssmptms\Constants::DOMAIN, false, plugin_basename(__DIR__) . '/languages/' );
}

add_action('plugins_loaded', 'Ssmptms\migrate_cron_event_schedule');

function ssmptms_deactivation() {
    Ssmptms\unschedule_cron_event();
}

add_filter('cron_schedules', 'ssmptms_add_cron_interval');
function ssmptms_add_cron_interval($schedules) {
    $schedules[Ssmptms\Constants::SCHEDULER_SCHEDULE] = array(
        'interval' => Ssmptms\Constants::SCHEDULER_INTERVAL,
        'display'  => __('Every Minute', Ssmptms\Constants::DOMAIN),
    );

    $schedules['minute'] = array(
        'interval' => Ssmptms\Constants::SCHEDULER_INTERVAL,
        'display'  => __('Every Minute', Ssmptms\Constants::DOMAIN),
    );

    return $schedules;
}
?>
