<?php
/**
 * Main Plugin Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager {

    /**
     * Plugin version
     */
    private $version = WATCHTOWER_MANAGER_VERSION;

    /**
     * REST API namespace
     */
    private $namespace = 'watchtower-manager/v1';

    /**
     * Initialize the plugin
     */
    public function init() {
        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $controller = new Watchtower_Manager_REST_Controller($this->namespace);
        $controller->register_routes();
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
