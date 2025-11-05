<?php
/**
 * REST API Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_REST_Controller {

    /**
     * API namespace
     */
    private $namespace;

    /**
     * Storage instance
     */
    private $storage;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
        $this->storage = new Watchtower_Manager_Storage();
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/register', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'register_agent'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'wordpress_version' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'php_version' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'agent_version' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/agents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_agents'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route($this->namespace, '/agents/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_agent'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));

        register_rest_route($this->namespace, '/agents', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_agent'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true', // Public endpoint
        ));

        register_rest_route($this->namespace, '/update-agent-password', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_agent_password'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/backups', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_backups'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/backup', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'start_backup'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'full',
                    'enum' => array('full', 'database', 'files'),
                ),
            ),
        ));

        register_rest_route($this->namespace, '/backup/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_backup_status'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/backup/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_backup'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/restore', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'start_restore'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
                'id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Backup timestamp ID',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/restore/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_restore_status'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }

    /**
     * Register a new agent
     */
    public function register_agent($request) {
        $site_url = $request->get_param('site');
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        if (empty($site_url) || empty($username) || empty($password)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Missing required fields: site, username, password',
            ), 400);
        }

        $agent_data = array(
            'site' => $site_url,
            'username' => $username,
            'password' => $password,
            'wordpress_version' => $request->get_param('wordpress_version') ?: 'unknown',
            'php_version' => $request->get_param('php_version') ?: 'unknown',
            'agent_version' => $request->get_param('agent_version') ?: 'unknown',
        );

        $result = $this->storage->save_agent($agent_data);

        if ($result) {
            $saved_agent = $this->storage->get_agent_by_url($site_url);

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Agent registered successfully',
                'agent' => $saved_agent,
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to save agent data',
            ), 500);
        }
    }

    /**
     * List all registered agents
     */
    public function list_agents($request) {
        $agents = $this->storage->get_all_agents();

        foreach ($agents as &$agent) {
            $agent['password'] = '********';
        }

        return new WP_REST_Response(array(
            'success' => true,
            'agents' => $agents,
            'count' => count($agents),
        ), 200);
    }

    /**
     * Get specific agent
     */
    public function get_agent($request) {
        $id = $request->get_param('id');
        $agents = $this->storage->get_all_agents();

        if (isset($agents[$id])) {
            $agent = $agents[$id];
            $agent['password'] = '********'; // Mask password

            return new WP_REST_Response(array(
                'success' => true,
                'agent' => $agent,
            ), 200);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'error' => 'Agent not found',
        ), 404);
    }

    /**
     * Delete agent
     */
    public function delete_agent($request) {
        $site_url = $request->get_param('site');

        $result = $this->storage->remove_agent($site_url);

        if ($result) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Agent removed successfully',
            ), 200);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'error' => 'Failed to remove agent',
        ), 500);
    }

    /**
     * Get manager status
     */
    public function get_status($request) {
        return new WP_REST_Response(array(
            'success' => true,
            'version' => WATCHTOWER_MANAGER_VERSION,
            'agent_count' => $this->storage->get_agent_count(),
            'timestamp' => current_time('mysql'),
        ), 200);
    }

    /**
     * Update agent password
     */
    public function update_agent_password($request) {
        $site_url = $request->get_param('site');
        $password = $request->get_param('password');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $agent['password'] = $password;
        $result = $this->storage->save_agent($agent);

        if ($result) {
            error_log('Watchtower Manager: Updated password for agent: ' . $site_url);
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Password updated successfully',
            ), 200);
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to update password',
            ), 500);
        }
    }

    /**
     * Get backups for an agent
     */
    public function get_backups($request) {
        $site_url = $request->get_param('site');

        $backups_data = $this->storage->get_backups_data($site_url);

        if ($backups_data === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No backup data found',
            ), 404);
        }

        return new WP_REST_Response($backups_data, 200);
    }

    /**
     * Start backup on agent
     */
    public function start_backup($request) {
        $site_url = $request->get_param('site');
        $type = $request->get_param('type');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $backup_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backup');

        $response = wp_remote_post($backup_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('type' => $type)),
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid response from agent',
            ), 500);
        }

        return new WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }

    /**
     * Get backup status from agent
     */
    public function get_backup_status($request) {
        $site_url = $request->get_param('site');
        $backup_id = $request->get_param('id');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $status_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups/' . $backup_id);

        $response = wp_remote_get($status_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid response from agent',
            ), 500);
        }

        return new WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }

    /**
     * Delete backup on agent
     */
    public function delete_backup($request) {
        $site_url = $request->get_param('site');
        $backup_id = $request->get_param('id');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $delete_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups/' . $backup_id);

        $response = wp_remote_request($delete_url, array(
            'method' => 'DELETE',
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid response from agent',
            ), 500);
        }

        return new WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }

    /**
     * Start restore on agent
     */
    public function start_restore($request) {
        $site_url = $request->get_param('site');
        $backup_id = $request->get_param('id');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $restore_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/restore');

        $response = wp_remote_post($restore_url, array(
            'timeout' => 600,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('id' => $backup_id)),
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid response from agent',
            ), 500);
        }

        return new WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }

    /**
     * Get restore status from agent
     */
    public function get_restore_status($request) {
        $site_url = $request->get_param('site');

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Agent not found',
            ), 404);
        }

        $status_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/restore/status');

        $response = wp_remote_get($status_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $response->get_error_message(),
            ), 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid response from agent',
            ), 500);
        }

        return new WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }

    /**
     * Check if user has permission to access API
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }
}
