<?php
/**
 * Plugin Name: Watchtower Agent
 * Plugin URI: https://github.com/abrayall/watchtower
 * Description: Remote management agent that provides REST API endpoints for WordPress management including user management, backups, and updates. Designed to be controlled by Watchtower Manager.
 * Version: 1.0.0
 * Author: Brayall, LLC
 * Author URI: https://github.com/abrayall
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: watchtower-agent
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

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

define('WATCHTOWER_AGENT_VERSION', watchtower_agent_get_version());
define('WATCHTOWER_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WATCHTOWER_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-watchtower-agent.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-rest-api-controller.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-user-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-backup-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-update-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-log-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-file-management.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/endpoints/class-audit-endpoint.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once WATCHTOWER_AGENT_PLUGIN_DIR . 'includes/class-audit-logger.php';

function watchtower_agent_init() {
    $plugin = new Watchtower_Agent();
    $plugin->init();

    if (is_admin()) {
        $settings = new Watchtower_Agent_Admin_Settings();
        $settings->init();
    }

    WP_Remote_Agent_Audit_Logger::get_instance();
}
add_action('plugins_loaded', 'watchtower_agent_init');

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'watchtower_agent_add_settings_link');
function watchtower_agent_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=watchtower-agent') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_filter('wp_is_application_passwords_available', 'watchtower_agent_enable_app_passwords');
function watchtower_agent_enable_app_passwords($available) {
    watchtower_agent_check_wordfence_app_password_block();

    return true;
}

add_filter('application_password_is_api_request', 'watchtower_agent_enable_app_passwords_api', 10, 1);
function watchtower_agent_enable_app_passwords_api($is_api_request) {
    watchtower_agent_check_wordfence_app_password_block();

    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'watchtower-agent') !== false) {
        return true;
    }
    return $is_api_request;
}

register_activation_hook(__FILE__, 'watchtower_agent_activate');
function watchtower_agent_activate() {
    flush_rewrite_rules();

    $app_passwords_available = wp_is_application_passwords_available();

    if (!$app_passwords_available) {
        watchtower_agent_enable_app_passwords_in_config();
    }

    watchtower_agent_check_wordfence_app_password_block();

    watchtower_agent_ensure_updraftplus();

    $agent_manager_url = get_option('watchtower_agent_manager_url');
    if (!empty($agent_manager_url)) {
        update_option('watchtower_manager_url', $agent_manager_url);
        if (empty(get_option('watchtower_manager_key'))) {
            update_option('watchtower_manager_key', 'local-manager');
        }
    }

    if (!wp_next_scheduled('watchtower_agent_weekly_backup')) {
        wp_schedule_event(strtotime('next Sunday 23:00'), 'weekly', 'watchtower_agent_weekly_backup');
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';

    $admin_users = get_users(array(
        'role' => 'administrator',
        'number' => 1,
        'orderby' => 'ID',
        'order' => 'ASC'
    ));

    if (!empty($admin_users)) {
        $admin_user = $admin_users[0];

        if (class_exists('WP_Application_Passwords')) {
            $existing_passwords = WP_Application_Passwords::get_user_application_passwords($admin_user->ID);
            $password_exists = false;

            foreach ($existing_passwords as $existing) {
                if ($existing['name'] === 'watchtower-agent') {
                    $password_exists = true;
                    break;
                }
            }

            if (!$password_exists) {
                $result = WP_Application_Passwords::create_new_application_password($admin_user->ID, array(
                    'name' => 'watchtower-agent'
                ));

                if (!is_wp_error($result)) {
                    list($password, $password_data) = $result;

                    set_transient('watchtower_agent_app_password', array(
                        'username' => $admin_user->user_login,
                        'password' => $password,
                        'created' => current_time('mysql')
                    ), 600); // 10 minutes

                    error_log('Watchtower Agent: Application password created for user "' . $admin_user->user_login . '"');
                    error_log('Watchtower Agent: Password: ' . $password);
                    error_log('Watchtower Agent: This password is also stored in transient "watchtower_agent_app_password" for 10 minutes');

                    $manager_storage_file = WP_PLUGIN_DIR . '/remote-manager/includes/class-agent-storage.php';

                    if (file_exists($manager_storage_file)) {
                        error_log('Watchtower Agent: Manager detected locally, auto-registering...');
                        watchtower_agent_register_with_manager($admin_user->user_login, $password);
                    } else {
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
    $manager_storage_file = WP_PLUGIN_DIR . '/watchtower-manager/includes/class-agent-storage.php';
    $manager_active = is_plugin_active('watchtower-manager/manager.php');

    if (file_exists($manager_storage_file) && $manager_active && defined('WATCHTOWER_MANAGER_DATA_DIR')) {
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

            update_option('watchtower_manager_url', get_site_url());
            update_option('watchtower_external_site_url', $agent_data['site_url']);  // URL manager knows us by
            if (empty(get_option('watchtower_manager_key'))) {
                update_option('watchtower_manager_key', 'local-manager');
            }

            return true;
        } else {
            $error = 'Failed to save agent data to local manager';
            error_log('Watchtower Agent: ' . $error);
            return $error;
        }
    } else {
        $manager_base_url = get_option('watchtower_agent_manager_url');

        if (empty($manager_base_url)) {
            $error = 'Manager URL not configured';
            error_log('Watchtower Agent: ' . $error);
            return $error;
        }

        $manager_url = $manager_base_url . '/wp-json/watchtower-manager/v1/register';

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

            update_option('watchtower_manager_url', $manager_base_url);
            update_option('watchtower_external_site_url', $registration_data['site_url']);  // URL manager knows us by
            if (empty(get_option('watchtower_manager_key'))) {
                update_option('watchtower_manager_key', 'local-manager');
            }

            return true;
        } else {
            $error = isset($data['error']) ? $data['error'] : 'Unknown error from manager';
            error_log('Watchtower Agent: Manager registration returned error: ' . $error);
            return $error;
        }
    }
}

register_deactivation_hook(__FILE__, 'watchtower_agent_deactivate');
function watchtower_agent_deactivate() {
    flush_rewrite_rules();

    $timestamp = wp_next_scheduled('watchtower_agent_weekly_backup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'watchtower_agent_weekly_backup');
    }
}

/**
 * Try to enable Application Passwords in wp-config.php
 */
function watchtower_agent_enable_app_passwords_in_config() {
    $config_file = null;

    if (file_exists(ABSPATH . 'wp-config.php')) {
        $config_file = ABSPATH . 'wp-config.php';
    }
    elseif (file_exists(dirname(ABSPATH) . '/wp-config.php')) {
        $config_file = dirname(ABSPATH) . '/wp-config.php';
    }

    if (!$config_file || !is_writable($config_file)) {
        error_log('Watchtower Agent: Cannot enable Application Passwords - wp-config.php not writable');
        return false;
    }

    $config_content = file_get_contents($config_file);

    if ($config_content === false) {
        error_log('Watchtower Agent: Cannot read wp-config.php');
        return false;
    }

    if (preg_match("/define\s*\(\s*['\"]WP_APPLICATION_PASSWORDS['\"]\s*,\s*false\s*\)/i", $config_content)) {
        $config_content = preg_replace(
            "/define\s*\(\s*['\"]WP_APPLICATION_PASSWORDS['\"]\s*,\s*false\s*\)\s*;/i",
            "// define( 'WP_APPLICATION_PASSWORDS', false ); // Disabled by Watchtower Agent",
            $config_content
        );

        $result = file_put_contents($config_file, $config_content);

        if ($result !== false) {
            error_log('Watchtower Agent: Successfully enabled Application Passwords in wp-config.php');
            return true;
        } else {
            error_log('Watchtower Agent: Failed to write changes to wp-config.php');
            return false;
        }
    }

    return true; // Already enabled or not disabled
}

/**
 * Ensure UpdraftPlus plugin is installed and activated
 */
function watchtower_agent_ensure_updraftplus() {
    if (is_plugin_active('updraftplus/updraftplus.php')) {
        error_log('Watchtower Agent: UpdraftPlus is already active');
        return true;
    }

    $installed_plugins = get_plugins();
    if (isset($installed_plugins['updraftplus/updraftplus.php'])) {
        $result = activate_plugin('updraftplus/updraftplus.php');
        if (is_wp_error($result)) {
            error_log('Watchtower Agent: Failed to activate UpdraftPlus: ' . $result->get_error_message());
            return false;
        }
        error_log('Watchtower Agent: UpdraftPlus activated successfully');
        return true;
    }

    error_log('Watchtower Agent: Installing UpdraftPlus...');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $api = plugins_api('plugin_information', array(
        'slug' => 'updraftplus',
        'fields' => array(
            'short_description' => false,
            'sections' => false,
            'requires' => false,
            'rating' => false,
            'ratings' => false,
            'downloaded' => false,
            'last_updated' => false,
            'added' => false,
            'tags' => false,
            'compatibility' => false,
            'homepage' => false,
            'donate_link' => false,
        ),
    ));

    if (is_wp_error($api)) {
        error_log('Watchtower Agent: Failed to get UpdraftPlus info: ' . $api->get_error_message());
        return false;
    }

    $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
    $result = $upgrader->install($api->download_link);

    if (is_wp_error($result)) {
        error_log('Watchtower Agent: Failed to install UpdraftPlus: ' . $result->get_error_message());
        return false;
    }

    if ($result !== true) {
        error_log('Watchtower Agent: Failed to install UpdraftPlus');
        return false;
    }

    $activate_result = activate_plugin('updraftplus/updraftplus.php');
    if (is_wp_error($activate_result)) {
        error_log('Watchtower Agent: Failed to activate UpdraftPlus: ' . $activate_result->get_error_message());
        return false;
    }

    error_log('Watchtower Agent: UpdraftPlus installed and activated successfully');
    return true;
}

/**
 * Weekly backup cron handler
 */
add_action('watchtower_agent_weekly_backup', 'watchtower_agent_run_weekly_backup');
function watchtower_agent_run_weekly_backup() {
    if (!class_exists('UpdraftPlus')) {
        error_log('Watchtower Agent: Cannot run weekly backup - UpdraftPlus not available');
        return;
    }

    error_log('Watchtower Agent: Running weekly backup...');

    global $updraftplus;

    $result = $updraftplus->boot_backup(true, true);

    if ($result) {
        error_log('Watchtower Agent: Weekly backup started successfully');

        wp_schedule_single_event(time() + 300, 'watchtower_agent_prune_backups');
    } else {
        error_log('Watchtower Agent: Failed to start weekly backup');
    }
}

/**
 * Prune old backups to keep only the 3 most recent
 */
add_action('watchtower_agent_prune_backups', 'watchtower_agent_prune_old_backups');
function watchtower_agent_prune_old_backups() {
    if (!class_exists('UpdraftPlus')) {
        error_log('Watchtower Agent: Cannot prune backups - UpdraftPlus not available');
        return;
    }

    error_log('Watchtower Agent: Pruning old backups...');

    global $updraftplus_admin;
    if (!$updraftplus_admin || !method_exists($updraftplus_admin, 'delete_set')) {
        require_once WP_PLUGIN_DIR . '/updraftplus/admin.php';
        $updraftplus_admin = new UpdraftPlus_Admin();
    }

    $backup_history = UpdraftPlus_Backup_History::get_history();

    krsort($backup_history);

    $backups_to_keep = 3;
    $count = 0;

    foreach ($backup_history as $timestamp => $backup) {
        $count++;
        if ($count > $backups_to_keep) {
            $nonce = isset($backup['nonce']) ? $backup['nonce'] : '';
            error_log('Watchtower Agent: Deleting old backup from ' . date('Y-m-d H:i:s', $timestamp));

            $opts = array(
                'what' => $timestamp,
                'nonce' => $nonce,
            );

            $updraftplus_admin->delete_set($opts);
        }
    }

    error_log('Watchtower Agent: Backup pruning complete');
}

/**
 * Execute restore process using UpdraftPlus (called in background)
 */
add_action('watchtower_agent_execute_restore', 'watchtower_agent_execute_restore_callback');
function watchtower_agent_execute_restore_callback($timestamp) {
    if (!class_exists('UpdraftPlus')) {
        error_log('Watchtower Agent: Cannot execute restore - UpdraftPlus not available');
        return;
    }

    error_log('Watchtower Agent: Starting restore for backup timestamp: ' . $timestamp);

    $job_id = md5(time() . rand());
    update_site_option('updraft_restore_in_progress', $job_id);
    update_site_option('watchtower_restore_progress', 10);

    global $updraftplus;

    $all_backups = UpdraftPlus_Backup_History::get_history();

    if (!isset($all_backups[$timestamp])) {
        error_log('Watchtower Agent: Backup not found: ' . $timestamp);
        delete_site_option('updraft_restore_in_progress');
        delete_site_option('watchtower_restore_progress');
        return;
    }

    $backup_history = $all_backups[$timestamp];

    $backup_history['timestamp'] = $timestamp;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once WP_PLUGIN_DIR . '/updraftplus/restorer.php';
    require_once WP_PLUGIN_DIR . '/updraftplus/includes/updraft-restorer-skin.php';
    require_once WP_PLUGIN_DIR . '/updraftplus/includes/class-filesystem-functions.php';

    $entities_to_restore = array();
    if (!empty($backup_history['db'])) $entities_to_restore['db'] = 'db';
    if (!empty($backup_history['plugins'])) $entities_to_restore['plugins'] = 'plugins';
    if (!empty($backup_history['themes'])) $entities_to_restore['themes'] = 'themes';
    if (!empty($backup_history['uploads'])) $entities_to_restore['uploads'] = 'uploads';
    if (!empty($backup_history['others'])) $entities_to_restore['others'] = 'others';

    error_log('Watchtower Agent: Restoring entities: ' . implode(', ', array_keys($entities_to_restore)));

    $updraft_dir = $updraftplus->backups_dir_location();
    $missing_files = array();

    if (!empty($backup_history['db'])) {
        $db_file = $updraft_dir . '/' . $backup_history['db'];
        if (!file_exists($db_file)) {
            $missing_files[] = 'database (' . $backup_history['db'] . ')';
        }
    }

    if (!empty($backup_history['plugins']) && is_array($backup_history['plugins'])) {
        foreach ($backup_history['plugins'] as $file) {
            if (!file_exists($updraft_dir . '/' . $file)) {
                $missing_files[] = 'plugins (' . $file . ')';
            }
        }
    }

    if (!empty($missing_files)) {
        $error_msg = 'Backup files are missing: ' . implode(', ', $missing_files) . '. This backup cannot be restored.';
        error_log('Watchtower Agent: ' . $error_msg);
        update_site_option('watchtower_restore_progress', -1);
        delete_site_option('updraft_restore_in_progress');
        return; // Exit early
    }

    $app_passwords_backup = null;
    if (isset($entities_to_restore['db'])) {
        $app_passwords_backup = array();
        $users = get_users();

        foreach ($users as $user) {
            $passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
            if (!empty($passwords)) {
                $app_passwords_backup[$user->ID] = array(
                    'user_login' => $user->user_login,
                    'passwords' => $passwords
                );
            }
        }

        $backup_file = WP_CONTENT_DIR . '/watchtower-app-passwords-backup.json';
        file_put_contents($backup_file, json_encode($app_passwords_backup));
        error_log('Watchtower Agent: Saved ' . count($app_passwords_backup) . ' user application passwords');

        $manager_settings = array(
            'manager_url' => get_option('watchtower_manager_url'),
            'manager_key' => get_option('watchtower_manager_key'),
            'external_site_url' => get_option('watchtower_external_site_url'),
        );
        $manager_settings_file = WP_CONTENT_DIR . '/watchtower-manager-settings-backup.json';
        file_put_contents($manager_settings_file, json_encode($manager_settings));
        error_log('Watchtower Agent: Saved manager settings: ' . $manager_settings['manager_url']);
    }

    $cleanup_dirs = array(
        WP_CONTENT_DIR . '/wflogs', // Wordfence logs
        WP_CONTENT_DIR . '/upgrade', // WordPress upgrade temp directory
    );

    foreach ($cleanup_dirs as $dir) {
        if (is_dir($dir)) {
            error_log('Watchtower Agent: Removing conflicting directory before restore: ' . $dir);
            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    @$todo($fileinfo->getRealPath());
                }
                @rmdir($dir);
            } catch (Exception $e) {
                error_log('Watchtower Agent: Failed to clean up directory: ' . $dir . ' - ' . $e->getMessage());
            }
        }
    }

    try {
        update_site_option('watchtower_restore_progress', 20);

        error_log('Watchtower Agent: Setting up WP_Filesystem');
        $url_parameters = array(
            'backup_timestamp' => $timestamp,
            'job_id' => $job_id
        );

        $updraftplus->log("Ensuring WP_Filesystem is setup for a restore");

        if (!defined('FS_METHOD')) {
            define('FS_METHOD', 'direct');
        }

        if (!WP_Filesystem()) {
            error_log('Watchtower Agent: Failed to initialize WP_Filesystem');
            update_site_option('watchtower_restore_progress', -1);
            delete_site_option('updraft_restore_in_progress');
            return;
        }

        $updraftplus->log("WP_Filesystem is setup and ready for a restore");
        error_log('Watchtower Agent: WP_Filesystem initialized successfully');

        update_site_option('watchtower_restore_progress', 25);

        $updraftplus->nonce = $job_id;
        $updraftplus->jobdata_set('job_type', 'restore');
        $updraftplus->jobdata_set('backup_timestamp', $timestamp);
        $updraftplus->jobdata_set('job_time_ms', microtime(true));

        foreach ($entities_to_restore as $entity) {
            $updraftplus->jobdata_set('restore_' . $entity, 1);
        }

        error_log('Watchtower Agent: Jobdata set for restore: ' . print_r($updraftplus->jobdata_getarray($job_id), true));

        global $updraftplus_restorer;
        $skin = new Updraft_Restorer_Skin();
        $updraftplus_restorer = new Updraft_Restorer($skin, $backup_history, false, array(), null);

        update_site_option('watchtower_restore_progress', 30);

        $updraftplus->log('Watchtower Agent: Calling perform_restore() with entities: ' . implode(', ', $entities_to_restore));
        $updraftplus->log('Watchtower Agent: Backup set structure: ' . print_r($backup_history, true));
        error_log('Watchtower Agent: Calling perform_restore() with entities: ' . implode(', ', $entities_to_restore));
        error_log('Watchtower Agent: Backup history: ' . print_r($backup_history, true));

        $result = $updraftplus_restorer->perform_restore($entities_to_restore, array());

        $updraftplus->log('Watchtower Agent: perform_restore() returned: ' . var_export($result, true));
        error_log('Watchtower Agent: perform_restore() returned: ' . var_export($result, true));

        $updraftplus_restorer->post_restore_clean_up($result);

        if ($result && !is_wp_error($result)) {
            error_log('Watchtower Agent: Restore completed successfully');

            if (isset($entities_to_restore['db'])) {
                $backup_file = WP_CONTENT_DIR . '/watchtower-app-passwords-backup.json';
                if (file_exists($backup_file)) {
                    $saved_passwords = json_decode(file_get_contents($backup_file), true);

                    if (!empty($saved_passwords)) {
                        error_log('Watchtower Agent: Restoring application passwords for ' . count($saved_passwords) . ' users');

                        foreach ($saved_passwords as $user_id => $user_data) {
                            $user = get_user_by('login', $user_data['user_login']);

                            if ($user) {
                                WP_Application_Passwords::delete_all_application_passwords($user->ID);

                                foreach ($user_data['passwords'] as $password_data) {
                                    $existing = get_user_meta($user->ID, '_application_passwords', true);
                                    if (!is_array($existing)) {
                                        $existing = array();
                                    }

                                    $existing[] = $password_data;
                                    update_user_meta($user->ID, '_application_passwords', $existing);
                                }

                                error_log('Watchtower Agent: Restored ' . count($user_data['passwords']) . ' application passwords for user: ' . $user_data['user_login']);
                            } else {
                                error_log('Watchtower Agent: User not found after restore: ' . $user_data['user_login']);
                            }
                        }

                        @unlink($backup_file);
                    }
                }

                $manager_settings_file = WP_CONTENT_DIR . '/watchtower-manager-settings-backup.json';
                if (file_exists($manager_settings_file)) {
                    $manager_settings = json_decode(file_get_contents($manager_settings_file), true);
                    if (!empty($manager_settings['manager_url'])) {
                        update_option('watchtower_manager_url', $manager_settings['manager_url']);
                        error_log('Watchtower Agent: Restored manager URL: ' . $manager_settings['manager_url']);
                    }
                    if (!empty($manager_settings['manager_key'])) {
                        update_option('watchtower_manager_key', $manager_settings['manager_key']);
                        error_log('Watchtower Agent: Restored manager key');
                    }
                    if (!empty($manager_settings['external_site_url'])) {
                        update_option('watchtower_external_site_url', $manager_settings['external_site_url']);
                        error_log('Watchtower Agent: Restored external site URL: ' . $manager_settings['external_site_url']);
                    }
                    @unlink($manager_settings_file);
                }

                $admin_user = get_user_by('login', 'admin');
                if ($admin_user) {
                    $existing_passwords = WP_Application_Passwords::get_user_application_passwords($admin_user->ID);
                    foreach ($existing_passwords as $password) {
                        if (strpos($password['name'], 'watchtower') !== false) {
                            WP_Application_Passwords::delete_application_password($admin_user->ID, $password['uuid']);
                        }
                    }

                    $new_password = WP_Application_Passwords::create_new_application_password($admin_user->ID, array('name' => 'watchtower'));
                    if (!is_wp_error($new_password) && is_array($new_password) && isset($new_password[0])) {
                        $plaintext_password = is_string($new_password[0]) ? $new_password[0] : (isset($new_password[0]['password']) ? $new_password[0]['password'] : null);

                        if (!$plaintext_password || !is_string($plaintext_password)) {
                            error_log('Watchtower Agent: Failed to extract plaintext password - unexpected format: ' . print_r($new_password, true));
                        } else {
                            error_log('Watchtower Agent: Created new watchtower application password: ' . $plaintext_password);
                        }

                        if ($plaintext_password && is_string($plaintext_password)) {

                        file_put_contents(WP_CONTENT_DIR . '/watchtower-api-password.txt', $plaintext_password);

                        $manager_url = get_option('watchtower_manager_url');
                        $manager_key = get_option('watchtower_manager_key');
                        $external_site_url = get_option('watchtower_external_site_url');

                        $restore_log = WP_CONTENT_DIR . '/watchtower-restore.log';
                        file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Created new password: $plaintext_password\n", FILE_APPEND);
                        file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Manager URL: $manager_url\n", FILE_APPEND);
                        file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Manager Key: $manager_key\n", FILE_APPEND);
                        file_put_contents($restore_log, date('Y-m-d H:i:s') . " - External Site URL: $external_site_url\n", FILE_APPEND);

                        if ($manager_url && $manager_key && $external_site_url) {
                            $response = wp_remote_post($manager_url . '/?rest_route=/watchtower-manager/v1/update-agent-password', array(
                                'body' => json_encode(array(
                                    'site_url' => $external_site_url,  // Use external URL manager knows about, not internal site_url()
                                    'password' => $plaintext_password,
                                )),
                                'headers' => array(
                                    'Content-Type' => 'application/json',
                                    'X-Manager-Key' => $manager_key,
                                ),
                                'timeout' => 10,
                                'sslverify' => false,
                            ));

                            if (!is_wp_error($response)) {
                                $body = wp_remote_retrieve_body($response);
                                file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Manager update SUCCESS: $body\n", FILE_APPEND);
                                error_log('Watchtower Agent: Updated manager with new password');
                            } else {
                                $error = $response->get_error_message();
                                file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Manager update FAILED: $error\n", FILE_APPEND);
                                error_log('Watchtower Agent: Failed to update manager: ' . $error);
                            }
                        } else {
                            file_put_contents($restore_log, date('Y-m-d H:i:s') . " - Manager settings missing, cannot auto-update\n", FILE_APPEND);
                        }
                        } // End if ($plaintext_password && is_string($plaintext_password))
                    }
                }
            }

            update_site_option('watchtower_restore_progress', 100);
        } elseif (is_wp_error($result)) {
            error_log('Watchtower Agent: Restore failed with WP_Error: ' . $result->get_error_message());
            update_site_option('watchtower_restore_progress', -1); // Error
        } else {
            error_log('Watchtower Agent: Restore failed (returned false)');
            update_site_option('watchtower_restore_progress', -1); // Error
        }
    } catch (Exception $e) {
        error_log('Watchtower Agent: Restore exception: ' . $e->getMessage());
        update_site_option('watchtower_restore_progress', -1); // Error
    }

    delete_site_option('updraft_restore_in_progress');
    sleep(2); // Keep progress visible for a moment
    delete_site_option('watchtower_restore_progress');
    error_log('Watchtower Agent: Restore process ended');
}

/**
 * Check if Wordfence is blocking Application Passwords and disable it
 * Uses static variable to avoid redundant checks during the same request
 */
function watchtower_agent_check_wordfence_app_password_block() {
    static $checked = false;
    if ($checked) {
        return false;
    }
    $checked = true;

    if (!is_plugin_active('wordfence/wordfence.php')) {
        return false; // Wordfence not active
    }

    if (!class_exists('wfConfig')) {
        $wordfence_file = WP_PLUGIN_DIR . '/wordfence/wordfence.php';
        if (file_exists($wordfence_file)) {
            require_once $wordfence_file;
        } else {
            return false;
        }
    }

    if (!class_exists('wfConfig')) {
        return false;
    }

    $is_blocked = wfConfig::get('loginSec_disableApplicationPasswords');

    if ($is_blocked) {
        wfConfig::set('loginSec_disableApplicationPasswords', 0);
        error_log('Watchtower Agent: Disabled Wordfence Application Password blocking (loginSec_disableApplicationPasswords)');
        return true;
    }

    return false; // Already disabled
}
