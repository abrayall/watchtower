<?php
/**
 * Admin Dashboard Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Admin_Dashboard {

    /**
     * Agent storage instance
     */
    private $storage;

    /**
     * Health storage instance
     */
    private $health_storage;

    /**
     * Constructor
     */
    public function __construct() {
        $this->storage = new Watchtower_Manager_Agent_Storage();
        $this->health_storage = new Watchtower_Manager_Health_Storage();
    }

    /**
     * Initialize admin dashboard
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        add_action('wp_ajax_watchtower_manager_remove_agent', array($this, 'ajax_remove_agent'));
        add_action('wp_ajax_watchtower_manager_update_agent', array($this, 'ajax_update_agent'));
        add_action('wp_ajax_watchtower_manager_scan_agent', array($this, 'ajax_scan_agent'));
        add_action('wp_ajax_watchtower_manager_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_watchtower_manager_get_available_logs', array($this, 'ajax_get_available_logs'));
        add_action('wp_ajax_watchtower_manager_toggle_debug', array($this, 'ajax_toggle_debug'));
        add_action('wp_ajax_watchtower_manager_get_backups', array($this, 'ajax_get_backups'));
        add_action('wp_ajax_watchtower_manager_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_watchtower_manager_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_watchtower_manager_get_restore_status', array($this, 'ajax_get_restore_status'));
        add_action('wp_ajax_watchtower_manager_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_watchtower_manager_get_agent', array($this, 'ajax_get_agent'));
        add_action('wp_ajax_watchtower_manager_get_activity_logs', array($this, 'ajax_get_activity_logs'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Remote Sites',           // Page title
            'Sites',                  // Menu title
            'manage_options',         // Capability
            'watchtower-manager',      // Menu slug
            array($this, 'render_dashboard'), // Callback
            'dashicons-admin-site-alt3', // Icon
            30                        // Position
        );

        add_submenu_page(
            'watchtower-manager',
            'All Sites',
            'All Sites',
            'manage_options',
            'watchtower-manager',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'watchtower-manager',
            'Settings',
            'Settings',
            'manage_options',
            'watchtower-manager-settings',
            array($this, 'render_settings')
        );

        add_submenu_page(
            null,  // Hidden from menu
            'Site Details',
            'Site Details',
            'manage_options',
            'watchtower-manager-site-details',
            array($this, 'render_site_details')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'watchtower-manager') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.min.css');

        if (isset($_GET['site'])) {
            wp_enqueue_style(
                'watchtower-details',
                plugins_url('assets/css/details.css', dirname(__FILE__)),
                array(),
                WATCHTOWER_MANAGER_VERSION
            );

            wp_enqueue_script(
                'watchtower-details',
                plugins_url('assets/js/details.js', dirname(__FILE__)),
                array('jquery', 'jquery-ui-datepicker'),
                WATCHTOWER_MANAGER_VERSION,
                true
            );

            wp_localize_script('watchtower-details', 'watchtower', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('watchtower_manager_nonce'),
                'siteUrl' => $_GET['site']
            ));
        } else {
            wp_enqueue_style(
                'watchtower-dashboard',
                plugins_url('assets/css/dashboard.css', dirname(__FILE__)),
                array(),
                WATCHTOWER_MANAGER_VERSION
            );

            wp_enqueue_script(
                'watchtower-dashboard',
                plugins_url('assets/js/dashboard.js', dirname(__FILE__)),
                array('jquery'),
                WATCHTOWER_MANAGER_VERSION,
                true
            );

            wp_localize_script('watchtower-dashboard', 'watchtower', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('watchtower_manager_nonce')
            ));
        }
    }


    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $agents = $this->storage->get_all_agents();
        $agent_count = count($agents);

        $healthy_count = 0;
        $unhealthy_count = 0;
        $agents_with_health = array();

        foreach ($agents as $agent) {
            $health_data = $this->health_storage->get_health_data($agent['site_url']);
            $health_status = $this->determine_health_status($health_data);

            $agent['health_status'] = $health_status;
            $agents_with_health[] = $agent;

            if ($health_status === 'healthy') {
                $healthy_count++;
            } elseif ($health_status === 'warning' || $health_status === 'critical') {
                $unhealthy_count++;
            }
        }

        usort($agents_with_health, function($a, $b) {
            $a_priority = ($a['health_status'] === 'warning' || $a['health_status'] === 'critical') ? 0 : 1;
            $b_priority = ($b['health_status'] === 'warning' || $b['health_status'] === 'critical') ? 0 : 1;

            if ($a_priority !== $b_priority) {
                return $a_priority - $b_priority;
            }

            $a_name = isset($a['site_name']) ? $a['site_name'] : $a['site_url'];
            $b_name = isset($b['site_name']) ? $b['site_name'] : $b['site_url'];

            return strcasecmp($a_name, $b_name);
        });

        $agents = $agents_with_health;

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <span>Sites</span>
                <span style="font-size: 14px; font-weight: 400; color: #646970;">Manager: <?php echo esc_html(WATCHTOWER_MANAGER_VERSION); ?></span>
            </h1>
            <?php // <a href="#" class="page-title-action">Add New Site</a> ?>
            <hr class="wp-header-end">

            <div class="watchtower-manager-dashboard">
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card stat-total filter-card" data-filter="all" onclick="filterSites('all')">
                        <h3>
                            Total Sites
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                        </h3>
                        <div class="stat-value"><?php echo $agent_count; ?></div>
                    </div>
                    <?php
                    $healthy_status = 'stat-healthy-critical'; // Red - no healthy sites
                    $healthy_icon = 'dashicons-dismiss';

                    if ($healthy_count > 0) {
                        $majority = ceil($agent_count / 2);
                        if ($healthy_count >= $majority) {
                            $healthy_status = 'stat-healthy-good'; // Green - majority healthy
                            $healthy_icon = 'dashicons-yes-alt';
                        } else {
                            $healthy_status = 'stat-healthy-warning'; // Yellow - minority healthy
                            $healthy_icon = 'dashicons-info';
                        }
                    }
                    ?>
                    <div class="stat-card <?php echo $healthy_status; ?> filter-card" data-filter="healthy" onclick="filterSites('healthy')">
                        <h3>
                            Healthy
                            <span class="dashicons <?php echo $healthy_icon; ?>"></span>
                        </h3>
                        <div class="stat-value"><?php echo $healthy_count; ?></div>
                    </div>
                    <?php
                    $unhealthy_status = 'stat-unhealthy-none'; // Green - no unhealthy sites
                    $unhealthy_icon = 'dashicons-yes-alt';

                    if ($unhealthy_count > 0) {
                        $majority = ceil($agent_count / 2);
                        if ($unhealthy_count >= $majority) {
                            $unhealthy_status = 'stat-unhealthy-critical'; // Red - majority unhealthy
                            $unhealthy_icon = 'dashicons-warning';
                        } else {
                            $unhealthy_status = 'stat-unhealthy-warning'; // Yellow - minority unhealthy
                            $unhealthy_icon = 'dashicons-info';
                        }
                    }
                    ?>
                    <div class="stat-card <?php echo $unhealthy_status; ?> filter-card" data-filter="unhealthy" onclick="filterSites('unhealthy')">
                        <h3>
                            Unhealthy
                            <span class="dashicons <?php echo $unhealthy_icon; ?>"></span>
                        </h3>
                        <div class="stat-value"><?php echo $unhealthy_count; ?></div>
                    </div>
                </div>

                <!-- Sites Table -->
                <div class="sites-table">
                    <?php if (empty($agents)): ?>
                        <div class="empty-state">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <h3>No sites registered yet</h3>
                            <p>Sites will appear here once they are registered with the manager.</p>
                            <p>Install and activate the <strong>WP Remote Agent</strong> plugin on your WordPress sites to get started.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Site</th>
                                    <th>Health</th>
                                    <th>WordPress</th>
                                    <th>PHP</th>
                                    <th>Agent</th>
                                    <th>Scanned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $index => $agent): ?>
                                    <?php
                                    $details_url = add_query_arg(array(
                                        'page' => 'watchtower-manager-site-details',
                                        'site_url' => urlencode($agent['site_url'])
                                    ), admin_url('admin.php'));
                                    ?>
                                    <tr data-site-index="<?php echo $index; ?>" data-details-url="<?php echo esc_url($details_url); ?>" data-health-status="<?php echo $agent['health_status']; ?>" class="clickable-row site-row">
                                        <td>
                                            <div class="site-url">
                                                <a href="<?php echo esc_url($agent['site_url']); ?>" target="_blank">
                                                    <?php if (isset($agent['site_icon']) && $agent['site_icon']): ?>
                                                        <img src="<?php echo esc_url($agent['site_icon']); ?>" alt="" class="site-icon">
                                                    <?php else: ?>
                                                        <div class="site-icon-placeholder">
                                                            <span class="dashicons dashicons-admin-site"></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <strong><?php echo esc_html($agent['site_name'] ?? $agent['site_url']); ?></strong>
                                                </a>
                                            </div>
                                            <div style="font-size: 12px; color: #646970; margin-top: 4px; margin-left: 34px;">
                                                <?php
                                                if (isset($agent['site_name'])) {
                                                    echo esc_html($agent['site_url']) . ' • ';
                                                }
                                                echo esc_html($agent['username']);
                                                ?>
                                                <button type="button" class="button-link copy-credentials-btn"
                                                        data-site-url="<?php echo esc_attr($agent['site_url']); ?>"
                                                        data-username="<?php echo esc_attr($agent['username']); ?>"
                                                        data-password="<?php echo esc_attr($agent['password']); ?>"
                                                        style="margin-left: 4px; cursor: pointer; vertical-align: middle; text-decoration: none; outline: none; border: none; background: none; padding: 0;"
                                                        title="Copy credentials to clipboard">
                                                    <span class="dashicons dashicons-admin-page" style="font-size: 14px; width: 14px; height: 14px; color: #787c82;"></span>
                                                </button>
                                            </div>
                                        </td>
                                        <td data-label="Health">
                                            <?php
                                            $health_status = $agent['health_status'];

                                            if ($health_status === 'healthy'):
                                            ?>
                                                <span class="health-badge health-badge-healthy">
                                                    <span class="dashicons dashicons-yes-alt"></span> Healthy
                                                </span>
                                            <?php elseif ($health_status === 'warning'): ?>
                                                <span class="health-badge health-badge-warning">
                                                    <span class="dashicons dashicons-warning"></span> Warning
                                                </span>
                                            <?php elseif ($health_status === 'critical'): ?>
                                                <span class="health-badge health-badge-critical">
                                                    <span class="dashicons dashicons-dismiss"></span> Critical
                                                </span>
                                            <?php else: ?>
                                                <span class="health-badge health-badge-unknown">
                                                    <span class="dashicons dashicons-minus"></span> Unknown
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="WordPress">
                                            <span class="badge badge-success">
                                                <?php echo esc_html($agent['wordpress_version']); ?>
                                            </span>
                                        </td>
                                        <td data-label="PHP"><?php echo esc_html($agent['php_version']); ?></td>
                                        <td data-label="Agent"><?php echo esc_html($agent['agent_version']); ?></td>
                                        <td data-label="Scanned">
                                            <?php
                                            $health_age = $this->health_storage->get_health_data_age($agent['site_url']);
                                            if ($health_age !== null) {
                                                echo $health_age < 60 ? 'just now' : human_time_diff(current_time('timestamp') - $health_age, current_time('timestamp')) . ' ago';
                                            } else {
                                                echo 'Never';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="actions">
                                                <a href="<?php echo esc_url(add_query_arg(array(
                                                    'page' => 'watchtower-manager-site-details',
                                                    'site_url' => urlencode($agent['site_url'])
                                                ), admin_url('admin.php'))); ?>"
                                                   class="button button-small button-primary">
                                                    Details
                                                </a>
                                                <a href="<?php echo esc_url($agent['admin_url'] ?? ($agent['site_url'] . '/wp-admin')); ?>"
                                                   class="button button-small"
                                                   target="_blank">
                                                    WordPress
                                                </a>
                                                <button class="button button-small scan-site"
                                                        data-site-url="<?php echo esc_attr($agent['site_url']); ?>">
                                                    Scan
                                                </button>
                                                <button class="button button-small button-link-delete remove-site"
                                                        data-site-url="<?php echo esc_attr($agent['site_url']); ?>">
                                                    Remove
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Mobile Sites Grid -->
                <div class="mobile-sites-grid">
                    <?php if (empty($agents)): ?>
                        <div class="empty-state">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <h3>No sites registered yet</h3>
                            <p>Sites will appear here once they are registered with the manager.</p>
                            <p>Install and activate the <strong>WP Remote Agent</strong> plugin on your WordPress sites to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($agents as $index => $agent): ?>
                            <?php
                            $details_url = add_query_arg(array(
                                'page' => 'watchtower-manager-site-details',
                                'site_url' => urlencode($agent['site_url'])
                            ), admin_url('admin.php'));
                            $health_status = $agent['health_status'];
                            $health_age = $this->health_storage->get_health_data_age($agent['site_url']);
                            ?>
                            <div class="mobile-site-tile site-row" data-details-url="<?php echo esc_url($details_url); ?>" data-health-status="<?php echo $health_status; ?>" onclick="window.location.href='<?php echo esc_url($details_url); ?>'">
                                <div class="mobile-site-header">
                                    <div class="mobile-site-title">
                                        <a href="<?php echo esc_url($agent['site_url']); ?>" target="_blank" onclick="event.stopPropagation();">
                                            <?php if (isset($agent['site_icon']) && $agent['site_icon']): ?>
                                                <img src="<?php echo esc_url($agent['site_icon']); ?>" alt="" class="site-icon">
                                            <?php else: ?>
                                                <div class="site-icon-placeholder">
                                                    <span class="dashicons dashicons-admin-site"></span>
                                                </div>
                                            <?php endif; ?>
                                            <strong><?php echo esc_html($agent['site_name'] ?? $agent['site_url']); ?></strong>
                                        </a>
                                    </div>
                                    <div class="mobile-site-health">
                                        <?php if ($health_status === 'healthy'): ?>
                                            <span class="health-badge health-badge-healthy">
                                                <span class="dashicons dashicons-yes-alt"></span> Healthy
                                            </span>
                                        <?php elseif ($health_status === 'warning'): ?>
                                            <span class="health-badge health-badge-warning">
                                                <span class="dashicons dashicons-warning"></span> Warning
                                            </span>
                                        <?php elseif ($health_status === 'critical'): ?>
                                            <span class="health-badge health-badge-critical">
                                                <span class="dashicons dashicons-dismiss"></span> Critical
                                            </span>
                                        <?php else: ?>
                                            <span class="health-badge health-badge-unknown">
                                                <span class="dashicons dashicons-minus"></span> Unknown
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mobile-site-info">
                                    <div class="mobile-site-meta">
                                        <?php
                                        if (isset($agent['site_name'])) {
                                            echo esc_html($agent['site_url']) . ' • ';
                                        }
                                        echo esc_html($agent['username']);
                                        ?>
                                    </div>
                                    <div class="mobile-site-versions">
                                        WP <?php echo esc_html($agent['wordpress_version']); ?> •
                                        PHP <?php echo esc_html($agent['php_version']); ?> •
                                        Agent <?php echo esc_html($agent['agent_version']); ?>
                                    </div>
                                    <div class="mobile-site-scanned">
                                        Scanned: <?php
                                        if ($health_age !== null) {
                                            echo $health_age < 60 ? 'just now' : human_time_diff(current_time('timestamp') - $health_age, current_time('timestamp')) . ' ago';
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="mobile-site-actions">
                                    <a href="<?php echo esc_url($agent['admin_url'] ?? ($agent['site_url'] . '/wp-admin')); ?>"
                                       class="button button-small"
                                       target="_blank"
                                       onclick="event.stopPropagation();">
                                        WordPress
                                    </a>
                                    <button class="button button-small scan-site"
                                            data-site-url="<?php echo esc_attr($agent['site_url']); ?>"
                                            onclick="event.stopPropagation();">
                                        Scan
                                    </button>
                                    <button class="button button-small button-link-delete remove-site"
                                            data-site-url="<?php echo esc_attr($agent['site_url']); ?>"
                                            onclick="event.stopPropagation();">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        if (isset($_POST['watchtower_settings_submit'])) {
            check_admin_referer('watchtower_settings');

            $auto_update = isset($_POST['watchtower_auto_update_agents']) ? 1 : 0;
            update_option('watchtower_auto_update_agents', $auto_update);

            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        }

        $auto_update_enabled = get_option('watchtower_auto_update_agents', false);
        ?>
        <div class="wrap">
            <h1>Watchtower Manager Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field('watchtower_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Auto-Update Agents</th>
                        <td>
                            <label>
                                <input type="checkbox" name="watchtower_auto_update_agents" value="1" <?php checked($auto_update_enabled, 1); ?>>
                                Automatically update agent plugins when a new version is available
                            </label>
                            <p class="description">
                                When enabled, the manager will automatically update agent plugins during automatic health checks.
                                Manual scans always update agents to the latest version.
                                The bundled agent version is <?php
                                $auto_updater = new Watchtower_Manager_Auto_Updater();
                                echo esc_html($auto_updater->get_bundled_agent_version() ?? 'N/A');
                                ?>.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings', 'primary', 'watchtower_settings_submit'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX: Remove agent
     */
    public function ajax_remove_agent() {
        check_ajax_referer('watchtower_manager_remove', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $result = $this->storage->remove_agent($site_url);

        if ($result) {
            wp_send_json_success(array('message' => 'Agent removed successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to remove agent'));
        }
    }

    /**
     * AJAX: Update agent
     */
    public function ajax_update_agent() {
        check_ajax_referer('watchtower_manager_update_agent', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $result = $this->health_storage->check_and_update_agent_version($agent, true);

        if (!isset($result['checked']) || !$result['checked']) {
            $error = isset($result['error']) ? $result['error'] : 'Failed to check agent version';
            wp_send_json_error(array('message' => $error));
            return;
        }

        if (!isset($result['needs_update']) || !$result['needs_update']) {
            wp_send_json_error(array('message' => 'Agent is already up to date'));
            return;
        }

        if (!isset($result['auto_updated']) || !$result['auto_updated']) {
            $message = isset($result['message']) ? $result['message'] : 'Update failed';
            if (isset($result['error'])) {
                $message .= ': ' . $result['error'];
            }
            wp_send_json_error(array('message' => $message));
            return;
        }

        $scan_result = $this->health_storage->fetch_and_save_health($agent);

        wp_send_json_success(array(
            'message' => 'Agent updated successfully',
            'agent_version' => $result['agent_version'],
            'bundled_version' => $result['bundled_version'],
            'rescanned' => $scan_result !== false
        ));
    }

    /**
     * AJAX: Scan agent (fetch and save /info and /health data)
     */
    public function ajax_scan_agent() {
        check_ajax_referer('watchtower_manager_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $result = $this->health_storage->fetch_and_save_health($agent);

        if (!$result) {
            wp_send_json_error(array('message' => 'Failed to scan site'));
            return;
        }

        wp_send_json_success(array(
            'message' => 'Site scanned successfully'
        ));
    }

    /**
     * AJAX: Get available logs for an agent
     */
    public function ajax_get_available_logs() {
        check_ajax_referer('watchtower_manager_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $logs_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/logs');

        $response = wp_remote_get($logs_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'timeout' => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get logs for an agent
     */
    public function ajax_get_logs() {
        check_ajax_referer('watchtower_manager_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $log_type = sanitize_text_field($_POST['log_type']);
        $lines = $_POST['lines'] === 'all' ? 'all' : intval($_POST['lines']);

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $logs_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/logs/' . $log_type) . '&lines=' . $lines;

        $response = wp_remote_get(
            $logs_url,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                ),
                'timeout' => 30,
                'sslverify' => false,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        wp_send_json_success($data);
    }

    /**
     * Handle toggle debug AJAX request
     */
    public function ajax_toggle_debug() {
        check_ajax_referer('watchtower_manager_toggle_debug', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $debug_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/debug');

        $response = wp_remote_post(
            $debug_url,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array('enabled' => $enabled)),
                'timeout' => 30,
                'sslverify' => false,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => isset($data['error']) ? $data['error'] : 'Unknown error'));
        }
    }

    /**
     * AJAX: Get backups for an agent
     */
    public function ajax_get_backups() {
        check_ajax_referer('watchtower_manager_backups', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $backups_data = $this->health_storage->get_backups_data($site_url);

        if (!$backups_data || !isset($backups_data['fetched_at'])) {
            $backups_data = $this->fetch_backups_from_agent($site_url, $agent);
        } else {
            $fetched_time = strtotime($backups_data['fetched_at']);
            $current_time = current_time('timestamp');
            $age_seconds = $current_time - $fetched_time;

            if ($age_seconds > 300) { // 5 minutes
                $backups_data = $this->fetch_backups_from_agent($site_url, $agent);
            }
        }

        wp_send_json_success($backups_data);
    }

    /**
     * Fetch backups data from agent
     */
    private function fetch_backups_from_agent($site_url, $agent) {
        $backups_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups');

        $response = wp_remote_get($backups_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'timeout' => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message(),
                'fetched_at' => current_time('mysql'),
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return array(
                'success' => false,
                'error' => 'Invalid response from agent',
                'fetched_at' => current_time('mysql'),
            );
        }

        $data['fetched_at'] = current_time('mysql');
        return $data;
    }

    /**
     * AJAX: Create backup
     */
    public function ajax_create_backup() {
        check_ajax_referer('watchtower_manager_backups', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $backup_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backup');

        $response = wp_remote_post($backup_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('type' => 'full')),
            'timeout' => 30,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => isset($data['error']) ? $data['error'] : 'Unknown error'));
        }
    }

    /**
     * AJAX: Restore backup
     */
    public function ajax_restore_backup() {
        check_ajax_referer('watchtower_manager_backups', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $backup_id = intval($_POST['backup_id']);

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $restore_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/restore');

        $response = wp_remote_post($restore_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('id' => $backup_id)),
            'timeout' => 300, // 5 minutes for restore to complete
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            error_log('Watchtower Manager: Restore request failed: ' . $response->get_error_message());
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('Watchtower Manager: Restore response status: ' . $status_code);
        error_log('Watchtower Manager: Restore response body: ' . substr($body, 0, 500));

        $data = json_decode($body, true);

        if (!$data) {
            wp_send_json_error(array('message' => 'Invalid JSON response from agent. Status: ' . $status_code));
            return;
        }

        if (isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            $error_msg = isset($data['error']) ? $data['error'] : 'Unknown error';
            if (isset($data['message'])) {
                $error_msg = $data['message'];
            }
            wp_send_json_error(array('message' => $error_msg));
        }
    }

    /**
     * AJAX: Get restore status
     */
    public function ajax_get_restore_status() {
        check_ajax_referer('watchtower_manager_backups', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $status_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/restore/status');

        $response = wp_remote_get($status_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'timeout' => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => isset($data['error']) ? $data['error'] : 'Unknown error'));
        }
    }

    /**
     * AJAX: Delete backup
     */
    public function ajax_delete_backup() {
        check_ajax_referer('watchtower_manager_backups', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site_url']);
        $backup_id = intval($_POST['backup_id']);

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $delete_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups/' . $backup_id);

        $response = wp_remote_request($delete_url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'timeout' => 30,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data && isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => isset($data['error']) ? $data['error'] : 'Unknown error'));
        }
    }

    /**
     * AJAX handler to get agent information
     */
    public function ajax_get_agent() {
        check_ajax_referer('watchtower_manager_get_agent', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site_url']) ? sanitize_text_field($_POST['site_url']) : '';

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Site URL required'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        wp_send_json_success($agent);
    }

    /**
     * AJAX handler to get activity logs from agent
     */
    public function ajax_get_activity_logs() {
        check_ajax_referer('watchtower_manager_get_activity_logs', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site_url']) ? sanitize_text_field($_POST['site_url']) : '';
        $from = isset($_POST['from']) ? intval($_POST['from']) : null;
        $to = isset($_POST['to']) ? intval($_POST['to']) : null;
        $lines = isset($_POST['lines']) ? intval($_POST['lines']) : null;

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Site URL required'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $translated_url = watchtower_manager_translate_agent_url($agent['site_url'], '/watchtower-agent/v1/audit');

        $query_params = array();
        if ($lines !== null) {
            $query_params['lines'] = $lines;
        }
        if ($from !== null) {
            $query_params['from'] = $from;
        }
        if ($to !== null) {
            $query_params['to'] = $to;
        }

        $url = add_query_arg($query_params, $translated_url);

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password'])
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            wp_send_json_error(array('message' => 'Invalid response from agent'));
            return;
        }

        wp_send_json_success($data);
    }

    /**
     * Determine overall health status
     */
    private function determine_health_status($health_data) {
        if (!$health_data || !isset($health_data['success']) || !$health_data['success']) {
            return 'critical';
        }

        $warnings = 0;
        $critical = 0;

        if (isset($health_data['memory']['usage_percentage'])) {
            $usage = floatval(str_replace('%', '', $health_data['memory']['usage_percentage']));
            if ($usage >= 90) $critical++;
            elseif ($usage >= 75) $warnings++;
        }

        if (isset($health_data['disk']['usage_percentage'])) {
            $usage = floatval(str_replace('%', '', $health_data['disk']['usage_percentage']));
            if ($usage >= 90) $critical++;
            elseif ($usage >= 80) $warnings++;
        }

        if ($critical > 0) return 'critical';
        if ($warnings > 0) return 'warning';
        return 'healthy';
    }

    /**
     * Get status for a specific metric
     */
    private function get_metric_status($value, $thresholds) {
        if ($value >= $thresholds['critical']) return 'critical';
        if ($value >= $thresholds['warning']) return 'warning';
        return 'healthy';
    }

    /**
     * Render site details page
     */
    public function render_site_details() {
    if (!isset($_GET['site_url'])) {
        wp_die('Site URL parameter missing');
    }

    $site_url = urldecode($_GET['site_url']);
    $agent = $this->storage->get_agent_by_url($site_url);

    if (!$agent) {
        wp_die('Agent not found');
    }

    $health_data = $this->health_storage->get_health_data($site_url);
    $health_age = $this->health_storage->get_health_data_age($site_url);

    if ($this->health_storage->is_health_data_stale($site_url)) {
        $this->health_storage->fetch_and_save_health($agent);
        $health_data = $this->health_storage->get_health_data($site_url);
        $health_age = 0;
    }

    $overall_status = $this->determine_health_status($health_data);
    $has_health_data = $health_data && isset($health_data['success']) && $health_data['success'];

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            Site Details
        </h1>
        <a href="<?php echo admin_url('admin.php?page=watchtower-manager'); ?>" class="page-title-action">
            ← Back to All Sites
        </a>
        <hr class="wp-header-end">

        <div class="watchtower-manager-dashboard">
            <!-- Overall Health Status -->
            <div class="health-status-card <?php echo $overall_status; ?>">
                <span class="dashicons <?php
                    echo $overall_status === 'healthy' ? 'dashicons-yes-alt' :
                        ($overall_status === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss');
                ?> health-status-icon <?php echo $overall_status; ?>"></span>
                <div class="health-status-content">
                    <div class="health-status-info">
                        <div class="health-status-title">
                            <?php echo esc_html($agent['site_name'] ?? $site_url); ?>
                            <?php if (isset($agent['site_name'])): ?>
                                <div style="font-size: 14px; font-weight: 400; color: #646970;">
                                    <?php echo esc_html($site_url); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="health-status-subtitle <?php echo $overall_status; ?>">
                            <?php
                            if ($overall_status === 'healthy') echo 'Site is Healthy';
                            elseif ($overall_status === 'warning') echo 'Site Needs Attention';
                            else echo 'Site Has Critical Issues';
                            ?>
                            <?php if ($has_health_data): ?>
                            <button class="health-status-toggle" onclick="toggleHealthDetails(event)" style="margin-left: 10px; background: none; border: none; cursor: pointer; color: inherit; padding: 0; vertical-align: middle;">
                                <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_health_data): ?>
                            <div class="health-status-meta" style="display: none;">
                                <table>
                                    <?php if (isset($health_data['wordpress'])): ?>
                                    <tr>
                                        <td>WordPress:</td>
                                        <td><?php echo esc_html($health_data['wordpress']['version']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($health_data['php'])): ?>
                                    <tr>
                                        <td>PHP:</td>
                                        <td><?php echo esc_html($health_data['php']['version']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($agent['agent_version'])): ?>
                                    <tr>
                                        <td>Agent:</td>
                                        <td><?php echo esc_html($agent['agent_version']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($health_data['theme'])): ?>
                                    <tr>
                                        <td>Theme:</td>
                                        <td><?php echo esc_html($health_data['theme']['name']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($health_data['plugins'])): ?>
                                    <tr>
                                        <td>Plugins:</td>
                                        <td><?php echo count($health_data['plugins']['active_plugins'] ?? []); ?> active</td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (isset($agent['password'])): ?>
                                    <tr>
                                        <td>Token:</td>
                                        <td>
                                            <span id="agent-token-value"><?php echo esc_html($agent['password']); ?></span>
                                            <button type="button" class="button-link copy-token-btn" onclick="copyTokenToClipboard()" style="margin-left: 8px; cursor: pointer; vertical-align: middle; text-decoration: none; outline: none; border: none; background: none; padding: 0;" title="Copy to clipboard">
                                                <span class="dashicons dashicons-admin-page" style="font-size: 16px; width: 16px; height: 16px; color: #787c82;"></span>
                                            </button>
                                            <span id="copy-feedback" style="display: none; color: #00a32a; margin-left: 8px;">Copied!</span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="health-status-timestamp">
                                Last checked: <?php echo $health_age < 60 ? 'just now' : human_time_diff(current_time('timestamp') - $health_age, current_time('timestamp')) . ' ago'; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Mobile Actions -->
                        <div class="health-status-actions-mobile">
                            <a href="<?php echo esc_url($site_url); ?>" target="_blank" class="health-status-btn">
                                <span class="dashicons dashicons-admin-site"></span>
                                Visit Site
                            </a>
                            <a href="<?php echo esc_url($agent['admin_url'] ?? ($site_url . '/wp-admin')); ?>" target="_blank" class="health-status-btn">
                                <span class="dashicons dashicons-admin-generic"></span>
                                WP Admin
                            </a>
                        </div>
                    </div>
                    <div class="health-status-actions">
                        <a href="<?php echo esc_url($site_url); ?>" target="_blank" class="health-status-btn">
                            <span class="dashicons dashicons-admin-site"></span>
                            Visit Site
                        </a>
                        <a href="<?php echo esc_url($agent['admin_url'] ?? ($site_url . '/wp-admin')); ?>" target="_blank" class="health-status-btn">
                            <span class="dashicons dashicons-admin-generic"></span>
                            WP Admin
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Tab Selector -->
            <div class="watchtower-mobile-tab-selector" style="margin-top: 20px; display: none;">
                <select id="mobile-tab-selector" style="width: 100%; height: 40px; font-size: 16px;">
                    <option value="overview" selected>Overview</option>
                    <option value="plugins">Plugins</option>
                    <option value="activity">Activity</option>
                    <option value="logs">Logs</option>
                    <option value="actions">Actions</option>
                </select>
            </div>

            <!-- Tab Navigation (Desktop) -->
            <div class="watchtower-tabs">
                <button class="watchtower-tab-btn active" data-tab="overview">
                    <span class="dashicons dashicons-dashboard"></span> Overview
                </button>
                <button class="watchtower-tab-btn" data-tab="plugins">
                    <span class="dashicons dashicons-admin-plugins"></span> Plugins
                </button>
                <?php /* Backups tab disabled
                <button class="watchtower-tab-btn" data-tab="backups">
                    <span class="dashicons dashicons-database-export"></span> Backups
                </button>
                */ ?>
                <button class="watchtower-tab-btn" data-tab="activity">
                    <span class="dashicons dashicons-clipboard"></span> Activity
                </button>
                <button class="watchtower-tab-btn" data-tab="logs">
                    <span class="dashicons dashicons-media-text"></span> Logs
                </button>
                <button class="watchtower-tab-btn" data-tab="actions">
                    <span class="dashicons dashicons-admin-tools"></span> Actions
                </button>
            </div>

            <!-- Tab Content: Overview -->
            <div class="watchtower-tab-content" id="tab-overview">
            <?php if ($has_health_data): ?>
                <!-- Metrics Grid - CPU, Memory, Disk Only -->
                <div class="metrics-grid">
                    <?php
                    if (isset($health_data['memory'])):
                        $memory_usage = floatval(str_replace('%', '', $health_data['memory']['usage_percentage'] ?? '0'));
                        $memory_status = $this->get_metric_status($memory_usage, ['warning' => 75, 'critical' => 90]);
                    ?>
                    <div class="metric-tile <?php echo $memory_status; ?>">
                        <div class="metric-header">
                            <span class="dashicons dashicons-performance metric-icon <?php echo $memory_status; ?>"></span>
                            <h3 class="metric-title">Memory Usage</h3>
                        </div>
                        <div class="metric-value"><?php echo esc_html($health_data['memory']['usage_percentage'] ?? 'N/A'); ?></div>
                        <div class="metric-subtitle"><?php echo esc_html($health_data['memory']['current']); ?> of <?php echo esc_html($health_data['memory']['limit']); ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $memory_status; ?>" style="width: <?php echo $memory_usage; ?>%"></div>
                        </div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <div><strong>Peak:</strong> <?php echo esc_html($health_data['memory']['peak']); ?></div>
                            <div><strong>WP Limit:</strong> <?php echo esc_html($health_data['memory']['wp_memory_limit']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    if (isset($health_data['disk'])):
                        $disk_usage = floatval(str_replace('%', '', $health_data['disk']['usage_percentage'] ?? '0'));
                        $disk_status = $this->get_metric_status($disk_usage, ['warning' => 80, 'critical' => 90]);
                    ?>
                    <div class="metric-tile <?php echo $disk_status; ?>">
                        <div class="metric-header">
                            <span class="dashicons dashicons-database metric-icon <?php echo $disk_status; ?>"></span>
                            <h3 class="metric-title">Disk Usage</h3>
                        </div>
                        <div class="metric-value"><?php echo esc_html($health_data['disk']['usage_percentage'] ?? 'N/A'); ?></div>
                        <div class="metric-subtitle"><?php echo esc_html($health_data['disk']['used']); ?> of <?php echo esc_html($health_data['disk']['total']); ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $disk_status; ?>" style="width: <?php echo $disk_usage; ?>%"></div>
                        </div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <div><strong>Free:</strong> <?php echo esc_html($health_data['disk']['free']); ?></div>
                            <div><strong>Used:</strong> <?php echo esc_html($health_data['disk']['used']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    if (isset($health_data['cpu']) && !empty($health_data['cpu'])):
                        $cpu_usage = floatval(str_replace('%', '', $health_data['cpu']['usage_percentage'] ?? '0'));
                        $cpu_status = $this->get_metric_status($cpu_usage, ['warning' => 75, 'critical' => 90]);
                    ?>
                    <div class="metric-tile <?php echo $cpu_status; ?>">
                        <div class="metric-header">
                            <span class="dashicons dashicons-performance metric-icon <?php echo $cpu_status; ?>"></span>
                            <h3 class="metric-title">CPU Usage</h3>
                        </div>
                        <div class="metric-value"><?php echo esc_html($health_data['cpu']['usage_percentage'] ?? 'N/A'); ?></div>
                        <div class="metric-subtitle"><?php echo esc_html($health_data['cpu']['cores'] ?? 1); ?> Core<?php echo ($health_data['cpu']['cores'] ?? 1) > 1 ? 's' : ''; ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $cpu_status; ?>" style="width: <?php echo min($cpu_usage, 100); ?>%"></div>
                        </div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <div><strong>Load 1 min:</strong> <?php echo esc_html($health_data['cpu']['load_1min']); ?></div>
                            <div><strong>Load 5 min:</strong> <?php echo esc_html($health_data['cpu']['load_5min']); ?></div>
                            <div><strong>Load 15 min:</strong> <?php echo esc_html($health_data['cpu']['load_15min']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Additional Info Tiles (Database, Theme, Constants) -->
                <div class="metrics-grid" style="margin-top: 20px;">
                    <!-- Database Tile -->
                    <?php if (isset($health_data['database'])): ?>
                    <div class="metric-tile">
                        <div class="metric-header">
                            <span class="dashicons dashicons-database metric-icon"></span>
                            <h3 class="metric-title">Database</h3>
                        </div>
                        <div class="metric-value"><?php echo esc_html($health_data['database']['database_name']); ?></div>
                        <div class="metric-subtitle"><?php echo esc_html($health_data['database']['database_version']); ?></div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <div><strong>Host:</strong> <?php echo esc_html($health_data['database']['database_host']); ?></div>
                            <div><strong>Size:</strong> <?php echo esc_html($health_data['database']['database_size'] ?? 'N/A'); ?></div>
                            <div><strong>Prefix:</strong> <code><?php echo esc_html($health_data['database']['table_prefix']); ?></code></div>
                            <div><strong>Charset:</strong> <?php echo esc_html($health_data['database']['database_charset']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Theme Tile -->
                    <?php if (isset($health_data['theme'])): ?>
                    <div class="metric-tile">
                        <div class="metric-header">
                            <span class="dashicons dashicons-admin-appearance metric-icon"></span>
                            <h3 class="metric-title">Active Theme</h3>
                        </div>
                        <div class="metric-value"><?php echo esc_html($health_data['theme']['name']); ?></div>
                        <div class="metric-subtitle">Version <?php echo esc_html($health_data['theme']['version']); ?></div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <div><strong>Template:</strong> <code><?php echo esc_html($health_data['theme']['template']); ?></code></div>
                            <div><strong>Stylesheet:</strong> <code><?php echo esc_html($health_data['theme']['stylesheet']); ?></code></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- WordPress Constants Tile -->
                    <?php if (isset($health_data['constants'])): ?>
                    <div class="metric-tile">
                        <div class="metric-header">
                            <span class="dashicons dashicons-admin-settings metric-icon"></span>
                            <h3 class="metric-title">WordPress Constants</h3>
                        </div>
                        <div class="metric-details" style="margin-top: 15px;">
                            <?php foreach ($health_data['constants'] as $constant => $value): ?>
                            <div>
                                <strong><?php echo esc_html($constant); ?>:</strong>
                                <?php if (is_bool($value)): ?>
                                    <code><?php echo $value ? 'true' : 'false'; ?></code>
                                <?php else: ?>
                                    <code><?php echo esc_html($value); ?></code>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Health Data Not Available -->
                <div style="background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                    <span class="dashicons dashicons-warning" style="font-size: 64px; width: 64px; height: 64px; color: #d63638; margin-bottom: 15px;"></span>
                    <h2>Health Data Not Available</h2>
                    <p style="color: #646970;">
                        <?php
                        if ($health_data && isset($health_data['error'])) {
                            echo '<strong>Error:</strong> ' . esc_html($health_data['error']);
                        } else {
                            echo 'Health data not available. The agent may be offline or health monitoring may not be configured.';
                        }
                        ?>
                    </p>
                    <p>
                        <button class="button button-primary" onclick="location.reload();">Retry</button>
                    </p>
                </div>
            <?php endif; ?>
            </div>

            <!-- Tab Content: Plugins -->
            <div class="watchtower-tab-content" id="tab-plugins" style="display: none;">
                <?php if ($has_health_data && isset($health_data['plugins']) && !empty($health_data['plugins']['active_plugins'])): ?>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <h2>Active Plugins (<?php echo count($health_data['plugins']['active_plugins']); ?>)</h2>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="font-weight: bold;">Plugin Name</th>
                                <th style="font-weight: bold;">Version</th>
                                <th style="font-weight: bold;">File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sorted_plugins = $health_data['plugins']['active_plugins'];
                            usort($sorted_plugins, function($a, $b) {
                                return strcasecmp($a['name'], $b['name']);
                            });
                            foreach ($sorted_plugins as $plugin):
                            ?>
                                <tr>
                                    <td><?php echo esc_html($plugin['name']); ?></td>
                                    <td><?php echo esc_html($plugin['version']); ?></td>
                                    <td><code><?php echo esc_html($plugin['file']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div style="background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                    <span class="dashicons dashicons-admin-plugins" style="font-size: 64px; width: 64px; height: 64px; color: #646970; margin-bottom: 15px;"></span>
                    <h2>No Plugin Data Available</h2>
                    <p style="color: #646970;">Plugin information is not available. The agent may be offline or health monitoring may not be configured.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php /* Backups tab content disabled
            <!-- Tab Content: Backups -->
            <div class="watchtower-tab-content" id="tab-backups" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Backups</h2>
                        <div style="display: flex; gap: 10px;">
                            <button id="create-backup-btn" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Backup Now
                            </button>
                            <button id="refresh-backups-btn" class="button" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" title="Refresh">
                                <span class="dashicons dashicons-update" style="margin-top: 0;"></span>
                            </button>
                        </div>
                    </div>
                    <div id="backups-loading" style="text-align: center; padding: 40px;">
                        <span class="dashicons dashicons-update" style="font-size: 32px; width: 32px; height: 32px; animation: rotation 2s infinite linear;"></span>
                        <p>Loading backups...</p>
                    </div>
                    <div id="backups-container" style="display: none;">
                        <table class="wp-list-table widefat fixed striped" id="backups-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%; font-weight: bold;">Date</th>
                                    <th style="width: 15%; text-align: center; font-weight: bold;">Size</th>
                                    <th style="width: 35%; text-align: center; font-weight: bold;">Components</th>
                                    <th style="width: 25%; text-align: center; font-weight: bold;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backups-table-body">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="backups-empty" style="display: none; text-align: center; padding: 40px;">
                        <span class="dashicons dashicons-database-export" style="font-size: 64px; width: 64px; height: 64px; color: #646970; margin-bottom: 15px;"></span>
                        <h3>No Backups Found</h3>
                        <p style="color: #646970;">There are no backups for this site yet. Create your first backup to get started.</p>
                        <button class="button button-primary" onclick="document.getElementById('create-backup-btn').click();">
                            <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Backup Now
                        </button>
                    </div>
                    <div id="backups-error" style="display: none; background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                        <span class="dashicons dashicons-warning" style="font-size: 64px; width: 64px; height: 64px; color: #d63638; margin-bottom: 15px;"></span>
                        <h3>Unable to Load Backups</h3>
                        <p style="color: #646970;" id="backups-error-message">An error occurred while loading backups.</p>
                        <button class="button button-primary" onclick="document.getElementById('refresh-backups-btn').click();">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Retry
                        </button>
                    </div>
                </div>
            </div>
            */ ?>

            <!-- Restore Progress Modal -->
            <div id="restore-progress-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <h2 style="margin-top: 0; margin-bottom: 20px;">Restoring Backup</h2>
                    <p id="restore-progress-message" style="margin-bottom: 20px; color: #646970;">Initializing restore...</p>

                    <div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden; margin-bottom: 15px;">
                        <div id="restore-progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">
                            <span id="restore-progress-percent">0%</span>
                        </div>
                    </div>

                    <div style="text-align: center;">
                        <button id="restore-progress-close" class="button button-secondary" style="display: none;">Close</button>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Activity -->
            <div class="watchtower-tab-content" id="tab-activity" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <h2 style="margin: 0;">Activity Log</h2>
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center;">
                                <label for="activity-date-picker" style="margin-right: 8px; margin-bottom: 0;"><strong>Date:</strong></label>
                                <input type="text" id="activity-date-picker" class="regular-text" style="width: 150px;" readonly placeholder="Select date...">
                            </div>
                            <div style="position: relative; display: flex; align-items: center;">
                                <label for="activity-action-filter" style="margin-right: 8px; margin-bottom: 0;"><strong>Action:</strong></label>
                                <button type="button" id="activity-action-filter" class="button" style="min-width: 120px; text-align: left; border-color: #8c8f94; color: #2c3338; font-size: 14px;">
                                    All Actions <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>
                                </button>
                                <div id="activity-action-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 200px; max-height: 300px; overflow-y: auto; margin-top: 4px;">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                            <div style="position: relative; display: flex; align-items: center;">
                                <label for="activity-actor-filter" style="margin-right: 8px; margin-bottom: 0;"><strong>Actor:</strong></label>
                                <button type="button" id="activity-actor-filter" class="button" style="min-width: 120px; text-align: left; border-color: #8c8f94; color: #2c3338; font-size: 14px;">
                                    All Actors <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>
                                </button>
                                <div id="activity-actor-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 200px; max-height: 300px; overflow-y: auto; margin-top: 4px;">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                            <button type="button" id="activity-refresh-btn" class="button" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" title="Refresh">
                                <span class="dashicons dashicons-update" style="margin-top: 0;"></span>
                            </button>
                        </div>
                    </div>

                    <div id="activity-log-viewer" style="background: #fff; border-radius: 4px; border: 1px solid #ddd; min-height: 400px; max-height: 600px; overflow-y: auto; position: relative;">
                        <div style="text-align: center; padding: 50px 0; color: #888;">
                            <span class="dashicons dashicons-calendar" style="font-size: 48px; width: 48px; height: 48px;"></span>
                            <p style="margin-top: 15px;">Select a date to view activity logs</p>
                        </div>
                    </div>

                    <div style="margin-top: 20px;">
                        <div id="activity-stats" style="color: #666; font-size: 13px;">
                            Showing <span id="activity-count-visible">0</span> of <span id="activity-count-total">0</span> entries
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Logs -->
            <div class="watchtower-tab-content" id="tab-logs" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">Log Viewer</h2>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div>
                                <label for="log-type-selector" style="margin-right: 8px;"><strong>Type:</strong></label>
                                <select id="log-type-selector" class="regular-text" style="width: auto;">
                                    <option value="">Loading...</option>
                                </select>
                            </div>
                            <div>
                                <label for="log-lines-count" style="margin-right: 8px;"><strong>Lines:</strong></label>
                                <select id="log-lines-count" class="regular-text" style="width: auto;">
                                    <option value="100" selected>100</option>
                                    <option value="250">250</option>
                                    <option value="500">500</option>
                                    <option value="1000">1000</option>
                                    <option value="all">All</option>
                                </select>
                            </div>
                            <button id="refresh-logs-btn" class="button" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 10px;" title="Refresh">
                                <span class="dashicons dashicons-update" style="margin-top: 0;"></span>
                            </button>
                        </div>
                    </div>
                    <div id="log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
                        <span style="color: #888;">Select a log type to view logs...</span>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Actions -->
            <div class="watchtower-tab-content" id="tab-actions" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <h2>Actions</h2>
                    <p>
                        <button class="button button-primary watchtower-update-agent-btn" data-site-url="<?php echo esc_attr($site_url); ?>">
                            <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Agent
                        </button>
                        <button class="button button-secondary watchtower-scan-btn" data-site-url="<?php echo esc_attr($site_url); ?>">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Scan
                        </button>
                        <?php
                        $debug_enabled = false;
                        if (isset($agent['constants']) && isset($agent['constants']['WP_DEBUG_LOG'])) {
                            $debug_enabled = $agent['constants']['WP_DEBUG_LOG'];
                        }
                        ?>
                        <button class="button watchtower-toggle-debug-btn" data-site-url="<?php echo esc_attr($site_url); ?>" data-debug-enabled="<?php echo $debug_enabled ? '1' : '0'; ?>">
                            <span class="dashicons dashicons-<?php echo $debug_enabled ? 'no' : 'yes'; ?>" style="margin-top: 3px;"></span> <?php echo $debug_enabled ? 'Disable' : 'Enable'; ?> Debug
                        </button>
                        <a href="<?php echo esc_url($site_url . '/wp-json/watchtower-agent/v1/info'); ?>" class="button" target="_blank">
                            View Agent Info (JSON)
                        </a>
                        <a href="<?php echo esc_url($site_url . '/wp-json/watchtower-agent/v1/health'); ?>" class="button" target="_blank">
                            View Health Data (JSON)
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php
}
}
