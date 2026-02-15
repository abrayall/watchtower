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

define('WATCHTOWER_MANAGER_VERSION', watchtower_manager_get_version());
define('WATCHTOWER_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WATCHTOWER_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
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
    $endpoint_parts = explode('?', $endpoint, 2);
    $path = $endpoint_parts[0];
    $query = isset($endpoint_parts[1]) ? $endpoint_parts[1] : '';

    $full_url = $site_url . '/?rest_route=' . $path;
    if (!empty($query)) {
        $full_url .= '&' . $query;
    }

    $parsed = parse_url($site_url);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

    if ($host === 'localhost' && $port !== 80 && $port !== 443) {
        $port_to_container = array(
            '8083' => 'watchtower_agent_site',
            '8082' => 'watchtower_manager_site',
            '8085' => 'watchtower-agent-wordpress',
        );

        if (isset($port_to_container[$port])) {
            $full_url = 'http://' . $port_to_container[$port] . '/?rest_route=' . $path;
            if (!empty($query)) {
                $full_url .= '&' . $query;
            }
        }
    }

    return $full_url;
}

require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-watchtower-manager.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-rest-api-controller.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-storage.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-auto-updater.php';
require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-admin-dashboard.php';

function watchtower_manager_init() {
    $plugin = new Watchtower_Manager();
    $plugin->init();

    if (is_admin()) {
        $dashboard = new Watchtower_Manager_Admin_Dashboard();
        $dashboard->init();
    }
}
add_action('plugins_loaded', 'watchtower_manager_init');

add_filter('cron_schedules', 'watchtower_manager_add_cron_schedules');
function watchtower_manager_add_cron_schedules($schedules) {
    $schedules['every_fifteen_minutes'] = array(
        'interval' => 900, // 15 minutes in seconds
        'display' => __('Every 15 Minutes')
    );

    $schedules['daily'] = array(
        'interval' => 86400, // 24 hours in seconds
        'display' => __('Once Daily')
    );

    return $schedules;
}

add_action('watchtower_manager_poll_health', 'watchtower_manager_poll_health_callback');
function watchtower_manager_poll_health_callback() {
    $storage = new Watchtower_Manager_Storage();

    $agents = $storage->get_all_agents();

    error_log('Watchtower Manager: Starting health poll for ' . count($agents) . ' agents');

    foreach ($agents as $agent) {
        $site_url = $agent['site'];

        $result = $storage->fetch_and_save_health($agent);

        if ($result) {
            error_log('Watchtower Manager: Health data updated for ' . $site_url);
        } else {
            error_log('Watchtower Manager: Failed to update health data for ' . $site_url);
        }
    }

    error_log('Watchtower Manager: Health poll completed');
}

add_action('watchtower_manager_check_versions', 'watchtower_manager_check_versions_callback');
function watchtower_manager_check_versions_callback() {
    $storage = new Watchtower_Manager_Storage();

    $agents = $storage->get_all_agents();

    error_log('Watchtower Manager: Starting version check for ' . count($agents) . ' agents');

    foreach ($agents as $agent) {
        $site_url = $agent['site'];

        $result = $storage->check_and_update_agent_version($agent);

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

register_activation_hook(__FILE__, 'watchtower_manager_activate');
function watchtower_manager_activate() {
    $data_dir = WATCHTOWER_MANAGER_DATA_DIR;
    $sites_dir = $data_dir . 'sites/';

    if (!file_exists($data_dir)) {
        wp_mkdir_p($data_dir);
    }

    if (!file_exists($sites_dir)) {
        wp_mkdir_p($sites_dir);
    }

    if (!wp_next_scheduled('watchtower_manager_poll_health')) {
        wp_schedule_event(time(), 'every_fifteen_minutes', 'watchtower_manager_poll_health');
        error_log('Watchtower Manager: Health polling cron job scheduled');
    }

    if (!wp_next_scheduled('watchtower_manager_check_versions')) {
        wp_schedule_event(time(), 'daily', 'watchtower_manager_check_versions');
        error_log('Watchtower Manager: Daily version check cron job scheduled');
    }

    $previous_version = get_option('watchtower_manager_version');
    $current_version = WATCHTOWER_MANAGER_VERSION;

    if ($previous_version && $previous_version !== $current_version) {
        error_log('Watchtower Manager: Upgraded from ' . $previous_version . ' to ' . $current_version . ' - triggering immediate version check');

        wp_schedule_single_event(time(), 'watchtower_manager_check_versions');
    }

    update_option('watchtower_manager_version', $current_version);

    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'watchtower_manager_deactivate');
function watchtower_manager_deactivate() {
    $timestamp = wp_next_scheduled('watchtower_manager_poll_health');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'watchtower_manager_poll_health');
        error_log('Watchtower Manager: Health polling cron job unscheduled');
    }

    $timestamp = wp_next_scheduled('watchtower_manager_check_versions');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'watchtower_manager_check_versions');
        error_log('Watchtower Manager: Version check cron job unscheduled');
    }

    flush_rewrite_rules();
}
