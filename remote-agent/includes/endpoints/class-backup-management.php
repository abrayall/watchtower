<?php
/**
 * Backup Management Endpoint (UpdraftPlus Integration)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Backup_Management {

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
        // Check UpdraftPlus status
        register_rest_route($this->namespace, '/backup/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_backup_status'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Trigger backup
        register_rest_route($this->namespace, '/backup/run', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'run_backup'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'type' => array(
                    'type' => 'string',
                    'required' => false,
                    'default' => 'full',
                    'enum' => array('full', 'database', 'files'),
                ),
            ),
        ));

        // List backups
        register_rest_route($this->namespace, '/backup/list', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_backups'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Delete backup
        register_rest_route($this->namespace, '/backup/(?P<timestamp>\d+)', array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'delete_backup'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'timestamp' => array(
                    'type' => 'integer',
                    'required' => true,
                ),
            ),
        ));
    }

    /**
     * Get backup status
     */
    public function get_backup_status($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
                'updraftplus_installed' => false,
            ), 400);
        }

        global $updraftplus;

        $status = array(
            'success' => true,
            'updraftplus_installed' => true,
            'updraftplus_version' => $updraftplus->version,
        );

        // Get last backup time
        $last_backup = UpdraftPlus_Options::get_updraft_option('updraft_last_backup');
        if ($last_backup) {
            $status['last_backup'] = $last_backup;
        }

        // Check if backup is currently running
        $backup_running = UpdraftPlus_Options::get_updraft_option('updraft_jobdata_*');
        $status['backup_running'] = !empty($backup_running);

        return new WP_REST_Response($status, 200);
    }

    /**
     * Run backup
     */
    public function run_backup($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
            ), 400);
        }

        global $updraftplus;

        $type = $request->get_param('type');

        // Prepare backup options
        $backup_files = true;
        $backup_database = true;

        if ($type === 'database') {
            $backup_files = false;
        } elseif ($type === 'files') {
            $backup_database = false;
        }

        try {
            // Trigger the backup
            $result = $updraftplus->boot_backup($backup_files, $backup_database);

            if ($result) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Backup started successfully',
                    'type' => $type,
                    'backup_files' => $backup_files,
                    'backup_database' => $backup_database,
                ), 200);
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => 'Failed to start backup',
                ), 400);
            }
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
            ), 500);
        }
    }

    /**
     * List backups
     */
    public function list_backups($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
            ), 400);
        }

        global $updraftplus;

        // Get backup history
        $backup_history = UpdraftPlus_Backup_History::get_history();

        $backups = array();
        foreach ($backup_history as $timestamp => $backup) {
            $backups[] = array(
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'nonce' => isset($backup['nonce']) ? $backup['nonce'] : '',
                'files' => isset($backup['files']) ? array_keys($backup['files']) : array(),
                'complete' => !empty($backup['db']) && !empty($backup['plugins']),
            );
        }

        return new WP_REST_Response(array(
            'success' => true,
            'backups' => $backups,
            'count' => count($backups),
        ), 200);
    }

    /**
     * Delete backup
     */
    public function delete_backup($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
            ), 400);
        }

        $timestamp = $request->get_param('timestamp');

        global $updraftplus;

        // Get backup history
        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (!isset($backup_history[$timestamp])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup not found',
            ), 404);
        }

        // Delete the backup
        $backup = $backup_history[$timestamp];
        $nonce = isset($backup['nonce']) ? $backup['nonce'] : '';

        $updraftplus->delete_set($timestamp, $nonce);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Backup deleted successfully',
            'timestamp' => $timestamp,
        ), 200);
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }
}
