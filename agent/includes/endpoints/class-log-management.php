<?php
/**
 * Log Management Endpoint
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Log_Management {

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
     * Register routes
     */
    public function register_routes() {
        // Get available logs
        register_rest_route($this->namespace, '/logs', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_available_logs'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Get log entries
        register_rest_route($this->namespace, '/logs/(?P<type>debug|error|access)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'type' => array(
                    'type' => 'string',
                    'required' => true,
                    'enum' => array('debug', 'error', 'access'),
                ),
                'lines' => array(
                    'required' => false,
                    'default' => 100,
                    'validate_callback' => function($param, $request, $key) {
                        if ($param === 'all') {
                            return true;
                        }
                        if (!is_numeric($param)) {
                            return false;
                        }
                        $param = (int) $param;
                        return $param >= 1 && $param <= 10000;
                    },
                    'sanitize_callback' => function($param, $request, $key) {
                        if ($param === 'all') {
                            return 'all';
                        }
                        return (int) $param;
                    },
                ),
            ),
        ));

        // Enable/disable debug mode
        register_rest_route($this->namespace, '/debug', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'set_debug_mode'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'enabled' => array(
                    'type' => 'boolean',
                    'required' => true,
                ),
            ),
        ));
    }

    /**
     * Get available log types and their status
     */
    public function get_available_logs($request) {
        $types = array('debug', 'error', 'access');
        $available_logs = array();
        $seen_paths = array(); // Track file paths to avoid duplicates

        foreach ($types as $type) {
            $log_file = $this->discover_log_file($type);

            // Skip if we've already added this file path
            if ($log_file && isset($seen_paths[$log_file])) {
                continue;
            }

            $status = array(
                'type' => $type,
                'discovered' => !empty($log_file),
                'path' => $log_file,
                'exists' => false,
                'readable' => false,
                'size' => null,
            );

            if ($log_file && file_exists($log_file)) {
                $status['exists'] = true;
                $status['readable'] = is_readable($log_file);
                if ($status['readable']) {
                    $status['size'] = filesize($log_file);
                    $seen_paths[$log_file] = true; // Mark this path as seen
                }
            }

            $available_logs[] = $status;
        }

        return new WP_REST_Response(array(
            'success' => true,
            'logs' => $available_logs,
        ), 200);
    }

    /**
     * Get log entries
     */
    public function get_logs($request) {
        $type = $request->get_param('type');
        $lines = $request->get_param('lines');

        // Discover log file path
        $log_file = $this->discover_log_file($type);

        if (!$log_file) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Log file not found or not configured',
                'type' => $type,
            ), 404);
        }

        if (!file_exists($log_file)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Log file does not exist',
                'type' => $type,
                'path' => $log_file,
            ), 404);
        }

        if (!is_readable($log_file)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Log file is not readable',
                'type' => $type,
                'path' => $log_file,
            ), 403);
        }

        // Read last N lines from file (backwards)
        $log_lines = $this->read_file_backwards($log_file, $lines);

        return new WP_REST_Response(array(
            'success' => true,
            'type' => $type,
            'path' => $log_file,
            'lines_requested' => $lines,
            'lines_returned' => count($log_lines),
            'logs' => $log_lines,
        ), 200);
    }

    /**
     * Discover log file path based on type
     */
    private function discover_log_file($type) {
        switch ($type) {
            case 'debug':
                // WordPress debug log
                if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    if (is_string(WP_DEBUG_LOG)) {
                        return WP_DEBUG_LOG;
                    }
                    return WP_CONTENT_DIR . '/debug.log';
                }
                return null;

            case 'error':
                // PHP error log
                $error_log = ini_get('error_log');
                if ($error_log && $error_log !== 'syslog') {
                    return $error_log;
                }
                // Try common locations
                $common_paths = array(
                    '/var/log/php/error.log',
                    '/var/log/php-fpm/error.log',
                    '/var/log/php_errors.log',
                    ABSPATH . 'error_log',
                );
                foreach ($common_paths as $path) {
                    if (file_exists($path)) {
                        return $path;
                    }
                }
                return null;

            case 'access':
                // Web server access log
                $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';

                // Try to detect server type
                if (stripos($server_software, 'apache') !== false) {
                    $common_paths = array(
                        '/var/log/apache2/access.log',
                        '/var/log/httpd/access_log',
                        '/var/log/apache/access.log',
                    );
                } elseif (stripos($server_software, 'nginx') !== false) {
                    $common_paths = array(
                        '/var/log/nginx/access.log',
                    );
                } else {
                    // Try both
                    $common_paths = array(
                        '/var/log/apache2/access.log',
                        '/var/log/nginx/access.log',
                        '/var/log/httpd/access_log',
                    );
                }

                foreach ($common_paths as $path) {
                    if (file_exists($path)) {
                        return $path;
                    }
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Read last N lines from a file efficiently (backwards reading)
     */
    private function read_file_backwards($file, $lines) {
        // Handle 'all' parameter - read entire file
        if ($lines === 'all') {
            $lines = PHP_INT_MAX;
        }

        $file_size = filesize($file);

        if ($file_size === 0) {
            return array();
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            return array();
        }

        $chunk_size = 4096; // Read 4KB chunks
        $buffer = '';
        $lines_found = array();
        $position = $file_size;

        // Read file backwards in chunks
        while ($position > 0 && count($lines_found) < $lines) {
            // Calculate chunk position
            $chunk_start = max(0, $position - $chunk_size);
            $chunk_length = $position - $chunk_start;

            // Seek to position and read chunk
            fseek($handle, $chunk_start);
            $chunk = fread($handle, $chunk_length);

            // Prepend to buffer
            $buffer = $chunk . $buffer;

            // Split into lines
            $split_lines = explode("\n", $buffer);

            // Keep the first part (incomplete line) in buffer for next iteration
            if ($position > $chunk_size) {
                $buffer = array_shift($split_lines);
            } else {
                $buffer = '';
            }

            // Add lines to result (in reverse order since we're reading backwards)
            for ($i = count($split_lines) - 1; $i >= 0; $i--) {
                $line = trim($split_lines[$i]);
                if ($line !== '') {
                    array_unshift($lines_found, $line);
                    if (count($lines_found) >= $lines) {
                        break 2;
                    }
                }
            }

            $position = $chunk_start;
        }

        // If we have leftover buffer content, it's the first line
        if ($buffer !== '' && count($lines_found) < $lines) {
            $line = trim($buffer);
            if ($line !== '') {
                array_unshift($lines_found, $line);
            }
        }

        fclose($handle);

        // Return in chronological order (oldest first)
        return $lines_found;
    }

    /**
     * Set debug mode (enable/disable WordPress debugging)
     */
    public function set_debug_mode($request) {
        $enabled = $request->get_param('enabled');

        // Find wp-config.php
        $config_file = $this->find_wp_config();

        if (!$config_file) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not locate wp-config.php',
            ), 500);
        }

        if (!is_writable($config_file)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'wp-config.php is not writable',
                'path' => $config_file,
            ), 403);
        }

        // Update debug constants
        $result = $this->update_debug_constants($config_file, $enabled);

        if (!$result['success']) {
            return new WP_REST_Response($result, 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'enabled' => $enabled,
            'constants' => array(
                'WP_DEBUG' => $enabled,
                'WP_DEBUG_LOG' => $enabled,
                'WP_DEBUG_DISPLAY' => false,
            ),
            'message' => 'Debug mode ' . ($enabled ? 'enabled' : 'disabled') . '. Changes will take effect on next request.',
        ), 200);
    }

    /**
     * Find wp-config.php location
     */
    private function find_wp_config() {
        // Try standard location
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }

        // Try one level up (for WordPress in subdirectory)
        if (file_exists(dirname(ABSPATH) . '/wp-config.php')) {
            return dirname(ABSPATH) . '/wp-config.php';
        }

        return null;
    }

    /**
     * Update debug constants in wp-config.php
     */
    private function update_debug_constants($config_file, $enabled) {
        $config_content = file_get_contents($config_file);

        if ($config_content === false) {
            return array(
                'success' => false,
                'error' => 'Could not read wp-config.php',
            );
        }

        $value = $enabled ? 'true' : 'false';

        // Constants to update
        $constants = array(
            'WP_DEBUG' => $value,
            'WP_DEBUG_LOG' => $value,
            'WP_DEBUG_DISPLAY' => 'false',  // Always false for security
        );

        foreach ($constants as $constant => $const_value) {
            // Check if constant is already defined
            $pattern = "/define\s*\(\s*['\"]" . $constant . "['\"]\s*,\s*.*?\s*\)\s*;/";

            if (preg_match($pattern, $config_content)) {
                // Replace existing definition
                $config_content = preg_replace(
                    $pattern,
                    "define( '" . $constant . "', " . $const_value . " );",
                    $config_content
                );
            } else {
                // Add new definition before the "That's all" comment
                $insert = "define( '" . $constant . "', " . $const_value . " );\n";
                $config_content = preg_replace(
                    "/(\/\*.*?stop editing.*?\*\/)/i",
                    $insert . "$1",
                    $config_content,
                    1
                );
            }
        }

        // Write back to file
        $result = file_put_contents($config_file, $config_content);

        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Could not write to wp-config.php',
            );
        }

        return array('success' => true);
    }

    /**
     * Check if user has permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }
}
