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
     * Agent storage instance
     */
    private $storage;

    /**
     * Constructor
     */
    public function __construct($namespace) {
        $this->namespace = $namespace;
        $this->storage = new Watchtower_Manager_Agent_Storage();
    }

    /**
     * Register all REST API routes
     */
    public function register_routes() {
        // Agent registration endpoint (public)
        register_rest_route($this->namespace, '/register', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'register_agent'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'site_url' => array(
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

        // List all agents endpoint (protected)
        register_rest_route($this->namespace, '/agents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_agents'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Get specific agent endpoint (protected)
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

        // Delete agent endpoint (protected)
        register_rest_route($this->namespace, '/agents', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_agent'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'site_url' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));

        // Manager status endpoint (public)
        register_rest_route($this->namespace, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
    }

    /**
     * Register a new agent
     */
    public function register_agent($request) {
        $site_url = $request->get_param('site_url');
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        // Validate required fields
        if (empty($site_url) || empty($username) || empty($password)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Missing required fields: site_url, username, password',
            ), 400);
        }

        // Prepare agent data
        $agent_data = array(
            'site_url' => $site_url,
            'username' => $username,
            'password' => $password,
            'wordpress_version' => $request->get_param('wordpress_version') ?: 'unknown',
            'php_version' => $request->get_param('php_version') ?: 'unknown',
            'agent_version' => $request->get_param('agent_version') ?: 'unknown',
        );

        // Save agent
        $result = $this->storage->save_agent($agent_data);

        if ($result) {
            // Get the saved agent data
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

        // Mask passwords for security
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
        $site_url = $request->get_param('site_url');

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
     * Check if user has permission to access API
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }
}
