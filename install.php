<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function ssmptms_activation() {
    if (!Ssmptms\Email_Queue::get_instance()->table_exists()) {
        Ssmptms\Email_Queue::get_instance()->create_table();
    }
    if (!Ssmptms\Filter_Rules::get_instance()->table_exists()) {
        Ssmptms\Filter_Rules::get_instance()->create_table();
    }

    // bump version
    update_option( Ssmptms\Constants::DB_VERSION, Ssmptms\Constants::VERSION );

    if (Ssmptms\Email_Queue::get_instance()->has_email_entries_for_sending()) {
        Ssmptms\schedule_cron_event();
    }
}

add_action( 'plugins_loaded', function() {
    $installed_version = get_option( Ssmptms\Constants::DB_VERSION, null );

    if ($installed_version === null) {
        throw new RuntimeException( 'M4W SMTP Mail Scheduler plugin not properly installed! Could not obtain version.' );
    }

    // DB upgrades come here

    // DB Migration from < 1.8.1 to >= 1.9.0
    // Create filter table if it doesn't exist (for plugin updates from versions before filters)
    if (!Ssmptms\Filter_Rules::get_instance()->table_exists()) {
        Ssmptms\Filter_Rules::get_instance()->create_table();
    }
});