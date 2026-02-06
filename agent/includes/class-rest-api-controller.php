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
        $user_management = new Watchtower_Agent_User_Management($this->namespace);
        $user_management->register_routes();

        $backup_management = new Watchtower_Agent_Backup_Management($this->namespace);
        $backup_management->register_routes();

        $update_management = new Watchtower_Agent_Update_Management($this->namespace);
        $update_management->register_routes();

        $log_management = new Watchtower_Agent_Log_Management($this->namespace);
        $log_management->register_routes();

        $maintenance_management = new Watchtower_Agent_Maintenance_Management($this->namespace);
        $maintenance_management->register_routes();

        $file_management = new Watchtower_Agent_File_Management();
        $file_management->register_routes();

        $audit_endpoint = new WP_Remote_Agent_Audit_Endpoint($this->namespace);
        $audit_endpoint->register_routes();

        register_rest_route($this->namespace, '/info', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_info'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        register_rest_route($this->namespace, '/app-password', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_app_password'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        register_rest_route($this->namespace, '/health', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_health'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        register_rest_route($this->namespace, '/update', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_plugin'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }

    /**
     * Get plugin info (static configuration data)
     */
    public function get_info($request) {
        $php_info = array(
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions(),
        );

        $site_icon_url = get_site_icon_url();
        $wordpress_info = array(
            'version' => get_bloginfo('version'),
            'site' => get_site_url(),
            'home_url' => get_home_url(),
            'name' => get_bloginfo('name'),
            'icon' => $site_icon_url ? $site_icon_url : null,
            'admin_url' => get_admin_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_locale(),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'debug_mode' => WP_DEBUG,
            'multisite' => is_multisite(),
        );

        global $wpdb;
        $db_info = array(
            'database_name' => DB_NAME,
            'database_host' => DB_HOST,
            'database_charset' => DB_CHARSET,
            'database_collate' => DB_COLLATE,
            'table_prefix' => $wpdb->prefix,
            'database_version' => $wpdb->db_version(),
        );

        $db_size_query = $wpdb->get_var("
            SELECT SUM(data_length + index_length)
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");
        if ($db_size_query) {
            $db_info['database_size'] = round($db_size_query / 1024 / 1024, 2) . ' MB';
            $db_info['database_size_bytes'] = $db_size_query;
        }

        $server_info = array(
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'https' => is_ssl(),
        );

        $plugins_info = $this->get_plugins_info();

        $theme = wp_get_theme();
        $theme_info = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
        );

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

        return new WP_REST_Response(array(
            'success' => true,
            'version' => WATCHTOWER_AGENT_VERSION,
            'timestamp' => current_time('mysql'),
            'php' => $php_info,
            'wordpress' => $wordpress_info,
            'database' => $db_info,
            'server' => $server_info,
            'plugins' => $plugins_info,
            'theme' => $theme_info,
            'constants' => $wp_constants,
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
        $php_info = array(
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'extensions' => get_loaded_extensions(),
        );

        $memory_usage = array(
            'current' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'current_bytes' => memory_get_usage(),
            'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'peak_bytes' => memory_get_peak_usage(),
            'limit' => ini_get('memory_limit'),
            'wp_memory_limit' => WP_MEMORY_LIMIT,
            'wp_max_memory_limit' => WP_MAX_MEMORY_LIMIT,
        );

        $memory_limit_bytes = $this->convert_to_bytes(ini_get('memory_limit'));
        if ($memory_limit_bytes > 0) {
            $memory_usage['usage_percentage'] = round((memory_get_usage() / $memory_limit_bytes) * 100, 2) . '%';
        }

        $site_icon_url = get_site_icon_url();
        $wordpress_info = array(
            'version' => get_bloginfo('version'),
            'site' => get_site_url(),
            'home_url' => get_home_url(),
            'name' => get_bloginfo('name'),
            'icon' => $site_icon_url ? $site_icon_url : null,
            'admin_url' => admin_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_locale(),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'debug_mode' => WP_DEBUG,
            'multisite' => is_multisite(),
        );

        global $wpdb;
        $db_info = array(
            'database_name' => DB_NAME,
            'database_host' => DB_HOST,
            'database_charset' => DB_CHARSET,
            'database_collate' => DB_COLLATE,
            'table_prefix' => $wpdb->prefix,
            'database_version' => $wpdb->db_version(),
        );

        $db_size_query = $wpdb->get_var("
            SELECT SUM(data_length + index_length)
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
        ");
        if ($db_size_query) {
            $db_info['database_size'] = round($db_size_query / 1024 / 1024, 2) . ' MB';
            $db_info['database_size_bytes'] = $db_size_query;
        }

        $server_info = array(
            'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'https' => is_ssl(),
        );

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

        $cpu_info = array();
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();

            $cores = 1; // Default fallback
            if (function_exists('shell_exec')) {
                if (stripos(PHP_OS, 'WIN') === 0) {
                    $output = shell_exec('wmic cpu get NumberOfCores');
                    if ($output) {
                        preg_match_all('/\d+/', $output, $matches);
                        if (!empty($matches[0])) {
                            $cores = array_sum(array_map('intval', $matches[0]));
                        }
                    }
                } else {
                    $output = shell_exec('nproc 2>/dev/null || grep -c ^processor /proc/cpuinfo 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null');
                    if ($output) {
                        $cores = intval(trim($output));
                    }
                }
            }

            $usage_percentage = ($cores > 0) ? round(($load[0] / $cores) * 100, 1) : 0;

            $cpu_info = array(
                'cores' => $cores,
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
                'usage_percentage' => $usage_percentage . '%',
            );
        }

        $plugins_info = $this->get_plugins_info();

        $theme = wp_get_theme();
        $theme_info = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
        );

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

    private function get_plugins_info() {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        require_once(ABSPATH . 'wp-admin/includes/update.php');

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $update_plugins = get_site_transient('update_plugins');

        $plugins = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }

            $is_active = in_array($plugin_file, $active_plugins);

            $update_info = null;
            $update_available = false;
            if ($update_plugins) {
                if (isset($update_plugins->response[$plugin_file])) {
                    $update_info = $update_plugins->response[$plugin_file];
                    $update_available = true;
                } elseif (isset($update_plugins->no_update[$plugin_file])) {
                    $update_info = $update_plugins->no_update[$plugin_file];
                }
            }

            $plugin = array(
                'file' => $plugin_file,
                'slug' => $slug,
                'name' => $plugin_data['Name'] ?? '',
                'version' => $plugin_data['Version'] ?? '',
                'description' => $plugin_data['Description'] ?? '',
                'author' => $plugin_data['Author'] ?? '',
                'author_uri' => $plugin_data['AuthorURI'] ?? '',
                'plugin_uri' => $plugin_data['PluginURI'] ?? '',
                'text_domain' => $plugin_data['TextDomain'] ?? '',
                'domain_path' => $plugin_data['DomainPath'] ?? '',
                'network' => !empty($plugin_data['Network']),
                'requires_wp' => $plugin_data['RequiresWP'] ?? null,
                'requires_php' => $plugin_data['RequiresPHP'] ?? null,
                'update_uri' => $plugin_data['UpdateURI'] ?? null,
                'requires_plugins' => $plugin_data['RequiresPlugins'] ?? null,
                'active' => $is_active,
                'update_available' => $update_available,
            );

            if ($update_info) {
                $plugin['wp_org'] = array(
                    'id' => $update_info->id ?? null,
                    'slug' => $update_info->slug ?? null,
                    'url' => $update_info->url ?? null,
                    'package' => $update_info->package ?? null,
                    'new_version' => $update_info->new_version ?? null,
                    'requires' => $update_info->requires ?? null,
                    'requires_php' => $update_info->requires_php ?? null,
                    'icons' => isset($update_info->icons) ? (array)$update_info->icons : null,
                );
            }

            $plugins[] = $plugin;
        }

        return array(
            'total_count' => count($all_plugins),
            'active_count' => count($active_plugins),
            'update_count' => ($update_plugins && isset($update_plugins->response)) ? count($update_plugins->response) : 0,
            'plugins' => $plugins,
        );
    }

    /**
     * Update agent plugin from uploaded ZIP file
     */
    public function update_plugin($request) {
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No file uploaded',
            ), 400);
        }

        $file = $files['file'];

        $allowed_types = array('application/zip', 'application/x-zip-compressed', 'application/x-zip', 'application/octet-stream');
        if (!in_array($file['type'], $allowed_types) && !preg_match('/\.zip$/i', $file['name'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'File must be a ZIP archive',
            ), 400);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'File upload error: ' . $file['error'],
            ), 400);
        }

        $temp_dir = get_temp_dir() . 'watchtower-update-' . time();
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not create temp directory',
            ), 500);
        }

        if (!class_exists('ZipArchive')) {
            $this->delete_directory($temp_dir);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'ZipArchive class not available',
            ), 500);
        }

        $zip = new ZipArchive();
        $zip_open = $zip->open($file['tmp_name']);

        if ($zip_open !== true) {
            $this->delete_directory($temp_dir);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not open ZIP file',
            ), 500);
        }

        if (!$zip->extractTo($temp_dir)) {
            $zip->close();
            $this->delete_directory($temp_dir);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not extract ZIP contents',
            ), 500);
        }

        $zip->close();

        $extracted_files = scandir($temp_dir);
        $plugin_dir = null;
        foreach ($extracted_files as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($temp_dir . '/' . $item)) {
                $plugin_dir = $item;
                break;
            }
        }

        if (!$plugin_dir) {
            $this->delete_directory($temp_dir);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No plugin directory found in ZIP',
            ), 400);
        }

        $source_path = trailingslashit($temp_dir) . $plugin_dir;
        $dest_path = WP_PLUGIN_DIR . '/' . $plugin_dir;

        if (!$this->copy_directory($source_path, $dest_path)) {
            $this->delete_directory($temp_dir);
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not copy plugin files',
            ), 500);
        }

        $this->delete_directory($temp_dir);

        $plugin_file = null;
        foreach (array('agent.php', $plugin_dir . '.php') as $possible_file) {
            $check_path = $plugin_dir . '/' . $possible_file;
            if (file_exists(WP_PLUGIN_DIR . '/' . $check_path)) {
                $plugin_file = $check_path;
                break;
            }
        }

        if (!$plugin_file) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not find plugin main file',
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin updated successfully',
            'plugin' => $plugin_file,
        ), 200);
    }

    /**
     * Recursively copy a directory
     */
    private function copy_directory($source, $dest) {
        if (!file_exists($dest)) {
            if (!wp_mkdir_p($dest)) {
                return false;
            }
        }

        $items = scandir($source);

        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $source_item = $source . DIRECTORY_SEPARATOR . $item;
            $dest_item = $dest . DIRECTORY_SEPARATOR . $item;

            if (is_dir($source_item)) {
                if (!$this->copy_directory($source_item, $dest_item)) {
                    return false;
                }
            } else {
                if (!copy($source_item, $dest_item)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Recursively delete a directory
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Check if user has permission to update plugin (more permissive for file uploads)
     */
    public function check_update_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('Authentication required.'),
                array('status' => 401)
            );
        }

        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Insufficient permissions.'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check if user has permission to access API
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }
}
