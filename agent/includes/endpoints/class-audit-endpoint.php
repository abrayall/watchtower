<?php
/**
 * Audit Log Endpoint
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Remote_Agent_Audit_Endpoint {

    /**
     * API namespace
     */
    private $namespace;

    /**
     * Audit logger instance
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
        $this->logger = WP_Remote_Agent_Audit_Logger::get_instance();
    }

    /**
     * Register routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/audit', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_audit_logs'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'from' => array(
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Start timestamp (unix timestamp)',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
                'to' => array(
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'End timestamp (unix timestamp, defaults to now)',
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    },
                ),
                'lines' => array(
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of lines to return (goes back in time if needed)',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 10000;
                    },
                ),
            ),
        ));
    }

    /**
     * Get audit logs
     */
    public function get_audit_logs($request) {
        $from = $request->get_param('from');
        $to = $request->get_param('to');
        $lines = $request->get_param('lines');

        if (empty($to)) {
            $to = time();
        }

        $entries = $this->read_log_entries($from, $to, $lines);

        return new WP_REST_Response(array(
            'success' => true,
            'entries' => $entries,
            'count' => count($entries),
            'from' => $from,
            'to' => $to,
        ), 200);
    }

    /**
     * Read log entries based on criteria
     */
    private function read_log_entries($from_timestamp, $to_timestamp, $max_lines) {
        $entries = array();
        $log_dir = $this->get_log_directory();

        if (!is_dir($log_dir)) {
            return $entries;
        }

        $log_files = $this->get_log_files($log_dir, $to_timestamp);

        foreach ($log_files as $log_file) {
            $file_entries = $this->read_file_entries($log_file, $from_timestamp, $to_timestamp);

            $entries = array_merge($entries, $file_entries);

            if ($from_timestamp && $this->get_file_date_timestamp($log_file) < $from_timestamp) {
                break;
            }
        }

        if (count($log_files) === 1) {
            $entries = array_reverse($entries);
        } else {
            usort($entries, function($a, $b) {
                return $b['unix_time'] - $a['unix_time'];
            });
        }

        if ($max_lines && count($entries) > $max_lines) {
            $entries = array_slice($entries, 0, $max_lines);
        }

        return $entries;
    }

    /**
     * Get log directory path
     */
    private function get_log_directory() {
        $upload_dir = wp_upload_dir();
        $base_dir = dirname($upload_dir['basedir']) . '/watchtower';
        return $base_dir . '/agent/audit';
    }

    /**
     * Get log files sorted by date (newest first)
     */
    private function get_log_files($log_dir, $to_timestamp) {
        $files = glob($log_dir . '/*.log');

        if (!$files) {
            return array();
        }

        $filtered_files = array();
        foreach ($files as $file) {
            $file_timestamp = $this->get_file_date_timestamp($file);
            if ($file_timestamp <= $to_timestamp) {
                $filtered_files[] = $file;
            }
        }

        usort($filtered_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $filtered_files;
    }

    /**
     * Get timestamp from log file name
     */
    private function get_file_date_timestamp($file) {
        if (preg_match('/(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $matches)) {
            return strtotime($matches[1] . ' 00:00:00');
        }
        return 0;
    }

    /**
     * Read entries from a single log file
     */
    private function read_file_entries($file, $from_timestamp, $to_timestamp) {
        $entries = array();

        if (!file_exists($file)) {
            return $entries;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            return $entries;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['unix_time'])) {
                continue;
            }

            if ($from_timestamp && $entry['unix_time'] < $from_timestamp) {
                continue;
            }

            if ($to_timestamp && $entry['unix_time'] > $to_timestamp) {
                continue;
            }

            $entries[] = $entry;
        }

        fclose($handle);

        return $entries;
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }
}
