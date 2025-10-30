<?php
/**
 * Main Plugin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent {

    /**
     * Plugin version
     */
    private $version = WATCHTOWER_AGENT_VERSION;

    /**
     * REST API namespace
     */
    private $namespace = 'watchtower-agent/v1';

    /**
     * Initialize the plugin
     */
    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));

        add_action('init', array($this, 'setup_capabilities'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $controller = new Watchtower_Agent_REST_Controller($this->namespace);
        $controller->register_routes();
    }

    /**
     * Setup custom capabilities
     */
    public function setup_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_remote_users');
            $admin_role->add_cap('manage_remote_backups');
            $admin_role->add_cap('manage_remote_updates');
        }
    }

    /**
     * Get plugin version
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get API namespace
     */
    public function get_namespace() {
        return $this->namespace;
    }
}
