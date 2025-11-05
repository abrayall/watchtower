<?php

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Maintenance_Management {

    private $namespace;

    public function __construct($namespace) {
        $this->namespace = $namespace;
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/maintenance', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_status'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route($this->namespace, '/maintenance', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'set_status'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'enabled' => array(
                    'type' => 'boolean',
                    'required' => true,
                    'description' => 'Enable or disable maintenance mode',
                ),
                'headline' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'message' => array(
                    'type' => 'string',
                    'required' => false,
                    'sanitize_callback' => 'wp_kses_post',
                ),
            ),
        ));
    }

    public function get_status($request) {
        $intermission_active = $this->is_intermission_active();

        if (!$intermission_active) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Intermission plugin is not installed or active',
                'maintenance_enabled' => false,
            ), 400);
        }

        $is_enabled = get_option('intermission_enabled', false);
        $headline = get_option('intermission_headline', '');
        $message = get_option('intermission_message', '');
        $countdown_gmt = get_option('intermission_countdown_gmt', 0);
        $auto_enable_gmt = get_option('intermission_auto_enable_gmt', 0);
        $auto_disable = get_option('intermission_auto_disable', false);

        return new WP_REST_Response(array(
            'success' => true,
            'maintenance_enabled' => (bool) $is_enabled,
            'settings' => array(
                'headline' => $headline,
                'message' => $message,
                'countdown_gmt' => $countdown_gmt,
                'auto_enable_gmt' => $auto_enable_gmt,
                'auto_disable' => (bool) $auto_disable,
            ),
        ), 200);
    }

    public function set_status($request) {
        $enabled = $request->get_param('enabled');
        $headline = $request->get_param('headline');
        $message = $request->get_param('message');

        $intermission_active = $this->is_intermission_active();

        if (!$intermission_active) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Intermission plugin is not installed or active',
            ), 400);
        }

        update_option('intermission_enabled', $enabled);

        if ($headline !== null) {
            update_option('intermission_headline', $headline);
        }

        if ($message !== null) {
            update_option('intermission_message', $message);
        }

        $status = $enabled ? 'enabled' : 'disabled';

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Maintenance mode ' . $status,
            'maintenance_enabled' => $enabled,
        ), 200);
    }

    private function is_intermission_active() {
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        return is_plugin_active('intermission/intermission.php');
    }

    public function check_permission() {
        return current_user_can('manage_options');
    }
}
