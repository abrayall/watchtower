<?php
/**
 * User Management Endpoint
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_User_Management {

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
        register_rest_route($this->namespace, '/users', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_users'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'role' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'search' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));

        register_rest_route($this->namespace, '/users', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'create_user'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'username' => array(
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_user',
                ),
                'email' => array(
                    'type' => 'string',
                    'required' => true,
                    'sanitize_callback' => 'sanitize_email',
                ),
                'password' => array(
                    'type' => 'string',
                    'required' => true,
                ),
                'role' => array(
                    'type' => 'string',
                    'required' => false,
                    'default' => 'subscriber',
                ),
                'first_name' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'last_name' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        register_rest_route($this->namespace, '/users/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_user'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));

        register_rest_route($this->namespace, '/users/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array($this, 'update_user'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'email' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_email',
                ),
                'role' => array(
                    'type' => 'string',
                    'required' => false,
                ),
                'first_name' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'last_name' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'password' => array(
                    'type' => 'string',
                    'required' => false,
                ),
            ),
        ));

        register_rest_route($this->namespace, '/users/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_user'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
                'reassign' => array(
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'User ID to reassign posts to',
                ),
            ),
        ));
    }

    /**
     * List users
     */
    public function list_users($request) {
        $args = array(
            'fields' => array('ID', 'user_login', 'user_email', 'display_name'),
        );

        if ($request->get_param('role')) {
            $args['role'] = $request->get_param('role');
        }

        if ($request->get_param('search')) {
            $args['search'] = '*' . $request->get_param('search') . '*';
        }

        $users = get_users($args);

        $formatted_users = array();
        foreach ($users as $user) {
            $user_data = get_userdata($user->ID);
            $formatted_users[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user_data->roles,
                'registered' => $user_data->user_registered,
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'users' => $formatted_users,
            'count' => count($formatted_users),
        ), 200);
    }

    /**
     * Create user
     */
    public function create_user($request) {
        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $role = $request->get_param('role');

        if (username_exists($username)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Username already exists',
            ), 400);
        }

        if (email_exists($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Email already exists',
            ), 400);
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $user_id->get_error_message(),
            ), 400);
        }

        $user = new WP_User($user_id);
        $user->set_role($role);

        if ($request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', $request->get_param('first_name'));
        }

        if ($request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', $request->get_param('last_name'));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email,
            'role' => $role,
        ), 201);
    }

    /**
     * Get user
     */
    public function get_user($request) {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found',
            ), 404);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'user' => array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'roles' => $user->roles,
                'registered' => $user->user_registered,
            ),
        ), 200);
    }

    /**
     * Update user
     */
    public function update_user($request) {
        $user_id = $request->get_param('id');

        if (!get_userdata($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found',
            ), 404);
        }

        $user_data = array('ID' => $user_id);

        if ($request->get_param('email')) {
            $email_exists = email_exists($request->get_param('email'));
            if ($email_exists && $email_exists != $user_id) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Email already exists',
                ), 400);
            }
            $user_data['user_email'] = $request->get_param('email');
        }

        if ($request->get_param('password')) {
            $user_data['user_pass'] = $request->get_param('password');
        }

        $result = wp_update_user($user_data);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result->get_error_message(),
            ), 400);
        }

        if ($request->get_param('role')) {
            $user = new WP_User($user_id);
            $user->set_role($request->get_param('role'));
        }

        if ($request->get_param('first_name')) {
            update_user_meta($user_id, 'first_name', $request->get_param('first_name'));
        }

        if ($request->get_param('last_name')) {
            update_user_meta($user_id, 'last_name', $request->get_param('last_name'));
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'User updated successfully',
            'user_id' => $user_id,
        ), 200);
    }

    /**
     * Delete user
     */
    public function delete_user($request) {
        $user_id = $request->get_param('id');
        $reassign = $request->get_param('reassign');

        if (!get_userdata($user_id)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'User not found',
            ), 404);
        }

        if ($user_id === get_current_user_id()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Cannot delete currently logged in user',
            ), 400);
        }

        require_once(ABSPATH . 'wp-admin/includes/user.php');

        $result = wp_delete_user($user_id, $reassign);

        if (!$result) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to delete user',
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'User deleted successfully',
            'user_id' => $user_id,
        ), 200);
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }
}
