<?php
/**
 * Health Storage Class
 * Handles reading and writing site health data to individual site directories
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Health_Storage {

    /**
     * Path to sites directory
     */
    private $sites_dir;

    /**
     * Constructor
     */
    public function __construct() {
        $this->sites_dir = WATCHTOWER_MANAGER_DATA_DIR . 'sites/';
    }

    /**
     * Get site directory path for a given site URL
     */
    private function get_site_dir($site_url) {
        $parsed = parse_url($site_url);

        // Get hostname
        $hostname = isset($parsed['host']) ? $parsed['host'] : 'unknown';

        // Get port - only add if it's non-standard
        $port = isset($parsed['port']) ? $parsed['port'] : null;

        // If no explicit port, don't add default ports
        if ($port && $this->is_standard_port($parsed['scheme'] ?? 'http', $port)) {
            $port = null;
        }

        // Get path (for multisite subdirectory installations)
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

        // Build directory name
        $dir_name = $hostname;
        if ($port) {
            $dir_name .= '-' . $port;
        }
        if (!empty($path)) {
            $dir_name .= '-' . $path;
        }

        // Sanitize directory name (remove any unsafe characters)
        $dir_name = preg_replace('/[^a-zA-Z0-9\-\.]/', '_', $dir_name);

        return $this->sites_dir . $dir_name . '/';
    }

    /**
     * Check if port is standard for the scheme
     */
    private function is_standard_port($scheme, $port) {
        $standard_ports = array(
            'http' => 80,
            'https' => 443,
        );

        return isset($standard_ports[$scheme]) && $standard_ports[$scheme] == $port;
    }

    /**
     * Get health file path for a site
     */
    private function get_health_file_path($site_url) {
        return $this->get_site_dir($site_url) . 'health.json';
    }

    /**
     * Save health data for a site
     */
    public function save_health_data($site_url, $health_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $this->get_health_file_path($site_url);

        // Add timestamp
        $health_data['checked_at'] = current_time('mysql');
        $health_data['site_url'] = $site_url;

        // Ensure site directory exists
        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($health_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($file_path, $json);

        return $result !== false;
    }

    /**
     * Get health data for a site
     */
    public function get_health_data($site_url) {
        $file_path = $this->get_health_file_path($site_url);

        if (!file_exists($file_path)) {
            return null;
        }

        $json = file_get_contents($file_path);
        $health_data = json_decode($json, true);

        return is_array($health_data) ? $health_data : null;
    }

    /**
     * Delete health data for a site
     */
    public function delete_health_data($site_url) {
        $file_path = $this->get_health_file_path($site_url);

        if (file_exists($file_path)) {
            return unlink($file_path);
        }

        return true;
    }

    /**
     * Fetch and save health data from agent
     */
    public function fetch_and_save_health($agent) {
        $site_url = $agent['site_url'];

        // Build health endpoint URL
        $health_url = $site_url . '/wp-json/watchtower-agent/v1/health';

        // For local agents (same WordPress instance), use internal address
        // Check if the host and port match any site in this WordPress installation
        $parsed_agent = parse_url($site_url);
        $parsed_current = parse_url(get_site_url());

        $agent_host = $parsed_agent['host'] ?? '';
        $agent_port = $parsed_agent['port'] ?? ($parsed_agent['scheme'] === 'https' ? 443 : 80);
        $current_host = $parsed_current['host'] ?? '';
        $current_port = $parsed_current['port'] ?? ($parsed_current['scheme'] === 'https' ? 443 : 80);

        // Prepare request arguments
        $request_args = array(
            'timeout' => 10,
            'sslverify' => false,
        );

        // If same host and port, it's local - use internal address
        if ($agent_host === $current_host && $agent_port === $current_port) {
            $path = $parsed_agent['path'] ?? '';
            $health_url = 'http://127.0.0.1' . $path . '/wp-json/watchtower-agent/v1/health';

            // Add Host header to prevent redirect
            $host_header = $agent_host;
            if ($agent_port !== 80 && $agent_port !== 443) {
                $host_header .= ':' . $agent_port;
            }
            $request_args['headers'] = array(
                'Host' => $host_header,
            );
        }

        $response = wp_remote_get($health_url, $request_args);

        if (is_wp_error($response)) {
            // Save error state
            $health_data = array(
                'success' => false,
                'error' => $response->get_error_message(),
            );
            return $this->save_health_data($site_url, $health_data);
        }

        $body = wp_remote_retrieve_body($response);
        $health_data = json_decode($body, true);

        if (!$health_data) {
            $health_data = array(
                'success' => false,
                'error' => 'Invalid response from agent',
            );
            return $this->save_health_data($site_url, $health_data);
        }

        // Split data into static (info) and dynamic (health) components
        $agent_storage = new Watchtower_Manager_Agent_Storage();
        $current_agent = $agent_storage->get_agent_by_url($site_url);

        // Extract static configuration data for info.json
        $static_data = array();
        $static_keys = array('php', 'wordpress', 'database', 'server', 'plugins', 'theme', 'constants');

        foreach ($static_keys as $key) {
            if (isset($health_data[$key])) {
                $static_data[$key] = $health_data[$key];
            }
        }

        // Update agent info with static configuration data
        if (!empty($static_data)) {
            $update_data = array_merge(array('site_url' => $site_url), $static_data);

            // Also save site_name, admin_url, and site_icon to top level for easy access
            if (isset($static_data['wordpress']['site_name'])) {
                $update_data['site_name'] = $static_data['wordpress']['site_name'];
            }
            if (isset($static_data['wordpress']['admin_url'])) {
                $update_data['admin_url'] = $static_data['wordpress']['admin_url'];
            }
            if (isset($static_data['wordpress']['site_icon'])) {
                $update_data['site_icon'] = $static_data['wordpress']['site_icon'];
            }

            $agent_storage->save_agent($update_data);
        }

        // Extract dynamic monitoring data for health.json
        $dynamic_data = array(
            'success' => true,
            'timestamp' => $health_data['timestamp'] ?? current_time('mysql'),
        );

        $dynamic_keys = array('cpu', 'memory', 'disk', 'uptime');

        foreach ($dynamic_keys as $key) {
            if (isset($health_data[$key])) {
                $dynamic_data[$key] = $health_data[$key];
            }
        }

        return $this->save_health_data($site_url, $dynamic_data);
    }

    /**
     * Get age of health data in seconds
     */
    public function get_health_data_age($site_url) {
        $health_data = $this->get_health_data($site_url);

        if (!$health_data || !isset($health_data['checked_at'])) {
            return null;
        }

        $checked_time = strtotime($health_data['checked_at']);
        $current_time = current_time('timestamp');

        return $current_time - $checked_time;
    }

    /**
     * Check if health data is stale (older than 5 minutes)
     */
    public function is_health_data_stale($site_url) {
        $age = $this->get_health_data_age($site_url);

        if ($age === null) {
            return true; // No data = stale
        }

        return $age > 300; // 5 minutes
    }
}
