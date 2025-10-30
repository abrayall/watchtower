<?php
/**
 * Audit Logger - Tracks all admin and API actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_Remote_Agent_Audit_Logger {

    /**
     * Log directory path
     */
    private $log_dir;

    /**
     * Retention days
     */
    private $retention_days = 30;

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $base_dir = dirname($upload_dir['basedir']) . '/watchtower';

        $this->log_dir = $base_dir . '/agent/audit';
        $this->ensure_log_directory();
        $this->init_hooks();
    }

    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);

            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }

            $index = $this->log_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('user_register', array($this, 'log_user_created'), 10, 1);
        add_action('profile_update', array($this, 'log_user_updated'), 10, 2);
        add_action('delete_user', array($this, 'log_user_deleted'), 10, 2);
        add_action('set_user_role', array($this, 'log_role_changed'), 10, 3);

        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'), 10, 1);
        add_action('wp_login_failed', array($this, 'log_login_failed'), 10, 2);

        add_action('activated_plugin', array($this, 'log_plugin_activated'), 10, 2);
        add_action('deactivated_plugin', array($this, 'log_plugin_deactivated'), 10, 2);
        add_action('switch_theme', array($this, 'log_theme_switched'), 10, 3);

        add_action('_core_updated_successfully', array($this, 'log_core_updated'), 10, 1);

        add_filter('rest_post_dispatch', array($this, 'log_rest_api_call'), 10, 3);

        add_action('wp_remote_agent_daily_cleanup', array($this, 'cleanup_old_logs'));
        if (!wp_next_scheduled('wp_remote_agent_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_remote_agent_daily_cleanup');
        }
    }

    /**
     * Write log entry to file
     */
    public function log($action, $details = array()) {
        $log_file = $this->get_log_file();

        $entry = array(
            'timestamp' => current_time('mysql'),
            'unix_time' => time(),
            'action' => $action,
            'actor_id' => get_current_user_id(),
            'actor_name' => $this->get_current_user_name(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'details' => $details,
        );

        $log_line = json_encode($entry) . "\n";

        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get current day's log file path
     */
    private function get_log_file() {
        $date = current_time('Y-m-d');
        return $this->log_dir . '/' . $date . '.log';
    }

    /**
     * Get current user name
     */
    private function get_current_user_name() {
        $user = wp_get_current_user();
        if ($user && $user->ID) {
            return $user->user_login;
        }
        return 'guest';
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return 'unknown';
    }

    /**
     * User created hook
     */
    public function log_user_created($user_id) {
        $user = get_userdata($user_id);
        $this->log('user_created', array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
        ));
    }

    /**
     * User updated hook
     */
    public function log_user_updated($user_id, $old_user_data) {
        $user = get_userdata($user_id);

        $changes = array();

        if ($old_user_data->user_email !== $user->user_email) {
            $changes['email'] = array('old' => $old_user_data->user_email, 'new' => $user->user_email);
        }

        $old_roles = $old_user_data->roles;
        $new_roles = $user->roles;
        if ($old_roles !== $new_roles) {
            $changes['roles'] = array('old' => implode(', ', $old_roles), 'new' => implode(', ', $new_roles));
        }

        if (!empty($changes)) {
            $this->log('user_updated', array(
                'user_id' => $user_id,
                'username' => $user->user_login,
                'changes' => $changes,
            ));
        }
    }

    /**
     * User deleted hook
     */
    public function log_user_deleted($user_id, $reassign_id) {
        $user = get_userdata($user_id);
        $this->log('user_deleted', array(
            'user_id' => $user_id,
            'username' => $user ? $user->user_login : 'unknown',
            'reassign_to' => $reassign_id,
        ));
    }

    /**
     * Role changed hook
     */
    public function log_role_changed($user_id, $role, $old_roles) {
        $user = get_userdata($user_id);
        $this->log('user_role_changed', array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'old_roles' => implode(', ', $old_roles),
            'new_role' => $role,
        ));
    }

    /**
     * User login hook
     */
    public function log_user_login($user_login, $user) {
        $this->log('user_login', array(
            'user_id' => $user->ID,
            'username' => $user_login,
        ));
    }

    /**
     * User logout hook
     */
    public function log_user_logout($user_id) {
        $user = get_userdata($user_id);
        $this->log('user_logout', array(
            'user_id' => $user_id,
            'username' => $user ? $user->user_login : 'unknown',
        ));
    }

    /**
     * Login failed hook
     */
    public function log_login_failed($username, $error) {
        $this->log('login_failed', array(
            'username' => $username,
            'error' => $error instanceof WP_Error ? $error->get_error_message() : 'unknown',
        ));
    }

    /**
     * Plugin activated hook
     */
    public function log_plugin_activated($plugin, $network_wide) {
        $this->log('plugin_activated', array(
            'plugin' => $plugin,
            'network_wide' => $network_wide,
        ));
    }

    /**
     * Plugin deactivated hook
     */
    public function log_plugin_deactivated($plugin, $network_wide) {
        $this->log('plugin_deactivated', array(
            'plugin' => $plugin,
            'network_wide' => $network_wide,
        ));
    }

    /**
     * Theme switched hook
     */
    public function log_theme_switched($new_name, $new_theme, $old_theme) {
        $this->log('theme_switched', array(
            'old_theme' => $old_theme->get('Name'),
            'new_theme' => $new_name,
        ));
    }

    /**
     * Core updated hook
     */
    public function log_core_updated($wp_version) {
        $this->log('core_updated', array(
            'version' => $wp_version,
        ));
    }

    /**
     * REST API call hook
     */
    public function log_rest_api_call($response, $server, $request) {
        if (strpos($request->get_route(), '/watchtower-agent/') !== 0) {
            return $response;
        }

        $status_code = $response->get_status();
        $method = $request->get_method();
        $route = $request->get_route();

        $this->log('rest_api_call', array(
            'method' => $method,
            'route' => $route,
            'status_code' => $status_code,
            'success' => $status_code >= 200 && $status_code < 300,
            'params' => $this->sanitize_params($request->get_params()),
        ));

        return $response;
    }

    /**
     * Sanitize request parameters (remove sensitive data)
     */
    private function sanitize_params($params) {
        $sensitive_keys = array('password', 'pass', 'pwd', 'secret', 'token', 'api_key');

        foreach ($params as $key => $value) {
            foreach ($sensitive_keys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $params[$key] = '[REDACTED]';
                }
            }
        }

        return $params;
    }

    /**
     * Cleanup old log files
     */
    public function cleanup_old_logs() {
        $files = glob($this->log_dir . '/*.log');

        if (!$files) {
            return;
        }

        $cutoff_time = time() - ($this->retention_days * DAY_IN_SECONDS);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
                error_log('WP Remote Agent: Deleted old audit log: ' . basename($file));
            }
        }
    }

    /**
     * Get retention days setting
     */
    public function get_retention_days() {
        return $this->retention_days;
    }

    /**
     * Set retention days
     */
    public function set_retention_days($days) {
        $this->retention_days = max(1, intval($days));
    }
}
