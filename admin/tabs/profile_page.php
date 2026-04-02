<?php
namespace Ssmptms;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists(__NAMESPACE__ . '\\Profile_Page', false)) {

    class Profile_Page {
        private static $instance;

        public function __construct() {
            add_action('admin_post_ssmptms_profile_activate', [$this, 'handle_profile_activation']);
            add_action('admin_post_ssmptms_profile_delete', [$this, 'handle_profile_delete']);
            add_action('admin_post_ssmptms_profile_save', [$this, 'handle_profile_save']);
        }
        public static function get_instance() {
		    if ( null === self::$instance ) {
			    self::$instance = new self();
		    }

		    return self::$instance;
	    }

        public function display_profile(?string $profile_id = null): void {
            $is_new = $profile_id === null;

            // Default profile values
            $profile = [
                'label'      => '',
                'from_email' => '',
                'from_name'  => '',
                'force_from_email' => false,
                'match_return_path' => false,
                'force_from_name' => false,
                'host'       => '',
                'port'       => 465,
                'encryption' => 'ssl',
                'autotls'    => true,
                'auth_mode'  => 'login',
                'username'   => '',
                'password'   => '',
            ];

            // Check for transient data if there was an error
            $transient_data = get_transient('ssmptms_profile_form_data');
            if ($transient_data && isset($_GET['error']) && $_GET['error'] == 1) {
                $profile = array_merge($profile, $transient_data);
            } elseif (!$is_new) {
                $profiles = get_option(Constants::PROFILES, []);
                if (isset($profiles[$profile_id])) {
                    $profile = array_merge($profile, $profiles[$profile_id]);
                }
            } else {
                $profile_id = guidv4();
            }

            echo '<div class="wrap"><h2>' . ($is_new ? __('Add SMTP Profile', Constants::DOMAIN) : __('Edit SMTP Profile', Constants::DOMAIN)) . '</h2>';

            echo_message_styles();
            $this->show_errors();

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ssmptms-profile-form">';
            wp_nonce_field('ssmptms_profile_save');
            echo '<input type="hidden" name="action" value="ssmptms_profile_save">';
            echo '<input type="hidden" name="profile_id" value="' . esc_attr($profile_id) . '">';

            echo '<div class="ssmptms-profile-grid">';

            echo '<div class="ssmptms-profile-card">';
            echo '<h3>' . esc_html__('Profile Details', Constants::DOMAIN) . '</h3>';
            echo '<p class="description">' . esc_html__('Set a friendly label and sender information for this profile.', Constants::DOMAIN) . '</p>';

            echo '<div class="ssmptms-field">';
            echo '<label for="label">' . esc_html__('Label', Constants::DOMAIN) . ' <span class="ssmptms-required">*</span></label>';
            echo '<input type="text" class="regular-text ssmptms-input" name="label" id="label" value="' . esc_attr($profile['label']) . '" required>';
            echo '<p class="description">' . esc_html__('Used in the profile list and log filters.', Constants::DOMAIN) . '</p>';
            echo '</div>';

            echo '<div class="ssmptms-field">';
            echo '<label for="from_email">' . esc_html__('From Email', Constants::DOMAIN) . ' <span class="ssmptms-required">*</span></label>';
            echo '<input type="email" class="regular-text ssmptms-input" name="from_email" id="from_email" value="' . esc_attr($profile['from_email']) . '" required autocomplete="email">';
            echo '<p class="description">' . esc_html__('This is the sender email address recipients will see.', Constants::DOMAIN) . '</p>';
            echo '</div>';

            echo '<div class="ssmptms-field ssmptms-field--checkbox">';
            echo '<label class="ssmptms-checkbox">';
            echo '<input type="checkbox" name="force_from_email" id="force_from_email" value="1" ' . checked(!empty($profile['force_from_email']), true, false) . '>';
            echo '<span>' . esc_html__('Always use the specified "From" email address, even if another is provided by the sender.', Constants::DOMAIN) . '</span>';
            echo '</label>';
            echo '</div>';

            echo '<div class="ssmptms-field ssmptms-field--checkbox">';
            echo '<label class="ssmptms-checkbox">';
            echo '<input type="checkbox" name="match_return_path" id="match_return_path" value="1" ' . checked(!empty($profile['match_return_path']), true, false) . '>';
            echo '<span>' . esc_html__('Automatically set the Return-Path header to match the "From" email address.', Constants::DOMAIN) . '</span>';
            echo '</label>';
            echo '</div>';

            echo '<div class="ssmptms-field">';
            echo '<label for="from_name">' . esc_html__('From Name', Constants::DOMAIN) . '</label>';
            echo '<input type="text" class="regular-text ssmptms-input" name="from_name" id="from_name" value="' . esc_attr($profile['from_name']) . '" autocomplete="name">';
            echo '<p class="description">' . esc_html__('Optional sender name shown in email clients.', Constants::DOMAIN) . '</p>';
            echo '</div>';

            echo '<div class="ssmptms-field ssmptms-field--checkbox">';
            echo '<label class="ssmptms-checkbox">';
            echo '<input type="checkbox" name="force_from_name" id="force_from_name" value="1" ' . checked(!empty($profile['force_from_name']), true, false) . '>';
            echo '<span>' . esc_html__('Always use the specified "From" name, even if another is provided by the sender.', Constants::DOMAIN) . '</span>';
            echo '</label>';
            echo '</div>';
            echo '</div>';

            echo '<div class="ssmptms-profile-card">';
            echo '<h3>' . esc_html__('SMTP Server', Constants::DOMAIN) . '</h3>';
            echo '<p class="description">' . esc_html__('Connection settings for your SMTP provider.', Constants::DOMAIN) . '</p>';

            echo '<div class="ssmptms-field-row">';
            echo '<div class="ssmptms-field">';
            echo '<label for="host">' . esc_html__('SMTP Host', Constants::DOMAIN) . ' <span class="ssmptms-required">*</span></label>';
            echo '<input type="text" class="regular-text ssmptms-input ssmptms-input--code" name="host" id="host" value="' . esc_attr($profile['host']) . '" required placeholder="smtp.example.com">';
            echo '</div>';

            echo '<div class="ssmptms-field ssmptms-field--sm">';
            echo '<label for="port">' . esc_html__('Port', Constants::DOMAIN) . ' <span class="ssmptms-required">*</span></label>';
            echo '<input type="number" class="small-text ssmptms-input ssmptms-input--sm" name="port" id="port" value="' . esc_attr($profile['port']) . '" required>';
            echo '</div>';
            echo '</div>';

            echo '<div class="ssmptms-field">';
            echo '<label for="encryption">' . esc_html__('Encryption', Constants::DOMAIN) . '</label>';
            echo '<select name="encryption" id="encryption" class="ssmptms-select">';
            echo '<option value="tls" ' . selected($profile['encryption'], 'tls', false) . '>TLS</option>';
            echo '<option value="ssl" ' . selected($profile['encryption'], 'ssl', false) . '>SSL</option>';
            echo '<option value="" ' . selected($profile['encryption'], '', false) . '>' . esc_html__('None', Constants::DOMAIN) . '</option>';
            echo '</select>';
            echo '</div>';

            echo '<div class="ssmptms-field ssmptms-field--checkbox">';
            echo '<label class="ssmptms-checkbox">';
            echo '<input type="checkbox" name="autotls" id="autotls" value="1" ' . checked(!empty($profile['autotls']), true, false) . '>';
            echo '<span>' . esc_html__('Enable Auto TLS (automatically upgrade to TLS if available)', Constants::DOMAIN) . '</span>';
            echo '</label>';
            echo '</div>';

            echo '<div class="ssmptms-field">';
            echo '<label for="ssmptms-auth_mode">' . esc_html__('Authentication', Constants::DOMAIN) . '</label>';
            echo '<select name="auth_mode" id="ssmptms-auth_mode" class="ssmptms-select">';
            echo '<option value="login" ' . selected($profile['auth_mode'], 'login', false) . '>' . esc_html__('Username & Password', Constants::DOMAIN) . '</option>';
            echo '<option value="none" ' . selected($profile['auth_mode'], 'none', false) . '>' . esc_html__('No authentication (trusted relay)', Constants::DOMAIN) . '</option>';
            echo '</select>';
            echo '</div>';

            echo '<div id="ssmptms-row-username" class="ssmptms-field">';
            echo '<label for="username">' . esc_html__('Username', Constants::DOMAIN) . '</label>';
            echo '<input type="text" class="regular-text ssmptms-input" name="username" id="username" value="' . esc_attr($profile['username']) . '" autocomplete="username">';
            echo '</div>';

            echo '<div id="ssmptms-row-password" class="ssmptms-field">';
            echo '<label for="password">' . esc_html__('Password', Constants::DOMAIN) . '</label>';
            echo '<input type="password" class="regular-text ssmptms-input" name="password" id="password" value="" autocomplete="new-password">';
            if (!$is_new) {
                $password_state_class = $profile['password'] ? 'ssmptms-password-set' : 'ssmptms-password-missing';
                $password_state_text = $profile['password'] ? __('Password is set.', Constants::DOMAIN) : __('No password set.', Constants::DOMAIN);
                echo '<p class="description ssmptms-password-state ' . esc_attr($password_state_class) . '">' . esc_html($password_state_text) . '</p>';
                echo '<p class="description">' . esc_html__('Enter a new password to change it, or leave blank to keep the current password.', Constants::DOMAIN) . '</p>';
            }
            echo '</div>';

            echo '</div>';

            echo '</div>';

            echo '<div class="ssmptms-profile-button-group">';
            echo '<button type="submit" class="button button-primary" id="ssmptms-profile-save">';
            echo '<span class="ssmptms-button-text">' . esc_html($is_new ? __('Add Profile', Constants::DOMAIN) : __('Save Profile', Constants::DOMAIN)) . '</span>';
            echo '</button>';
            echo '<div class="ssmptms-profile-back-button-wrapper">';
            echo '<a href="' . admin_url('options-general.php?page=' . Constants::SETTINGS_PAGE) . '">';
            echo '<button type="button" class="button button-secondary">&larr; ' . __('Back', Constants::DOMAIN) . '</button>';
            echo '</a>';
            echo '</div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';

            if ($transient_data) {
                delete_transient('ssmptms_profile_form_data');
            }
        }

        private function show_errors(): void {
            $errors = get_transient('ssmptms_profile_errors');
            if (!empty($errors)) {
                echo '<div class="smtp-mail-message smtp-mail-error"><ul>';
                foreach ($errors as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul></div>';
                delete_transient('ssmptms_profile_errors');
            }
        }

        public function handle_profile_save() {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ssmptms_profile_save')) {
                wp_die('Security check failed');
            }
        
            // Sanitize input
            $profile_id = sanitize_text_field($_POST['profile_id']);
            $label      = sanitize_text_field($_POST['label']);
            $from_email = sanitize_email($_POST['from_email']);
            $from_name  = sanitize_text_field($_POST['from_name']);
            $host       = sanitize_text_field($_POST['host']);
            $port       = intval($_POST['port']);
            $encryption = sanitize_text_field($_POST['encryption']);
            $autotls    = isset($_POST['autotls']) ? 1 : 0;
            $auth_mode  = sanitize_text_field($_POST['auth_mode'] ?? 'login');
            $username   = sanitize_text_field($_POST['username']);
            $password   = $_POST['password'] ?? '';
            $sender     = isset($_POST['match_return_path']) ? $from_email : '';
            $match_return_path = isset($_POST['match_return_path']) ? 1 : 0;
            $force_from_email = isset($_POST['force_from_email']) ? 1 : 0;
            $force_from_name = isset($_POST['force_from_name']) ? 1 : 0;
        
            $profiles = get_option(Constants::PROFILES, []);
            $existing_profile = !empty($profiles[$profile_id]);
        
            // Store form data for re-population in case of errors
            $form_data = [
                'label'      => $label,
                'from_email' => $from_email,
                'from_name'  => $from_name,
                'sender'     => $sender,
                'host'       => $host,
                'port'       => $port,
                'encryption' => $encryption,
                'autotls'    => $autotls,
                'auth_mode'  => $auth_mode,
                'username'   => $username,
                'force_from_email' => $force_from_email,
                'match_return_path' => $match_return_path,
                'force_from_name' => $force_from_name,
            ];
        
            // Validate input
            $errors = [];
            if (empty($label)) {
                $errors[] = __('Label is required.', Constants::DOMAIN);
            }
            if (!is_email($from_email)) {
                $errors[] = __('From Email must be a valid email address.', Constants::DOMAIN);
            }
            if (empty($host)) {
                $errors[] = __('SMTP Host is required.', Constants::DOMAIN);
            }
            if ($port <= 0) {
                $errors[] = __('Port must be a positive number.', Constants::DOMAIN);
            }
            if ($auth_mode !== 'none') {
                if (empty($username)) {
                    $errors[] = __('Username is required.', Constants::DOMAIN);
                }
                if (!$existing_profile && empty($password)) {
                    $errors[] = __('Password is required.', Constants::DOMAIN);
                }
            }
        
            // If validation failed
            if (!empty($errors)) {
                set_transient('ssmptms_profile_errors', $errors, 30);
                set_transient('ssmptms_profile_form_data', $form_data, 30);
                wp_safe_redirect(admin_url("admin.php?page=".Constants::PROFILE_EDIT_PAGE."&profile=$profile_id&error=1"));
                exit;
            }
        
            // Handle password encryption
            if ($auth_mode === 'none') {
                $username = '';
                $password = '';
            } elseif ($existing_profile) {
                // If no new password, reuse old one
                if (empty($password) && isset($profiles[$profile_id]['password'])) {
                    $password = decrypt_password($profiles[$profile_id]['password']);
                }
            }
            $encrypted_password = encrypt_password($password);
        
            // Save profile
            $profiles[$profile_id] = [
                'id'        => $profile_id,
                'label'     => $label,
                'from_email'=> $from_email,
                'from_name' => $from_name,
                'sender'    => $sender,
                'host'      => $host,
                'port'      => $port,
                'encryption'=> $encryption,
                'autotls'   => $autotls,
                'auth_mode' => $auth_mode,
                'username'  => $username,
                'password'  => $encrypted_password,
                'match_return_path' => $match_return_path,
                'force_from_email' => $force_from_email,
                'force_from_name' => $force_from_name,
            ];
            update_option(Constants::PROFILES, $profiles);
        
            // Set active profile if none exists
            if (get_option(Constants::PROFILE_ACTIVE, null) === null) {
                update_option(Constants::PROFILE_ACTIVE, $profile_id);
            }
        
            // Test SMTP connection
            $test_success = $this->test_connection($profiles[$profile_id]);
            if (!$test_success) {
                $errors[] = wp_kses_post(__('SMTP connection test <strong>failed</strong>. Check credentials or server settings.', Constants::DOMAIN));
            }
        
            if (!empty($errors)) {
                set_transient('ssmptms_profile_errors', $errors, 30);
                set_transient('ssmptms_profile_form_data', $form_data, 30);
                wp_safe_redirect(admin_url("admin.php?page=".Constants::PROFILE_EDIT_PAGE."&profile=$profile_id&error=1"));
                exit;
            }
        
            // Redirect to success page
            wp_safe_redirect(admin_url('options-general.php?page=' . Constants::SETTINGS_PAGE . '&saved=1'));
            exit;
        }

        public function handle_profile_activation() {
            $active_key = Constants::PROFILE_ACTIVE;
                
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
        
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ssmptms_profile_activate')) {
                wp_die('Security check failed');
            }
        
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            if (!$profile_id) {
                wp_die('No profile specified');
            }
        
            update_option($active_key, $profile_id);
        
            wp_safe_redirect(admin_url('options-general.php?page=' . Constants::SETTINGS_PAGE . '&activated=1'));
            exit;
        }

        public function handle_profile_delete() {
            $profiles_key = Constants::PROFILES;
        
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
        
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ssmptms_profile_delete')) {
                wp_die('Security check failed');
            }
        
            $profile_id = isset($_GET['profile']) ? sanitize_text_field($_GET['profile']) : null;
            if (!$profile_id) {
                wp_die('No profile specified');
            }
        
            $profiles = get_option($profiles_key, []);
            if (isset($profiles[$profile_id])) {
                unset($profiles[$profile_id]);
                update_option($profiles_key, $profiles);
            }
        
            wp_safe_redirect(admin_url('options-general.php?page=' . Constants::SETTINGS_PAGE . '&deleted=1'));
            exit;
        }

        private function test_connection($profile) {
            if (empty($profile) || !is_array($profile)) {
                return false;
            }
        
            $mailer = Mailer::get_instance()->prepare_mailer($profile);
            if ($mailer === null) {
                return false;
            }
        
            try {
                if (!$mailer->smtpConnect()) {
                    return false;
                }
                $mailer->smtpClose();
                return true;
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('SMTP connection test failed: ' . $e->getMessage());
                }
                return false;
            }
        }

    }
}

Profile_Page::get_instance();
