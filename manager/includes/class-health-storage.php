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

        if (!is_array($health_data)) {
            return null;
        }

        // Merge in static configuration data from agent storage
        $agent_storage = new Watchtower_Manager_Agent_Storage();
        $agent = $agent_storage->get_agent_by_url($site_url);

        if ($agent) {
            // Add static configuration fields from agent info
            $static_keys = array('php', 'wordpress', 'database', 'server', 'plugins', 'theme', 'constants');
            foreach ($static_keys as $key) {
                if (isset($agent[$key])) {
                    $health_data[$key] = $agent[$key];
                }
            }
        }

        return $health_data;
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

        // Build info and health endpoint URLs using query parameter format (more compatible)
        $info_url = $site_url . '/?rest_route=/watchtower-agent/v1/info';
        $health_url = $site_url . '/?rest_route=/watchtower-agent/v1/health';

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

        // Docker container translation: if agent is localhost with non-standard port, try container name
        if ($agent_host === 'localhost' && $agent_port !== 80 && $agent_port !== 443) {
            // Map common localhost ports to Docker container names
            $port_to_container = array(
                '8083' => 'watchtower_agent_site',
            );

            if (isset($port_to_container[$agent_port])) {
                $info_url = 'http://' . $port_to_container[$agent_port] . '/?rest_route=/watchtower-agent/v1/info';
                $health_url = 'http://' . $port_to_container[$agent_port] . '/?rest_route=/watchtower-agent/v1/health';
            }
        }
        // If same host and port, it's local - use internal address
        elseif ($agent_host === $current_host && $agent_port === $current_port) {
            $path = $parsed_agent['path'] ?? '';
            $info_url = 'http://127.0.0.1' . $path . '/?rest_route=/watchtower-agent/v1/info';
            $health_url = 'http://127.0.0.1' . $path . '/?rest_route=/watchtower-agent/v1/health';

            // Add Host header to prevent redirect
            $host_header = $agent_host;
            if ($agent_port !== 80 && $agent_port !== 443) {
                $host_header .= ':' . $agent_port;
            }
            $request_args['headers'] = array(
                'Host' => $host_header,
            );
        }

        // First, fetch /info to get agent version
        $info_response = wp_remote_get($info_url, $request_args);
        $agent_version = null;

        if (!is_wp_error($info_response)) {
            $info_body = wp_remote_retrieve_body($info_response);
            $info_data = json_decode($info_body, true);
            if ($info_data && isset($info_data['version'])) {
                $agent_version = $info_data['version'];
            }
        }

        // Then fetch /health
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
        if (!empty($static_data) || $agent_version) {
            $update_data = array_merge(array('site_url' => $site_url), $static_data);

            // Also save flattened fields to top level for easy access
            if (isset($static_data['wordpress']['site_name'])) {
                $update_data['site_name'] = $static_data['wordpress']['site_name'];
            }
            if (isset($static_data['wordpress']['admin_url'])) {
                $update_data['admin_url'] = $static_data['wordpress']['admin_url'];
            }
            if (isset($static_data['wordpress']['site_icon'])) {
                $update_data['site_icon'] = $static_data['wordpress']['site_icon'];
            }
            if (isset($static_data['wordpress']['version'])) {
                $update_data['wordpress_version'] = $static_data['wordpress']['version'];
            }
            if (isset($static_data['php']['version'])) {
                $update_data['php_version'] = $static_data['php']['version'];
            }
            if ($agent_version) {
                $update_data['agent_version'] = $agent_version;
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

        // Check and update agent version if needed (forced during manual scans)
        if ($agent_version) {
            $update_result = $this->check_and_update_agent_version($agent, true);

            // If agent was updated, re-fetch health data to get new version
            if (!empty($update_result['auto_updated'])) {
                // Sleep briefly to allow the agent to fully restart
                sleep(2);

                // Re-fetch agent info to update version
                $info_response = wp_remote_get($info_url, $request_args);
                if (!is_wp_error($info_response)) {
                    $info_body = wp_remote_retrieve_body($info_response);
                    $info_data = json_decode($info_body, true);
                    if ($info_data && isset($info_data['version'])) {
                        $agent_storage->save_agent(array(
                            'site_url' => $site_url,
                            'agent_version' => $info_data['version']
                        ));
                    }
                }
            }
        }

        // Fetch and save backups data
        $this->fetch_and_save_backups($site_url, $agent, $request_args);

        return $this->save_health_data($site_url, $dynamic_data);
    }

    /**
     * Fetch and save backups data from agent
     */
    private function fetch_and_save_backups($site_url, $agent, $request_args) {
        // Get translated URL for backups endpoint
        $backups_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups');

        // Add authentication headers
        $backups_request_args = array_merge($request_args, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        // Fetch backups data
        $backups_response = wp_remote_get($backups_url, $backups_request_args);

        if (is_wp_error($backups_response)) {
            // Save error state
            $backups_data = array(
                'success' => false,
                'error' => $backups_response->get_error_message(),
                'fetched_at' => current_time('mysql'),
            );
        } else {
            $backups_body = wp_remote_retrieve_body($backups_response);
            $backups_data = json_decode($backups_body, true);

            if (!$backups_data) {
                $backups_data = array(
                    'success' => false,
                    'error' => 'Invalid response from agent',
                    'fetched_at' => current_time('mysql'),
                );
            } else {
                $backups_data['fetched_at'] = current_time('mysql');
            }
        }

        // Save backups data to backups.json
        $this->save_backups_data($site_url, $backups_data);
    }

    /**
     * Save backups data to backups.json
     */
    private function save_backups_data($site_url, $backups_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'backups.json';

        // Ensure site directory exists
        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($backups_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($file_path, $json);

        return $result !== false;
    }

    /**
     * Get backups data from backups.json
     */
    public function get_backups_data($site_url) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'backups.json';

        if (!file_exists($file_path)) {
            return null;
        }

        $json = file_get_contents($file_path);
        $backups_data = json_decode($json, true);

        if (!is_array($backups_data)) {
            return null;
        }

        return $backups_data;
    }

    /**
     * Check and update agent version if needed
     *
     * @param array $agent_data Agent information
     * @param bool $force_update Force update even if auto-update is disabled (for manual updates)
     */
    public function check_and_update_agent_version($agent_data, $force_update = false) {
        $auto_updater = new Watchtower_Manager_Auto_Updater();

        if (!$auto_updater->has_bundled_agent()) {
            return array(
                'checked' => false,
                'error' => 'No bundled agent available'
            );
        }

        // Get agent version from /info endpoint
        $site_url = $agent_data['site_url'];
        $info_url = $site_url . '/?rest_route=/watchtower-agent/v1/info';

        // Apply Docker localhost translation if needed
        $parsed_agent = parse_url($site_url);
        $agent_host = $parsed_agent['host'] ?? '';
        $agent_port = $parsed_agent['port'] ?? ($parsed_agent['scheme'] === 'https' ? 443 : 80);

        if ($agent_host === 'localhost' && $agent_port !== 80 && $agent_port !== 443) {
            $port_to_container = array(
                '8083' => 'watchtower_agent_site',
            );

            if (isset($port_to_container[$agent_port])) {
                $info_url = 'http://' . $port_to_container[$agent_port] . '/?rest_route=/watchtower-agent/v1/info';
            }
        }

        $response = wp_remote_get($info_url, array(
            'timeout' => 10,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return array(
                'checked' => false,
                'error' => 'Could not fetch agent info: ' . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $info_data = json_decode($body, true);

        if (!$info_data || !isset($info_data['version'])) {
            return array(
                'checked' => false,
                'error' => 'Invalid info response'
            );
        }

        $agent_version = $info_data['version'];
        $needs_update = $auto_updater->needs_update($agent_version);

        if (!$needs_update) {
            return array(
                'checked' => true,
                'needs_update' => false,
                'agent_version' => $agent_version,
                'bundled_version' => $auto_updater->get_bundled_agent_version()
            );
        }

        // Auto-update is needed - check if auto-update is enabled (unless forced)
        $auto_update_enabled = get_option('watchtower_auto_update_agents', false);

        if (!$auto_update_enabled && !$force_update) {
            return array(
                'checked' => true,
                'needs_update' => true,
                'auto_updated' => false,
                'agent_version' => $agent_version,
                'bundled_version' => $auto_updater->get_bundled_agent_version(),
                'message' => 'Auto-update disabled'
            );
        }

        // Perform update (auto or manual)
        $update_result = $auto_updater->update_agent($agent_data);

        return array_merge(array(
            'checked' => true,
            'needs_update' => true,
            'auto_updated' => $update_result['success'],
            'agent_version' => $agent_version,
            'bundled_version' => $auto_updater->get_bundled_agent_version()
        ), $update_result);
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
