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
        // Trigger backup
        register_rest_route($this->namespace, '/backup', array(
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
        register_rest_route($this->namespace, '/backups', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_backups'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Restore backup
        register_rest_route($this->namespace, '/restore', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'restore_backup'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'id' => array(
                    'type' => 'integer',
                    'required' => true,
                    'description' => 'Backup timestamp ID',
                ),
            ),
        ));

        // Get restore status
        register_rest_route($this->namespace, '/restore/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_restore_status'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Get or Delete backup
        register_rest_route($this->namespace, '/backups/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_backup'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'required' => true,
                    ),
                ),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_backup'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'id' => array(
                        'type' => 'integer',
                        'required' => true,
                    ),
                ),
            ),
        ));
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
            // Trigger the backup (UpdraftPlus will generate the timestamp)
            $result = $updraftplus->boot_backup($backup_files, $backup_database);

            if ($result) {
                // Get the actual backup ID/timestamp from UpdraftPlus
                $backup_id = $updraftplus->backup_time;
                $nonce = $updraftplus->file_nonce;

                // Store backup job info
                set_transient('watchtower_backup_' . $backup_id, array(
                    'status' => 'running',
                    'percent_complete' => 5,
                    'type' => $type,
                    'nonce' => $nonce,
                    'started_at' => current_time('mysql'),
                ), 3600); // Keep for 1 hour

                // Schedule pruning after backup completes (wait 5 minutes)
                wp_schedule_single_event(time() + 300, 'watchtower_agent_prune_backups');

                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'Backup started successfully',
                    'id' => $backup_id,
                    'nonce' => $nonce,
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
        $updraft_dir = $updraftplus->backups_dir_location();

        $backups = array();
        foreach ($backup_history as $timestamp => $backup) {
            // Skip backups that don't have their database file
            // Without the DB file, a backup cannot be restored
            if (!empty($backup['db'])) {
                $db_file = $updraft_dir . '/' . $backup['db'];
                if (!file_exists($db_file)) {
                    error_log('Watchtower Agent: Skipping backup ' . $timestamp . ' - database file missing: ' . $backup['db']);
                    continue; // Skip this backup
                }
            } else {
                // No database at all - skip this backup
                continue;
            }

            $backup_data = array(
                'id' => $timestamp,
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'nonce' => isset($backup['nonce']) ? $backup['nonce'] : '',
                'complete' => true,
            );

            // Check which components are available and calculate total size
            $components = array();
            $total_size = 0;

            if (!empty($backup['db'])) {
                $components[] = 'database';
                $total_size += isset($backup['db-size']) ? $backup['db-size'] : 0;
            }
            if (!empty($backup['plugins'])) {
                $components[] = 'plugins';
                $total_size += isset($backup['plugins-size']) ? $backup['plugins-size'] : 0;
            }
            if (!empty($backup['themes'])) {
                $components[] = 'themes';
                $total_size += isset($backup['themes-size']) ? $backup['themes-size'] : 0;
            }
            if (!empty($backup['uploads'])) {
                $components[] = 'uploads';
                $total_size += isset($backup['uploads-size']) ? $backup['uploads-size'] : 0;
            }
            if (!empty($backup['others'])) {
                $components[] = 'others';
                $total_size += isset($backup['others-size']) ? $backup['others-size'] : 0;
            }

            $backup_data['components'] = $components;
            $backup_data['size'] = $total_size;
            $backup_data['complete'] = !empty($backup['db']) && count($components) > 1;

            $backups[] = $backup_data;
        }

        // Sort by timestamp (newest first)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return new WP_REST_Response(array(
            'success' => true,
            'backups' => $backups,
            'count' => count($backups),
        ), 200);
    }

    /**
     * Restore backup
     */
    public function restore_backup($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
            ), 400);
        }

        $timestamp = $request->get_param('id');

        global $updraftplus;

        // Get backup history
        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (!isset($backup_history[$timestamp])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup not found',
            ), 404);
        }

        $backup = $backup_history[$timestamp];

        // Validate backup has necessary components
        if (empty($backup['db'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup does not contain database - cannot restore',
            ), 400);
        }

        // Execute restore synchronously with long timeout
        error_log('Watchtower: Starting restore process for backup: ' . $timestamp);

        // Set up PHP to allow long-running process
        ignore_user_abort(true);
        set_time_limit(600); // 10 minutes max

        // Start output buffering to capture UpdraftPlus output
        // UpdraftPlus logs to stdout which corrupts the JSON response
        ob_start();

        // Execute the restore action immediately
        do_action('watchtower_agent_execute_restore', $timestamp);

        // Discard UpdraftPlus output
        $restore_output = ob_get_clean();
        if (!empty($restore_output)) {
            error_log('Watchtower: Captured and discarded UpdraftPlus output (' . strlen($restore_output) . ' bytes)');
        }

        error_log('Watchtower: Restore action completed for backup: ' . $timestamp);

        // Return response after restore completes
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Restore completed',
            'timestamp' => $timestamp,
            'components' => array('db', 'plugins', 'themes', 'uploads', 'others'),
        ), 200);
    }

    /**
     * Get restore status
     */
    public function get_restore_status($request) {
        // Check if there's a restore in progress
        $restore_job_id = get_site_option('updraft_restore_in_progress');
        $progress = get_site_option('watchtower_restore_progress', false);

        if (!$restore_job_id && $progress === false) {
            return new WP_REST_Response(array(
                'success' => true,
                'status' => 'idle',
                'percent_complete' => 0,
                'message' => 'No restore in progress',
            ), 200);
        }

        // Determine status based on progress
        $status = 'running';
        $message = 'Restore in progress';
        $percent_complete = $progress !== false ? (int)$progress : 0;

        if ($progress === false && !$restore_job_id) {
            $status = 'idle';
            $percent_complete = 0;
            $message = 'No restore in progress';
        } elseif ($progress == 100) {
            $status = 'complete';
            $percent_complete = 100;
            $message = 'Restore completed successfully';
        } elseif ($progress == -1) {
            $status = 'error';
            $percent_complete = 0;
            $message = 'Restore failed';
        } else {
            // Still running
            if ($percent_complete < 30) {
                $message = 'Preparing restore...';
            } elseif ($percent_complete < 60) {
                $message = 'Restoring database...';
            } elseif ($percent_complete < 90) {
                $message = 'Restoring files...';
            } else {
                $message = 'Finishing restore...';
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'status' => $status,
            'percent_complete' => $percent_complete,
            'message' => $message,
            'job_id' => $restore_job_id,
        ), 200);
    }

    /**
     * Get backup status
     */
    public function get_backup($request) {
        if (!class_exists('UpdraftPlus')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'UpdraftPlus plugin is not installed or activated',
            ), 400);
        }

        $timestamp = $request->get_param('id');

        // First check if backup exists in history (completed)
        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (isset($backup_history[$timestamp])) {
            $backup = $backup_history[$timestamp];

            // Check which components are available and calculate total size
            $components = array();
            $total_size = 0;

            if (!empty($backup['db'])) {
                $components[] = 'database';
                $total_size += isset($backup['db-size']) ? $backup['db-size'] : 0;
            }
            if (!empty($backup['plugins'])) {
                $components[] = 'plugins';
                $total_size += isset($backup['plugins-size']) ? $backup['plugins-size'] : 0;
            }
            if (!empty($backup['themes'])) {
                $components[] = 'themes';
                $total_size += isset($backup['themes-size']) ? $backup['themes-size'] : 0;
            }
            if (!empty($backup['uploads'])) {
                $components[] = 'uploads';
                $total_size += isset($backup['uploads-size']) ? $backup['uploads-size'] : 0;
            }
            if (!empty($backup['others'])) {
                $components[] = 'others';
                $total_size += isset($backup['others-size']) ? $backup['others-size'] : 0;
            }

            return new WP_REST_Response(array(
                'success' => true,
                'id' => $timestamp,
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'nonce' => isset($backup['nonce']) ? $backup['nonce'] : '',
                'components' => $components,
                'size' => $total_size,
                'complete' => !empty($backup['db']) && count($components) > 1,
                'status' => 'complete',
                'percent_complete' => 100,
            ), 200);
        }

        // Check if backup is in progress
        $job_data = get_transient('watchtower_backup_' . $timestamp);

        if ($job_data) {
            return new WP_REST_Response(array(
                'success' => true,
                'id' => $timestamp,
                'timestamp' => $timestamp,
                'status' => $job_data['status'],
                'percent_complete' => $job_data['percent_complete'],
                'type' => $job_data['type'],
                'started_at' => $job_data['started_at'],
                'error' => isset($job_data['error']) ? $job_data['error'] : null,
            ), 200);
        }

        // Backup not found
        return new WP_REST_Response(array(
            'success' => false,
            'error' => 'Backup not found',
        ), 404);
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

        $timestamp = $request->get_param('id');

        // Load UpdraftPlus admin if not loaded
        global $updraftplus_admin;
        if (!$updraftplus_admin || !method_exists($updraftplus_admin, 'delete_set')) {
            require_once WP_PLUGIN_DIR . '/updraftplus/admin.php';
            $updraftplus_admin = new UpdraftPlus_Admin();
        }

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

        $opts = array(
            'what' => $timestamp,
            'nonce' => $nonce,
        );

        $updraftplus_admin->delete_set($opts);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Backup deleted successfully',
            'id' => $timestamp,
        ), 200);
    }

    /**
     * Check permission
     */
    public function check_permission() {
        return current_user_can('manage_options');
    }
}
