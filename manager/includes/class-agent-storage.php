<?php
/**
 * Agent Storage Class
 * Handles reading and writing agent data to individual site directories
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Agent_Storage {

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

        $hostname = isset($parsed['host']) ? $parsed['host'] : 'unknown';

        $port = isset($parsed['port']) ? $parsed['port'] : null;

        if ($port && $this->is_standard_port($parsed['scheme'] ?? 'http', $port)) {
            $port = null;
        }

        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

        $dir_name = $hostname;
        if ($port) {
            $dir_name .= '-' . $port;
        }
        if (!empty($path)) {
            $dir_name .= '-' . $path;
        }

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
     * Get info file path for a given site URL
     */
    private function get_info_file_path($site_url) {
        return $this->get_site_dir($site_url) . 'info.json';
    }

    /**
     * Get all registered agents
     */
    public function get_all_agents() {
        if (!file_exists($this->sites_dir)) {
            return array();
        }

        $agents = array();
        $site_dirs = glob($this->sites_dir . '*/info.json');

        foreach ($site_dirs as $info_file) {
            $json = file_get_contents($info_file);
            $agent = json_decode($json, true);

            if (is_array($agent)) {
                $agents[] = $agent;
            }
        }

        return $agents;
    }

    /**
     * Get agent by site URL
     */
    public function get_agent_by_url($site_url) {
        $info_file = $this->get_info_file_path($site_url);

        if (!file_exists($info_file)) {
            return null;
        }

        $json = file_get_contents($info_file);
        $agent = json_decode($json, true);

        return is_array($agent) ? $agent : null;
    }

    /**
     * Add or update agent
     */
    public function save_agent($agent_data) {
        $site_url = $agent_data['site_url'];
        $site_dir = $this->get_site_dir($site_url);
        $info_file = $this->get_info_file_path($site_url);

        $existing_agent = $this->get_agent_by_url($site_url);
        $is_new_registration = !$existing_agent;

        if ($existing_agent) {
            $agent_data = array_merge($existing_agent, $agent_data);
            $agent_data['updated_at'] = current_time('mysql');
        } else {
            $agent_data['registered_at'] = current_time('mysql');
            $agent_data['updated_at'] = current_time('mysql');
        }

        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($agent_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($info_file, $json);

        if ($result !== false && $is_new_registration) {
            require_once WATCHTOWER_MANAGER_PLUGIN_DIR . 'includes/class-health-storage.php';
            $health_storage = new Watchtower_Manager_Health_Storage();
            $health_storage->fetch_and_save_health($agent_data);
        }

        return $result !== false;
    }

    /**
     * Remove agent by site URL
     */
    public function remove_agent($site_url) {
        $site_dir = $this->get_site_dir($site_url);

        if (!file_exists($site_dir)) {
            return true; // Already removed
        }

        $this->delete_directory($site_dir);

        return !file_exists($site_dir);
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
     * Get agent count
     */
    public function get_agent_count() {
        return count($this->get_all_agents());
    }
}
