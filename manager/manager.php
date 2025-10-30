<?php
/**
 * Plugin Name: Watchtower
 * Plugin URI: https://github.com/abrayall/watchtower
 * Description: Central management plugin to control and monitor multiple Watchtower Agent installations across WordPress sites
 * Version: 1.0.0
 * Author: Brayall, LLC
 * Author URI: https://github.com/abrayall
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
// Store data outside plugin directory so it persists across updates
define('WATCHTOWER_MANAGER_DATA_DIR', WP_CONTENT_DIR . '/watchtower/manager/');

/**
 * Helper: Translate site URL to Docker container URL if needed
 *
 * This function handles the translation of localhost URLs with non-standard ports
 * to internal Docker container names. This is necessary when the manager plugin
 * needs to communicate with agent plugins that are running in separate Docker containers.
 *
 * @param string $site_url The original site URL (e.g., http://localhost:8083)
 * @param string $endpoint The REST endpoint path (e.g., /watchtower-agent/v1/backups)
 * @return string The translated URL for API calls
 */
function watchtower_manager_translate_agent_url($site_url, $endpoint) {
    // Build the full endpoint URL
    $full_url = $site_url . '/?rest_route=' . $endpoint;

    // Parse the site URL
    $parsed = parse_url($site_url);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

    // Check if this is a localhost URL with non-standard port
    if ($host === 'localhost' && $port !== 80 && $port !== 443) {
        // Map localhost ports to Docker container names
        $port_to_container = array(
            '8083' => 'watchtower_agent_site',
            '8082' => 'watchtower_manager_site',
        );

        // If we have a container mapping, translate the URL
        if (isset($port_to_container[$port])) {
            $full_url = 'http://' . $port_to_container[$port] . '/?rest_route=' . $endpoint;
        }
    }

    return $full_url;
}

// Load plugin classes
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-watchtower-manager.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-rest-api-controller.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-agent-storage.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-health-storage.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-auto-updater.php';
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

// Add custom cron schedules
add_filter('cron_schedules', 'watchtower_manager_add_cron_schedules');
function watchtower_manager_add_cron_schedules($schedules) {
    // Health polling every 5 minutes
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 5 minutes in seconds
        'display' => __('Every 5 Minutes')
    );

    // Version checks daily
    $schedules['daily'] = array(
        'interval' => 86400, // 24 hours in seconds
        'display' => __('Once Daily')
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

// Cron callback to check and update agent versions
add_action('watchtower_manager_check_versions', 'watchtower_manager_check_versions_callback');
function watchtower_manager_check_versions_callback() {
    $agent_storage = new Watchtower_Manager_Agent_Storage();
    $health_storage = new Watchtower_Manager_Health_Storage();

    $agents = $agent_storage->get_all_agents();

    // Log the start of version checking
    error_log('Watchtower Manager: Starting version check for ' . count($agents) . ' agents');

    foreach ($agents as $agent) {
        $site_url = $agent['site_url'];

        // Check and update agent version if needed
        $result = $health_storage->check_and_update_agent_version($agent);

        if (isset($result['checked']) && $result['checked']) {
            if (isset($result['needs_update']) && $result['needs_update']) {
                if (isset($result['auto_updated']) && $result['auto_updated']) {
                    error_log('Watchtower Manager: Agent updated successfully for ' . $site_url);
                } else {
                    error_log('Watchtower Manager: Agent needs update but auto-update disabled for ' . $site_url);
                }
            } else {
                error_log('Watchtower Manager: Agent is up-to-date for ' . $site_url);
            }
        } else {
            $error = isset($result['error']) ? $result['error'] : 'Unknown error';
            error_log('Watchtower Manager: Version check failed for ' . $site_url . ': ' . $error);
        }
    }

    error_log('Watchtower Manager: Version check completed');
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

    // Schedule health polling cron job (every 5 minutes)
    if (!wp_next_scheduled('watchtower_manager_poll_health')) {
        wp_schedule_event(time(), 'every_five_minutes', 'watchtower_manager_poll_health');
        error_log('Watchtower Manager: Health polling cron job scheduled');
    }

    // Schedule version check cron job (daily)
    if (!wp_next_scheduled('watchtower_manager_check_versions')) {
        wp_schedule_event(time(), 'daily', 'watchtower_manager_check_versions');
        error_log('Watchtower Manager: Daily version check cron job scheduled');
    }

    // Check if this is an upgrade (version changed)
    $previous_version = get_option('watchtower_manager_version');
    $current_version = WATCHTOWER_MANAGER_VERSION;

    if ($previous_version && $previous_version !== $current_version) {
        // This is an upgrade - trigger immediate version check
        error_log('Watchtower Manager: Upgraded from ' . $previous_version . ' to ' . $current_version . ' - triggering immediate version check');

        // Run version check immediately
        wp_schedule_single_event(time(), 'watchtower_manager_check_versions');
    }

    // Save current version
    update_option('watchtower_manager_version', $current_version);

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

    // Unschedule version check cron job
    $timestamp = wp_next_scheduled('watchtower_manager_check_versions');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'watchtower_manager_check_versions');
        error_log('Watchtower Manager: Version check cron job unscheduled');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}
