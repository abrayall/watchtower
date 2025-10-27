<?php
/**
 * Auto Updater Class
 * Handles automatic updating of agent plugins on remote sites
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Auto_Updater {

    /**
     * Path to bundled agent plugin
     */
    private $agent_plugin_path;

    /**
     * Manager version
     */
    private $manager_version;

    /**
     * Constructor
     */
    public function __construct() {
        $this->manager_version = WATCHTOWER_MANAGER_VERSION;
        $this->agent_plugin_path = $this->find_agent_plugin_path();
    }

    /**
     * Translate URL for Docker localhost to container name
     */
    private function translate_url($url) {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? 443 : 80);

        // Docker container translation: if localhost with non-standard port, use container name
        if ($host === 'localhost' && $port !== 80 && $port !== 443) {
            $port_to_container = array(
                '8083' => 'watchtower_agent_site',
            );

            if (isset($port_to_container[$port])) {
                return 'http://' . $port_to_container[$port];
            }
        }

        return $url;
    }

    /**
     * Find the bundled agent plugin ZIP file
     */
    private function find_agent_plugin_path() {
        $assets_dir = WATCHTOWER_MANAGER_PLUGIN_DIR . 'assets/';

        if (!file_exists($assets_dir)) {
            return null;
        }

        // Look for watchtower-agent-*.zip
        $files = glob($assets_dir . 'watchtower-agent-*.zip');

        if (empty($files)) {
            return null;
        }

        // Return the first (and should be only) match
        return $files[0];
    }

    /**
     * Check if agent plugin is bundled
     */
    public function has_bundled_agent() {
        return !empty($this->agent_plugin_path) && file_exists($this->agent_plugin_path);
    }

    /**
     * Get bundled agent version from filename
     */
    public function get_bundled_agent_version() {
        if (!$this->has_bundled_agent()) {
            return null;
        }

        // Extract version from filename: watchtower-agent-0.0.2.zip
        $filename = basename($this->agent_plugin_path);
        if (preg_match('/watchtower-agent-(.+)\.zip$/', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if agent needs update
     */
    public function needs_update($agent_version) {
        $bundled_version = $this->get_bundled_agent_version();

        if (!$bundled_version) {
            return false;
        }

        // Compare versions - agent should match bundled version
        return $agent_version !== $bundled_version;
    }

    /**
     * Update agent plugin on remote site using WordPress REST API
     */
    public function update_agent($agent_data) {
        if (!$this->has_bundled_agent()) {
            return array(
                'success' => false,
                'error' => 'No bundled agent plugin found'
            );
        }

        $site_url = $agent_data['site_url'];
        $username = $agent_data['username'];
        $password = $agent_data['password'];

        // Skip deletion - WordPress will handle replacing the plugin
        // (Deleting an active plugin via REST API is problematic)

        // Install new version via WordPress API
        $install_result = $this->install_plugin_from_zip($site_url, $username, $password);

        return $install_result;
    }

    /**
     * Delete existing plugin using WordPress REST API
     */
    private function delete_plugin($site_url, $username, $password, $plugin_slug) {
        $translated_url = $this->translate_url($site_url);
        // Use query parameter format for maximum compatibility
        $endpoint = $translated_url . '/?rest_route=/wp/v2/plugins/' . urlencode($plugin_slug);

        $response = wp_remote_request($endpoint, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            ),
            'timeout' => 30,
            'sslverify' => false,
        ));

        // It's ok if this fails - plugin might be active or not exist
        return array('success' => true);
    }

    /**
     * Install plugin from ZIP using custom Watchtower Agent endpoint
     */
    private function install_plugin_from_zip($site_url, $username, $password) {
        // Read plugin file
        $file_contents = file_get_contents($this->agent_plugin_path);
        if ($file_contents === false) {
            return array(
                'success' => false,
                'error' => 'Could not read agent plugin file'
            );
        }

        $filename = basename($this->agent_plugin_path);
        $translated_url = $this->translate_url($site_url);
        // Use custom update endpoint (works without pretty permalinks)
        $endpoint = $translated_url . '/?rest_route=/watchtower-agent/v1/update';

        // Create boundary for multipart/form-data
        $boundary = wp_generate_password(24);

        // Build multipart body
        $body = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
        $body .= 'Content-Type: application/zip' . "\r\n\r\n";
        $body .= $file_contents . "\r\n";
        $body .= '--' . $boundary . '--';

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => 60,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Installation failed: ' . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($response_code !== 200 && $response_code !== 201) {
            $error_message = 'Installation failed with code ' . $response_code;
            if ($result && isset($result['message'])) {
                $error_message = $result['message'];
            }
            return array(
                'success' => false,
                'error' => $error_message,
                'response_code' => $response_code,
                'response_body' => $response_body
            );
        }

        return array(
            'success' => true,
            'message' => 'Plugin updated successfully',
            'plugin' => $result
        );
    }

    /**
     * Get update status for an agent
     */
    public function get_update_status($agent_data) {
        // Get agent version from info
        $agent_version = null;

        if (isset($agent_data['wordpress']['version'])) {
            // Try to get from saved info data
            $agent_storage = new Watchtower_Manager_Agent_Storage();
            $agent_info = $agent_storage->get_agent_by_url($agent_data['site_url']);

            // We need to fetch version from agent's /info endpoint
            $info_url = $agent_data['site_url'] . '/wp-json/watchtower-agent/v1/info';
            $response = wp_remote_get($info_url, array(
                'timeout' => 10,
                'sslverify' => false,
            ));

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $info_data = json_decode($body, true);

                if ($info_data && isset($info_data['version'])) {
                    $agent_version = $info_data['version'];
                }
            }
        }

        $bundled_version = $this->get_bundled_agent_version();
        $needs_update = $agent_version && $bundled_version && $this->needs_update($agent_version);

        return array(
            'agent_version' => $agent_version,
            'manager_version' => $this->manager_version,
            'bundled_version' => $bundled_version,
            'needs_update' => $needs_update,
            'can_update' => $this->has_bundled_agent()
        );
    }
}
