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

        register_rest_route($this->namespace, '/backups', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'list_backups'),
            'permission_callback' => array($this, 'check_permission'),
        ));

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

        register_rest_route($this->namespace, '/restore/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_restore_status'),
            'permission_callback' => array($this, 'check_permission'),
        ));

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

        $type = $request->get_param('type');

        $backup_files = true;
        $backup_database = true;

        if ($type === 'database') {
            $backup_files = false;
        } elseif ($type === 'files') {
            $backup_database = false;
        }

        $backup_id = time();
        $nonce = substr(md5(time().rand()), 20);

        set_transient('watchtower_backup_' . $backup_id, array(
            'status' => 'running',
            'percent_complete' => 5,
            'type' => $type,
            'nonce' => $nonce,
            'started_at' => current_time('mysql'),
        ), 3600);

        wp_schedule_single_event(time() + 300, 'watchtower_agent_prune_backups');

        $response_data = array(
            'success' => true,
            'message' => 'Backup started successfully',
            'id' => $backup_id,
            'nonce' => $nonce,
            'type' => $type,
            'backup_files' => $backup_files,
            'backup_database' => $backup_database,
        );

        $json = json_encode($response_data);

        header('Content-Length: ' . (4 + strlen($json)));
        header('Connection: close');
        header('Content-Type: application/json');
        if (function_exists('session_id') && session_id()) {
            session_write_close();
        }
        echo "\r\n\r\n";
        echo $json;

        $ob_level = ob_get_level();
        while ($ob_level > 0) {
            ob_end_flush();
            $ob_level--;
        }
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        global $updraftplus;

        $options = array(
            'use_timestamp' => $backup_id,
            'use_nonce' => $nonce,
        );

        $updraftplus->boot_backup($backup_files, $backup_database, false, false, false, $options);

        die();
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

        $backup_history = UpdraftPlus_Backup_History::get_history();
        $updraft_dir = $updraftplus->backups_dir_location();

        $backups = array();
        foreach ($backup_history as $timestamp => $backup) {
            if (!empty($backup['db'])) {
                $db_file = $updraft_dir . '/' . $backup['db'];
                if (!file_exists($db_file)) {
                    error_log('Watchtower Agent: Skipping backup ' . $timestamp . ' - database file missing: ' . $backup['db']);
                    continue; // Skip this backup
                }
            } else {
                continue;
            }

            $backup_data = array(
                'id' => $timestamp,
                'timestamp' => $timestamp,
                'date' => date('Y-m-d H:i:s', $timestamp),
                'nonce' => isset($backup['nonce']) ? $backup['nonce'] : '',
                'complete' => true,
            );

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

        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (!isset($backup_history[$timestamp])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup not found',
            ), 404);
        }

        $backup = $backup_history[$timestamp];

        if (empty($backup['db'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup does not contain database - cannot restore',
            ), 400);
        }

        $response_data = array(
            'success' => true,
            'message' => 'Restore started',
            'timestamp' => $timestamp,
        );

        $json = json_encode($response_data);

        header('Content-Length: ' . (4 + strlen($json)));
        header('Connection: close');
        header('Content-Type: application/json');
        if (function_exists('session_id') && session_id()) {
            session_write_close();
        }
        echo "\r\n\r\n";
        echo $json;

        $ob_level = ob_get_level();
        while ($ob_level > 0) {
            ob_end_flush();
            $ob_level--;
        }
        flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        set_time_limit(600);

        do_action('watchtower_agent_execute_restore', $timestamp);

        die();
    }

    /**
     * Get restore status
     */
    public function get_restore_status($request) {
        $restore_job_id = get_site_option('updraft_restore_in_progress');

        if (!$restore_job_id) {
            return new WP_REST_Response(array(
                'success' => true,
                'status' => 'idle',
                'percent_complete' => 0,
                'message' => 'No restore in progress',
            ), 200);
        }

        global $updraftplus;
        $jobdata = $updraftplus->jobdata_getarray($restore_job_id);

        $status = 'running';
        $percent_complete = 0;
        $message = 'Restore in progress';

        if (!empty($jobdata)) {
            $jobstatus = empty($jobdata['jobstatus']) ? 'begun' : $jobdata['jobstatus'];

            switch ($jobstatus) {
                case 'begun':
                    $percent_complete = 5;
                    $message = 'Restore begun';
                    break;
                case 'downloading':
                    $percent_complete = 10;
                    $message = 'Downloading backup files';
                    break;
                case 'downloaded':
                    $percent_complete = 20;
                    $message = 'Backup files downloaded';
                    break;
                case 'restoring':
                    $percent_complete = 30;
                    $message = 'Restoring files';
                    if (!empty($jobdata['restore_entity'])) {
                        $message = 'Restoring ' . $jobdata['restore_entity'];
                        if (!empty($jobdata['restore_progress'])) {
                            $progress = min((float)$jobdata['restore_progress'], 1);
                            $percent_complete = 30 + (60 * $progress);
                        }
                    }
                    break;
                case 'finishing':
                    $percent_complete = 95;
                    $message = 'Finishing restore';
                    break;
                case 'finished':
                    $percent_complete = 100;
                    $message = 'Restore completed successfully';
                    $status = 'complete';
                    break;
            }
        }

        $custom_progress = get_site_option('watchtower_restore_progress', false);
        if ($custom_progress !== false) {
            if ($custom_progress == -1) {
                $status = 'error';
                $percent_complete = 0;
                $message = 'Restore failed';
            } elseif ($custom_progress == 100) {
                $status = 'complete';
                $percent_complete = 100;
                $message = 'Restore completed successfully';
            } elseif ($custom_progress > $percent_complete) {
                $percent_complete = (int)$custom_progress;
            }
        }

        return new WP_REST_Response(array(
            'success' => true,
            'status' => $status,
            'percent_complete' => round($percent_complete),
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

        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (isset($backup_history[$timestamp])) {
            $backup = $backup_history[$timestamp];

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

        $job_data = get_transient('watchtower_backup_' . $timestamp);

        if ($job_data) {
            global $updraftplus;
            $jobdata = $updraftplus->jobdata_getarray($job_data['nonce']);

            $status = 'running';
            $percent_complete = 5;
            $message = 'Starting backup...';

            if (!empty($jobdata)) {
                $jobstatus = empty($jobdata['jobstatus']) ? 'begun' : $jobdata['jobstatus'];

                switch ($jobstatus) {
                    case 'begun':
                        $percent_complete = 10;
                        $message = 'Backup begun';
                        break;
                    case 'filescreating':
                        $percent_complete = 20;
                        $message = 'Creating file backup zips';
                        if (!empty($jobdata['filecreating_substatus'])) {
                            if (isset($jobdata['filecreating_substatus']['i']) && isset($jobdata['filecreating_substatus']['t'])) {
                                $t = max((int)$jobdata['filecreating_substatus']['t'], 1);
                                $progress = $jobdata['filecreating_substatus']['i'] / $t;
                                $percent_complete = 20 + (30 * $progress);
                            }
                        }
                        break;
                    case 'filescreated':
                        $percent_complete = 50;
                        $message = 'Created file backup zips';
                        break;
                    case 'clouduploading':
                    case 'partialclouduploading':
                        $percent_complete = 60;
                        $message = 'Uploading files to remote storage';
                        if (!empty($jobdata['uploading_substatus'])) {
                            if (isset($jobdata['uploading_substatus']['i']) && isset($jobdata['uploading_substatus']['t'])) {
                                $t = max((int)$jobdata['uploading_substatus']['t'], 1);
                                $progress = $jobdata['uploading_substatus']['i'] / $t;
                                $percent_complete = 60 + (30 * $progress);
                            }
                        }
                        break;
                    case 'pruning':
                        $percent_complete = 95;
                        $message = 'Pruning old backups';
                        break;
                    case 'finished':
                        $percent_complete = 100;
                        $message = 'Backup complete';
                        $status = 'complete';
                        break;
                }
            }

            return new WP_REST_Response(array(
                'success' => true,
                'id' => $timestamp,
                'timestamp' => $timestamp,
                'status' => $status,
                'percent_complete' => round($percent_complete),
                'message' => $message,
                'type' => $job_data['type'],
                'started_at' => $job_data['started_at'],
            ), 200);
        }

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

        global $updraftplus_admin;
        if (!$updraftplus_admin || !method_exists($updraftplus_admin, 'delete_set')) {
            require_once WP_PLUGIN_DIR . '/updraftplus/admin.php';
            $updraftplus_admin = new UpdraftPlus_Admin();
        }

        $backup_history = UpdraftPlus_Backup_History::get_history();

        if (!isset($backup_history[$timestamp])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Backup not found',
            ), 404);
        }

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
