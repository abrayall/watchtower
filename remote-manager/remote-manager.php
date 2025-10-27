<?php
/**
 * Plugin Name: Watchtower Manager
 * Plugin URI: https://github.com/abrayall/watchtower
 * Description: Central management plugin to control and monitor multiple Watchtower Agent installations across WordPress sites
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: watchtower-manager
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
function watchtower_manager_get_version() {
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
define('WATCHTOWER_MANAGER_VERSION', watchtower_manager_get_version());
define('WATCHTOWER_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WATCHTOWER_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WATCHTOWER_MANAGER_DATA_DIR', WATCHTOWER_MANAGER_PLUGIN_DIR . 'data/');

// Load plugin classes
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-watchtower-manager.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-rest-api-controller.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-agent-storage.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-health-storage.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-admin-dashboard.php';

// Initialize the plugin
function watchtower_manager_init() {
    $plugin = new Watchtower_Manager();
    $plugin->init();

    // Initialize admin dashboard (only in admin area)
    if (is_admin()) {
        $dashboard = new Watchtower_Manager_Admin_Dashboard();
        $dashboard->init();
    }
}
add_action('plugins_loaded', 'watchtower_manager_init');

// Add custom cron schedule for health polling
add_filter('cron_schedules', 'watchtower_manager_add_cron_schedules');
function watchtower_manager_add_cron_schedules($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display' => __('Every 5 Minutes')
    );
    return $schedules;
}

// Cron callback to poll agent health
add_action('watchtower_manager_poll_health', 'watchtower_manager_poll_health_callback');
function watchtower_manager_poll_health_callback() {
    $agent_storage = new Watchtower_Manager_Agent_Storage();
    $health_storage = new Watchtower_Manager_Health_Storage();

    $agents = $agent_storage->get_all_agents();

    // Log the start of polling
    error_log('Watchtower Manager: Starting health poll for ' . count($agents) . ' agents');

    foreach ($agents as $agent) {
        $site_url = $agent['site_url'];

        // Fetch and save health data
        $result = $health_storage->fetch_and_save_health($agent);

        if ($result) {
            error_log('Watchtower Manager: Health data updated for ' . $site_url);
        } else {
            error_log('Watchtower Manager: Failed to update health data for ' . $site_url);
        }
    }

    error_log('Watchtower Manager: Health poll completed');
}

// Activation hook
register_activation_hook(__FILE__, 'watchtower_manager_activate');
function watchtower_manager_activate() {
    // Create data directory structure
    $data_dir = WATCHTOWER_MANAGER_DATA_DIR;
    $sites_dir = $data_dir . 'sites/';

    if (!file_exists($data_dir)) {
        wp_mkdir_p($data_dir);
    }

    if (!file_exists($sites_dir)) {
        wp_mkdir_p($sites_dir);
    }

    // Schedule health polling cron job
    if (!wp_next_scheduled('watchtower_manager_poll_health')) {
        wp_schedule_event(time(), 'every_five_minutes', 'watchtower_manager_poll_health');
        error_log('Watchtower Manager: Health polling cron job scheduled');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'watchtower_manager_deactivate');
function watchtower_manager_deactivate() {
    // Unschedule health polling cron job
    $timestamp = wp_next_scheduled('watchtower_manager_poll_health');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'watchtower_manager_poll_health');
        error_log('Watchtower Manager: Health polling cron job unscheduled');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
