<?php
/**
 * Storage Class
 * Handles reading and writing agent data and health data to individual site directories
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Storage {

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
     * Get health file path for a site
     */
    private function get_health_file_path($site_url) {
        return $this->get_site_dir($site_url) . 'health.json';
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
        $site_url = $agent_data['site'];
        $site_dir = $this->get_site_dir($site_url);
        $info_file = $this->get_info_file_path($site_url);

        $existing_agent = $this->get_agent_by_url($site_url);
        $is_new_registration = !$existing_agent;

        if ($existing_agent) {
            $agent_data = array_merge($existing_agent, $agent_data);
            $agent_data['updated'] = current_time('mysql');
        } else {
            $agent_data['registered'] = current_time('mysql');
            $agent_data['updated'] = current_time('mysql');
        }

        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($agent_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($info_file, $json);

        if ($result !== false && $is_new_registration) {
            $this->fetch_and_save_health($agent_data);
        }

        return $result !== false;
    }

    /**
     * Remove agent by site URL
     */
    public function remove_agent($site_url) {
        $site_dir = $this->get_site_dir($site_url);

        if (!file_exists($site_dir)) {
            return true;
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

    /**
     * Save health data for a site
     */
    public function save_health_data($site_url, $health_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $this->get_health_file_path($site_url);

        $health_data['checked_at'] = current_time('mysql');
        $health_data['site'] = $site_url;

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

        $agent = $this->get_agent_by_url($site_url);

        if ($agent) {
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
        $site_url = $agent['site'];

        $info_url = $site_url . '/?rest_route=/watchtower-agent/v1/info';
        $health_url = $site_url . '/?rest_route=/watchtower-agent/v1/health';

        $parsed_agent = parse_url($site_url);
        $parsed_current = parse_url(get_site_url());

        $agent_host = $parsed_agent['host'] ?? '';
        $agent_port = $parsed_agent['port'] ?? ($parsed_agent['scheme'] === 'https' ? 443 : 80);
        $current_host = $parsed_current['host'] ?? '';
        $current_port = $parsed_current['port'] ?? ($parsed_current['scheme'] === 'https' ? 443 : 80);

        $request_args = array(
            'timeout' => 10,
            'sslverify' => false,
        );

        if ($agent_host === 'localhost' && $agent_port !== 80 && $agent_port !== 443) {
            $port_to_container = array(
                '8083' => 'watchtower_agent_site',
            );

            if (isset($port_to_container[$agent_port])) {
                $info_url = 'http://' . $port_to_container[$agent_port] . '/?rest_route=/watchtower-agent/v1/info';
                $health_url = 'http://' . $port_to_container[$agent_port] . '/?rest_route=/watchtower-agent/v1/health';
            }
        }
        elseif ($agent_host === $current_host && $agent_port === $current_port) {
            $path = $parsed_agent['path'] ?? '';
            $info_url = 'http://127.0.0.1' . $path . '/?rest_route=/watchtower-agent/v1/info';
            $health_url = 'http://127.0.0.1' . $path . '/?rest_route=/watchtower-agent/v1/health';

            $host_header = $agent_host;
            if ($agent_port !== 80 && $agent_port !== 443) {
                $host_header .= ':' . $agent_port;
            }
            $request_args['headers'] = array(
                'Host' => $host_header,
            );
        }

        $info_response = wp_remote_get($info_url, $request_args);
        $agent_version = null;

        if (!is_wp_error($info_response)) {
            $info_body = wp_remote_retrieve_body($info_response);
            $info_data = json_decode($info_body, true);
            if ($info_data && isset($info_data['version'])) {
                $agent_version = $info_data['version'];
            }
        }

        $response = wp_remote_get($health_url, $request_args);

        if (is_wp_error($response)) {
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

        $current_agent = $this->get_agent_by_url($site_url);

        $static_data = array();
        $static_keys = array('php', 'wordpress', 'database', 'server', 'plugins', 'theme', 'constants');

        foreach ($static_keys as $key) {
            if (isset($health_data[$key])) {
                $static_data[$key] = $health_data[$key];
            }
        }

        if (!empty($static_data) || $agent_version) {
            $update_data = array_merge(array('site' => $site_url), $static_data);

            if (isset($static_data['wordpress']['name'])) {
                $update_data['name'] = $static_data['wordpress']['name'];
            }
            if (isset($static_data['wordpress']['admin_url'])) {
                $update_data['admin_url'] = $static_data['wordpress']['admin_url'];
            }
            if (isset($static_data['wordpress']['icon'])) {
                $update_data['icon'] = $static_data['wordpress']['icon'];
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

            $has_intermission = false;
            $plugins_list = isset($static_data['plugins']['plugins']) ? $static_data['plugins']['plugins'] : array();
            foreach ($plugins_list as $plugin) {
                if (isset($plugin['file']) && $plugin['file'] === 'intermission/intermission.php') {
                    $has_intermission = true;
                    break;
                }
            }

            if ($has_intermission && isset($agent['username']) && isset($agent['password'])) {
                $maintenance_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/maintenance');
                $maintenance_response = wp_remote_get(
                    $maintenance_url,
                    array(
                        'timeout' => 10,
                        'headers' => array(
                            'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                        ),
                        'sslverify' => false,
                    )
                );

                if (!is_wp_error($maintenance_response)) {
                    $maintenance_body = json_decode(wp_remote_retrieve_body($maintenance_response), true);
                    if (isset($maintenance_body['maintenance_enabled'])) {
                        $update_data['mode'] = $maintenance_body['maintenance_enabled'] ? 'maintenance' : 'live';
                    }
                }
            } else {
                $update_data['mode'] = 'live';
            }

            $this->save_agent($update_data);
        }

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

        if ($agent_version) {
            $update_result = $this->check_and_update_agent_version($agent, true);

            if (!empty($update_result['auto_updated'])) {
                sleep(2);

                $info_response = wp_remote_get($info_url, $request_args);
                if (!is_wp_error($info_response)) {
                    $info_body = wp_remote_retrieve_body($info_response);
                    $info_data = json_decode($info_body, true);
                    if ($info_data && isset($info_data['version'])) {
                        $this->save_agent(array(
                            'site' => $site_url,
                            'agent_version' => $info_data['version']
                        ));
                    }
                }
            }
        }

        $this->fetch_and_save_backups($site_url, $agent, $request_args);
        $this->fetch_and_save_users($site_url, $agent, $request_args);

        if (isset($static_data['plugins'])) {
            $plugins_data = $static_data['plugins'];
            $plugins_data['fetched_at'] = current_time('mysql');
            $this->save_plugins_data($site_url, $plugins_data);
        }

        return $this->save_health_data($site_url, $dynamic_data);
    }

    /**
     * Fetch and save backups data from agent
     */
    private function fetch_and_save_backups($site_url, $agent, $request_args) {
        $backups_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups');

        $backups_request_args = array_merge($request_args, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        $backups_response = wp_remote_get($backups_url, $backups_request_args);

        if (is_wp_error($backups_response)) {
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

        $this->save_backups_data($site_url, $backups_data);
    }

    /**
     * Save backups data to backups.json
     */
    public function save_backups_data($site_url, $backups_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'backups.json';

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
     * Fetch and save users data from agent
     */
    private function fetch_and_save_users($site_url, $agent, $request_args) {
        $users_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/users?role=administrator');

        $users_request_args = array_merge($request_args, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        $users_response = wp_remote_get($users_url, $users_request_args);

        if (is_wp_error($users_response)) {
            $users_data = array(
                'success' => false,
                'error' => $users_response->get_error_message(),
                'fetched_at' => current_time('mysql'),
            );
        } else {
            $users_body = wp_remote_retrieve_body($users_response);
            $users_data = json_decode($users_body, true);

            if (!$users_data) {
                $users_data = array(
                    'success' => false,
                    'error' => 'Invalid response from agent',
                    'fetched_at' => current_time('mysql'),
                );
            } else {
                $users_data['fetched_at'] = current_time('mysql');
            }
        }

        $this->save_users_data($site_url, $users_data);
    }

    /**
     * Save users data to users.json
     */
    private function save_users_data($site_url, $users_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'users.json';

        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($users_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($file_path, $json);

        return $result !== false;
    }

    /**
     * Get users data from users.json
     */
    public function get_users_data($site_url) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'users.json';

        if (!file_exists($file_path)) {
            return null;
        }

        $json = file_get_contents($file_path);
        $users_data = json_decode($json, true);

        if (!is_array($users_data)) {
            return null;
        }

        return $users_data;
    }

    /**
     * Save plugins data to plugins.json
     */
    public function save_plugins_data($site_url, $plugins_data) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'plugins.json';

        if (!file_exists($site_dir)) {
            wp_mkdir_p($site_dir);
        }

        $json = json_encode($plugins_data, JSON_PRETTY_PRINT);
        $result = file_put_contents($file_path, $json);

        return $result !== false;
    }

    /**
     * Get plugins data from plugins.json
     */
    public function get_plugins_data($site_url) {
        $site_dir = $this->get_site_dir($site_url);
        $file_path = $site_dir . 'plugins.json';

        if (!file_exists($file_path)) {
            return null;
        }

        $json = file_get_contents($file_path);
        $plugins_data = json_decode($json, true);

        if (!is_array($plugins_data)) {
            return null;
        }

        return $plugins_data;
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

        $site_url = $agent_data['site'];
        $info_url = $site_url . '/?rest_route=/watchtower-agent/v1/info';

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
            return true;
        }

        return $age > 300;
    }
}
