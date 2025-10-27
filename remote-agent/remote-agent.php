<?php
/**
 * Plugin Name: Watchtower Agent
 * Plugin URI: https://github.com/abrayall/watchtower
 * Description: Remote management agent that provides REST API endpoints for WordPress management including user management, backups, and updates. Designed to be controlled by Watchtower Manager.
 * Version: 1.0.0
 * Author: abrayall
 * Author URI: https://github.com/abrayall
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: watchtower-agent
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Read version from version.properties file
 */
function watchtower_agent_get_version() {
    $version_file = plugin_dir_path(__FILE__) . 'version.properties';

    if (!file_exists($version_file)) {
        return '1.0.0'; // Fallback version
    }

    $properties = parse_ini_file($version_file);

    if ($properties === false || !isset($properties['major']) || !isset($properties['minor']) || !isset($properties['maintenance'])) {
        return '1.0.0'; // Fallback version
    }

    return $properties['major'] . '.' . $properties['minor'] . '.' . $properties['maintenance'];
}

// Define plugin constants
define('WATCHTOWER_AGENT_VERSION', watchtower_agent_get_version());
define('WATCHTOWER_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WATCHTOWER_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load plugin classes
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-watchtower-agent.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-rest-api-controller.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-user-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-backup-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-update-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-admin-settings.php';

// Initialize the plugin
function watchtower_agent_init() {
    $plugin = new Watchtower_Agent();
    $plugin->init();

    // Initialize admin settings (only in admin area)
    if (is_admin()) {
        $settings = new Watchtower_Agent_Admin_Settings();
        $settings->init();
    }
}
add_action('plugins_loaded', 'watchtower_agent_init');

// Add settings link on plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'watchtower_agent_add_settings_link');
function watchtower_agent_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=watchtower-agent') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Activation hook
register_activation_hook(__FILE__, 'watchtower_agent_activate');
function watchtower_agent_activate() {
    // Flush rewrite rules
    flush_rewrite_rules();

    // Generate application password for the first admin user
    require_once ABSPATH . 'wp-admin/includes/user.php';

    // Get the first administrator user
    $admin_users = get_users(array(
        'role' => 'administrator',
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC'
    ));

    if (!empty($admin_users)) {
        $admin_user = $admin_users[0];

        // Check if WP_Application_Passwords class exists (WordPress 5.6+)
        if (class_exists('WP_Application_Passwords')) {
            // Check if an app password with this name already exists
            $existing_passwords = WP_Application_Passwords::get_user_application_passwords($admin_user->ID);
            $password_exists = false;

            foreach ($existing_passwords as $existing) {
                if ($existing['name'] === 'watchtower-agent') {
                    $password_exists = true;
                    break;
                }
            }

            // Only create if it doesn't exist
            if (!$password_exists) {
                $result = WP_Application_Passwords::create_new_application_password($admin_user->ID, array(
                    'name' => 'watchtower-agent'
                ));

                if (!is_wp_error($result)) {
                    list($password, $password_data) = $result;

                    // Store the password temporarily (for 10 minutes) so it can be retrieved
                    set_transient('watchtower_agent_app_password', array(
                        'username' => $admin_user->user_login,
                        'password' => $password,
                        'created' => current_time('mysql')
                    ), 600); // 10 minutes

                    // Also log to error log for reference
                    error_log('Watchtower Agent: Application password created for user "' . $admin_user->user_login . '"');
                    error_log('Watchtower Agent: Password: ' . $password);
                    error_log('Watchtower Agent: This password is also stored in transient "watchtower_agent_app_password" for 10 minutes');

                    // Check if manager is installed locally
                    $manager_storage_file = WP_PLUGIN_DIR . '/remote-manager/includes/class-agent-storage.php';

                    if (file_exists($manager_storage_file)) {
                        // Manager is local - auto-register
                        error_log('Watchtower Agent: Manager detected locally, auto-registering...');
                        watchtower_agent_register_with_manager($admin_user->user_login, $password);
                    } else {
                        // Manager is remote - check if auto-register is enabled
                        $auto_register = get_option('watchtower_agent_auto_register', false);
                        $manager_url = get_option('watchtower_agent_manager_url', '');

                        if ($auto_register && !empty($manager_url)) {
                            error_log('Watchtower Agent: Auto-register enabled, registering with remote manager...');
                            watchtower_agent_register_with_manager($admin_user->user_login, $password);
                        } else {
                            error_log('Watchtower Agent: No local manager found. Please configure manager URL in Settings > Watchtower Agent');
                        }
                    }
                }
            }
        }
    }
}

/**
 * Register this agent with Watchtower Manager
 *
 * @return true|string Returns true on success, error message string on failure
 */
function watchtower_agent_register_with_manager($username, $password) {
    // Check if manager plugin is installed and active
    $manager_storage_file = WP_PLUGIN_DIR . '/remote-manager/includes/class-agent-storage.php';
    $manager_active = is_plugin_active('remote-manager/remote-manager.php');

    if (file_exists($manager_storage_file) && $manager_active && defined('WATCHTOWER_MANAGER_DATA_DIR')) {
        // Manager is on same WordPress instance and active - use direct storage access
        require_once $manager_storage_file;

        $storage = new Watchtower_Manager_Agent_Storage();

        $agent_data = array(
            'site_url' => get_site_url(),
            'admin_url' => get_admin_url(),
            'username' => $username,
            'password' => $password,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'agent_version' => WATCHTOWER_AGENT_VERSION,
        );

        $result = $storage->save_agent($agent_data);

        if ($result) {
            error_log('Watchtower Agent: Successfully registered with manager (local)');
            return true;
        } else {
            $error = 'Failed to save agent data to local manager';
            error_log('Watchtower Agent: ' . $error);
            return $error;
        }
    } else {
        // Manager is on remote WordPress - use REST API
        $manager_url = get_option('watchtower_agent_manager_url');

        if (empty($manager_url)) {
            $error = 'Manager URL not configured';
            error_log('Watchtower Agent: ' . $error);
            return $error;
        }

        $manager_url = $manager_url . '/wp-json/watchtower-manager/v1/register';

        $registration_data = array(
            'site_url' => get_site_url(),
            'admin_url' => get_admin_url(),
            'username' => $username,
            'password' => $password,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'agent_version' => WATCHTOWER_AGENT_VERSION,
        );

        $response = wp_remote_post($manager_url, array(
            'body' => json_encode($registration_data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            error_log('Watchtower Agent: Failed to register with manager: ' . $error);
            return $error;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            error_log('Watchtower Agent: Successfully registered with manager (remote)');
            return true;
        } else {
            $error = isset($data['error']) ? $data['error'] : 'Unknown error from manager';
            error_log('Watchtower Agent: Manager registration returned error: ' . $error);
            return $error;
        }
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'watchtower_agent_deactivate');
function watchtower_agent_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
