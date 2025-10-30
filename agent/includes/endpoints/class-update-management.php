<?php
/**
 * Update Management Endpoint (Core, Plugins, Themes)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Update_Management {

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
        // Check for available updates
        register_rest_route($this->namespace, '/updates/check', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'check_updates'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Update WordPress core
        register_rest_route($this->namespace, '/updates/core', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_core'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Update all plugins
        register_rest_route($this->namespace, '/updates/plugins', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_all_plugins'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Update specific plugin
        register_rest_route($this->namespace, '/updates/plugin/(?P<plugin>[a-zA-Z0-9_-]+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_plugin'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'plugin' => array(
                    'type' => 'string',
                    'required' => true,
                ),
            ),
        ));

        // Update all themes
        register_rest_route($this->namespace, '/updates/themes', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_all_themes'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Update specific theme
        register_rest_route($this->namespace, '/updates/theme/(?P<theme>[a-zA-Z0-9_-]+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_theme'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'theme' => array(
                    'type' => 'string',
                    'required' => true,
                ),
            ),
        ));

        // Update everything
        register_rest_route($this->namespace, '/updates/all', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'update_all'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }

    /**
     * Check for available updates
     */
    public function check_updates($request) {
        // Force check for updates
        wp_update_plugins();
        wp_update_themes();
        do_action('wp_update_plugins');
        do_action('wp_update_themes');

        // Get core updates
        $core_updates = get_core_updates();
        $core_update_available = false;
        $core_version = null;

        if (!empty($core_updates) && $core_updates[0]->response == 'upgrade') {
            $core_update_available = true;
            $core_version = $core_updates[0]->version;
        }

        // Get plugin updates
        $plugin_updates = get_plugin_updates();
        $plugins_needing_update = array();

        foreach ($plugin_updates as $plugin_file => $plugin_data) {
            $plugins_needing_update[] = array(
                'plugin' => $plugin_file,
                'name' => $plugin_data->Name,
                'current_version' => $plugin_data->Version,
                'new_version' => $plugin_data->update->new_version,
            );
        }

        // Get theme updates
        $theme_updates = get_theme_updates();
        $themes_needing_update = array();

        foreach ($theme_updates as $theme_key => $theme_data) {
            $themes_needing_update[] = array(
                'theme' => $theme_key,
                'name' => $theme_data->get('Name'),
                'current_version' => $theme_data->get('Version'),
                'new_version' => $theme_data->update['new_version'],
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'wordpress' => array(
                'current_version' => get_bloginfo('version'),
                'update_available' => $core_update_available,
                'new_version' => $core_version,
            ),
            'plugins' => array(
                'updates_available' => count($plugins_needing_update),
                'plugins' => $plugins_needing_update,
            ),
            'themes' => array(
                'updates_available' => count($themes_needing_update),
                'themes' => $themes_needing_update,
            ),
        ), 200);
    }

    /**
     * Update WordPress core
     */
    public function update_core($request) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        // Check for updates
        $core_updates = get_core_updates();

        if (empty($core_updates) || $core_updates[0]->response != 'upgrade') {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No core updates available',
            ), 400);
        }

        $update = $core_updates[0];

        // Create upgrader instance
        $upgrader = new Core_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($update);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'WordPress core updated successfully',
            'version' => get_bloginfo('version'),
        ), 200);
    }

    /**
     * Update all plugins
     */
    public function update_all_plugins($request) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $plugin_updates = get_plugin_updates();

        if (empty($plugin_updates)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No plugin updates available',
            ), 400);
        }

        $plugins_to_update = array_keys($plugin_updates);
        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

        $results = array();
        $errors = array();

        foreach ($plugins_to_update as $plugin) {
            $result = $upgrader->upgrade($plugin);

            if (is_wp_error($result)) {
                $errors[] = array(
                    'plugin' => $plugin,
                    'error' => $result->get_error_message(),
                );
            } else {
                $results[] = array(
                    'plugin' => $plugin,
                    'status' => 'updated',
                );
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin updates completed',
            'updated' => $results,
            'errors' => $errors,
            'total_updated' => count($results),
            'total_errors' => count($errors),
        ), 200);
    }

    /**
     * Update specific plugin
     */
    public function update_plugin($request) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $plugin_slug = $request->get_param('plugin');
        $plugin_updates = get_plugin_updates();

        // Find the plugin file
        $plugin_file = null;
        foreach ($plugin_updates as $file => $plugin_data) {
            if (strpos($file, $plugin_slug) !== false) {
                $plugin_file = $file;
                break;
            }
        }

        if (!$plugin_file) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Plugin not found or no update available',
            ), 404);
        }

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($plugin_file);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Plugin updated successfully',
            'plugin' => $plugin_file,
        ), 200);
    }

    /**
     * Update all themes
     */
    public function update_all_themes($request) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $theme_updates = get_theme_updates();

        if (empty($theme_updates)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No theme updates available',
            ), 400);
        }

        $themes_to_update = array_keys($theme_updates);
        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());

        $results = array();
        $errors = array();

        foreach ($themes_to_update as $theme) {
            $result = $upgrader->upgrade($theme);

            if (is_wp_error($result)) {
                $errors[] = array(
                    'theme' => $theme,
                    'error' => $result->get_error_message(),
                );
            } else {
                $results[] = array(
                    'theme' => $theme,
                    'status' => 'updated',
                );
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Theme updates completed',
            'updated' => $results,
            'errors' => $errors,
            'total_updated' => count($results),
            'total_errors' => count($errors),
        ), 200);
    }

    /**
     * Update specific theme
     */
    public function update_theme($request) {
        require_once ABSPATH . 'wp-admin/includes/admin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $theme_slug = $request->get_param('theme');
        $theme_updates = get_theme_updates();

        if (!isset($theme_updates[$theme_slug])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Theme not found or no update available',
            ), 404);
        }

        $upgrader = new Theme_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->upgrade($theme_slug);

        if (is_wp_error($result)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $result->get_error_message(),
            ), 400);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Theme updated successfully',
            'theme' => $theme_slug,
        ), 200);
    }

    /**
     * Update everything (core, plugins, themes)
     */
    public function update_all($request) {
        $results = array(
            'core' => null,
            'plugins' => null,
            'themes' => null,
        );

        // Update core
        $core_result = $this->update_core($request);
        $core_data = $core_result->get_data();
        $results['core'] = $core_data;

        // Update plugins
        $plugins_result = $this->update_all_plugins($request);
        $plugins_data = $plugins_result->get_data();
        $results['plugins'] = $plugins_data;

        // Update themes
        $themes_result = $this->update_all_themes($request);
        $themes_data = $themes_result->get_data();
        $results['themes'] = $themes_data;

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'All updates completed',
            'results' => $results,
        ), 200);
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('update_core') && current_user_can('update_plugins') && current_user_can('update_themes');
    }
}
