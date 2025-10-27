<?php
/**
 * REST API Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_REST_Controller {

    /**
     * API namespace
     */
    private $namespace;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // User Management endpoints
        $user_management = new Watchtower_Agent_User_Management($this->namespace);
        $user_management->register_routes();

        // Backup Management endpoints
        $backup_management = new Watchtower_Agent_Backup_Management($this->namespace);
        $backup_management->register_routes();

        // Update Management endpoints
        $update_management = new Watchtower_Agent_Update_Management($this->namespace);
        $update_management->register_routes();

        // Info endpoint (public)
        register_rest_route($this->namespace, '/info', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_info'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // Application password retrieval endpoint (public, but transient-based)
        register_rest_route($this->namespace, '/app-password', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_app_password'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        // Health endpoint (public)
        register_rest_route($this->namespace, '/health', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_health'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
    }

    /**
     * Get plugin info
     */
    public function get_info($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'version' => WATCHTOWER_AGENT_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_url' => get_site_url(),
            'admin_url' => get_admin_url(),
            'admin_email' => get_option('admin_email'),
            'updraftplus_installed' => class_exists('UpdraftPlus'),
            'timestamp' => current_time('mysql'),
        ), 200);
    }

    /**
     * Get application password (from transient)
     */
    public function get_app_password($request) {
        $password_data = get_transient('watchtower_agent_app_password');

        if ($password_data === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No application password available. The password may have expired (10 minute limit) or the plugin may not have been recently activated.',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'username' => $password_data['username'],
            'password' => $password_data['password'],
            'created' => $password_data['created'],
            'message' => 'This password will only be shown once and expires from this endpoint in 10 minutes. Save it securely.',
        ), 200);
    }

    /**
     * Get system health information
     */
    public function get_health($request) {
        // PHP Information
        $php_info = array(
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions(),
        );

        // Memory Usage
        $memory_usage = array(
            'current' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'current_bytes' => memory_get_usage(),
            'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'peak_bytes' => memory_get_peak_usage(),
            'limit' => ini_get('memory_limit'),
            'wp_memory_limit' => WP_MEMORY_LIMIT,
            'wp_max_memory_limit' => WP_MAX_MEMORY_LIMIT,
        );

        // Calculate memory usage percentage
        $memory_limit_bytes = $this->convert_to_bytes(ini_get('memory_limit'));
        if ($memory_limit_bytes > 0) {
            $memory_usage['usage_percentage'] = round((memory_get_usage() / $memory_limit_bytes) * 100, 2) . '%';
        }

        // WordPress Information
        $site_icon_url = get_site_icon_url();
        $wordpress_info = array(
            'version' => get_bloginfo('version'),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'site_name' => get_bloginfo('name'),
            'site_icon' => $site_icon_url ? $site_icon_url : null,
            'admin_url' => admin_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_locale(),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'debug_mode' => WP_DEBUG,
            'multisite' => is_multisite(),
        );

        // Database Information
        global $wpdb;
        $db_info = array(
            'database_name' => DB_NAME,
            'database_host' => DB_HOST,
            'database_charset' => DB_CHARSET,
            'database_collate' => DB_COLLATE,
            'table_prefix' => $wpdb->prefix,
            'database_version' => $wpdb->db_version(),
        );

        // Get database size
        $db_size_query = $wpdb->get_var("
            SELECT SUM(data_length + index_length)
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");
        if ($db_size_query) {
            $db_info['database_size'] = round($db_size_query / 1024 / 1024, 2) . ' MB';
            $db_info['database_size_bytes'] = $db_size_query;
        }

        // Server Information
        $server_info = array(
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'https' => is_ssl(),
        );

        // Disk Space (if available)
        $disk_info = array();
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $disk_free = @disk_free_space(ABSPATH);
            $disk_total = @disk_total_space(ABSPATH);

            if ($disk_free !== false && $disk_total !== false) {
                $disk_used = $disk_total - $disk_free;
                $disk_info = array(
                    'free' => round($disk_free / 1024 / 1024 / 1024, 2) . ' GB',
                    'free_bytes' => $disk_free,
                    'used' => round($disk_used / 1024 / 1024 / 1024, 2) . ' GB',
                    'used_bytes' => $disk_used,
                    'total' => round($disk_total / 1024 / 1024 / 1024, 2) . ' GB',
                    'total_bytes' => $disk_total,
                    'usage_percentage' => round(($disk_used / $disk_total) * 100, 2) . '%',
                );
            }
        }

        // CPU Load (if available on Linux/Unix)
        $cpu_info = array();
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cpu_info = array(
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
            );
        }

        // Active Plugins
        $active_plugins = get_option('active_plugins', array());
        $plugins_info = array(
            'active_count' => count($active_plugins),
            'active_plugins' => array(),
        );

        foreach ($active_plugins as $plugin_file) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
            $plugins_info['active_plugins'][] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'file' => $plugin_file,
            );
        }

        // Active Theme
        $theme = wp_get_theme();
        $theme_info = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
        );

        // WordPress Constants
        $wp_constants = array(
            'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            'SCRIPT_DEBUG' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false,
            'WP_CACHE' => defined('WP_CACHE') ? WP_CACHE : false,
            'CONCATENATE_SCRIPTS' => defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true,
            'COMPRESS_SCRIPTS' => defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : true,
            'COMPRESS_CSS' => defined('COMPRESS_CSS') ? COMPRESS_CSS : true,
        );

        // Uptime (if available)
        $uptime_info = array();
        if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            $uptime = @shell_exec('uptime');
            if ($uptime) {
                $uptime_info['uptime'] = trim($uptime);
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'timestamp' => current_time('mysql'),
            'php' => $php_info,
            'memory' => $memory_usage,
            'wordpress' => $wordpress_info,
            'database' => $db_info,
            'server' => $server_info,
            'disk' => $disk_info,
            'cpu' => $cpu_info,
            'plugins' => $plugins_info,
            'theme' => $theme_info,
            'constants' => $wp_constants,
            'uptime' => $uptime_info,
        ), 200);
    }

    /**
     * Convert PHP size format to bytes
     */
    private function convert_to_bytes($size) {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Check if user has permission to access API
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }
}
