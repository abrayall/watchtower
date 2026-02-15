<?php
/**
 * Admin Dashboard Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Manager_Admin_Dashboard {

    /**
     * Storage instance
     */
    private $storage;

    /**
     * Constructor
     */
    public function __construct() {
        $this->storage = new Watchtower_Manager_Storage();
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
        add_action('wp_ajax_watchtower_manager_get_backup_status', array($this, 'ajax_get_backup_status'));
        add_action('wp_ajax_watchtower_manager_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_watchtower_manager_get_restore_status', array($this, 'ajax_get_restore_status'));
        add_action('wp_ajax_watchtower_manager_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_watchtower_manager_get_agent', array($this, 'ajax_get_agent'));
        add_action('wp_ajax_watchtower_manager_get_activity_logs', array($this, 'ajax_get_activity_logs'));
        add_action('wp_ajax_watchtower_manager_get_users', array($this, 'ajax_get_users'));
        add_action('wp_ajax_watchtower_manager_create_user', array($this, 'ajax_create_user'));
        add_action('wp_ajax_watchtower_manager_update_user', array($this, 'ajax_update_user'));
        add_action('wp_ajax_watchtower_manager_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_watchtower_manager_reset_password', array($this, 'ajax_reset_password'));
        add_action('wp_ajax_watchtower_manager_list_files', array($this, 'ajax_list_files'));
        add_action('wp_ajax_watchtower_manager_create_file', array($this, 'ajax_create_file'));
        add_action('wp_ajax_watchtower_manager_get_file_content', array($this, 'ajax_get_file_content'));
        add_action('wp_ajax_watchtower_manager_save_file', array($this, 'ajax_save_file'));
        add_action('wp_ajax_watchtower_manager_rename_file', array($this, 'ajax_rename_file'));
        add_action('wp_ajax_watchtower_manager_delete_file', array($this, 'ajax_delete_file'));
        add_action('wp_ajax_watchtower_manager_toggle_maintenance', array($this, 'ajax_toggle_maintenance'));
        add_action('wp_ajax_watchtower_manager_get_tags', array($this, 'ajax_get_tags'));
        add_action('wp_ajax_watchtower_manager_save_tags', array($this, 'ajax_save_tags'));
        add_action('wp_ajax_watchtower_manager_get_all_tags', array($this, 'ajax_get_all_tags'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Remote Sites',           // Page title
            'Watchtower',             // Menu title
            'manage_options',         // Capability
            'watchtower-manager',      // Menu slug
            array($this, 'render_dashboard'), // Callback
            'dashicons-visibility', // Icon
            30                        // Position
        );

        add_submenu_page(
            'watchtower-manager',
            'Sites',
            'Sites',
            'manage_options',
            'watchtower-manager',
            array($this, 'render_dashboard')
        );

        add_submenu_page(
            'watchtower-manager',
            'Plugins',
            'Plugins',
            'manage_options',
            'watchtower-manager-plugins',
            array($this, 'render_plugins_page')
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
                WATCHTOWER_MANAGER_VERSION . '-' . time(),
                true
            );

            wp_localize_script('watchtower-details', 'context', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('watchtower_manager_nonce'),
                'siteUrl' => urldecode($_GET['site'])
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

            wp_localize_script('watchtower-dashboard', 'context', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('watchtower_manager_nonce'),
                'searchTerms' => $this->get_search_terms()
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
        $all_search_terms = array();

        foreach ($agents as $agent) {
            $health_data = $this->storage->get_health_data($agent['site']);
            $health_status = $this->determine_health_status($health_data);

            $agent['health_status'] = $health_status;

            $search_parts = array();
            $search_parts[] = isset($agent['name']) ? $agent['name'] : '';
            $search_parts[] = $agent['site'];
            $search_parts[] = isset($agent['username']) ? $agent['username'] : '';
            $search_parts[] = isset($agent['wordpress_version']) ? $agent['wordpress_version'] : '';
            $search_parts[] = isset($agent['php_version']) ? $agent['php_version'] : '';
            $search_parts[] = isset($agent['agent_version']) ? $agent['agent_version'] : '';
            $search_parts[] = $health_status;

            $search_tags = array();
            $search_plugins = array();
            $search_users = array();
            $search_theme = '';
            $search_settings = array();

            if (isset($agent['theme']['name'])) {
                $search_parts[] = $agent['theme']['name'];
                $search_theme = $agent['theme']['name'];
                $all_search_terms[] = $agent['theme']['name'];
            }

            $tags = $this->storage->get_tags_data($agent['site']);
            if (is_array($tags)) {
                $search_parts = array_merge($search_parts, $tags);
                $search_tags = $tags;
                $all_search_terms = array_merge($all_search_terms, $tags);
            }

            $plugins_data = $this->storage->get_plugins_data($agent['site']);
            if ($plugins_data && isset($plugins_data['plugins']) && is_array($plugins_data['plugins'])) {
                foreach ($plugins_data['plugins'] as $plugin) {
                    if (isset($plugin['name'])) {
                        $search_parts[] = $plugin['name'];
                        $search_plugins[] = $plugin['name'];
                        $all_search_terms[] = $plugin['name'];
                    }
                }
            }

            $users_data = $this->storage->get_users_data($agent['site']);
            if ($users_data && isset($users_data['users']) && is_array($users_data['users'])) {
                foreach ($users_data['users'] as $user) {
                    if (isset($user['username'])) {
                        $search_parts[] = $user['username'];
                        $search_users[] = $user['username'];
                        $all_search_terms[] = $user['username'];
                    }
                }
            }

            if (isset($agent['constants']) && is_array($agent['constants'])) {
                foreach ($agent['constants'] as $key => $value) {
                    $search_parts[] = $key;
                    $search_settings[] = $key;
                }
            }

            if (isset($agent['name'])) {
                $all_search_terms[] = $agent['name'];
            }
            $all_search_terms[] = $agent['site'];

            $agent['_search_text'] = strtolower(implode(' ', array_filter($search_parts)));
            $agent['_tags'] = $search_tags;
            $agent['_search_tags'] = strtolower(implode('|', $search_tags));
            $agent['_search_plugins'] = strtolower(implode('|', $search_plugins));
            $agent['_search_users'] = strtolower(implode('|', $search_users));
            $agent['_search_theme'] = strtolower($search_theme);
            $agent['_search_settings'] = strtolower(implode('|', $search_settings));

            $health_age = $this->storage->get_health_data_age($agent['site']);
            $agent['_sort_scanned'] = $health_age !== null ? $health_age : 999999999;
            $agents_with_health[] = $agent;

            if ($health_status === 'healthy') {
                $healthy_count++;
            } elseif ($health_status === 'warning' || $health_status === 'critical') {
                $unhealthy_count++;
            }
        }

        $all_search_terms = array_values(array_unique(array_filter($all_search_terms)));
        sort($all_search_terms);

        usort($agents_with_health, function($a, $b) {
            $a_priority = ($a['health_status'] === 'warning' || $a['health_status'] === 'critical') ? 0 : 1;
            $b_priority = ($b['health_status'] === 'warning' || $b['health_status'] === 'critical') ? 0 : 1;

            if ($a_priority !== $b_priority) {
                return $a_priority - $b_priority;
            }

            $a_name = isset($a['name']) ? $a['name'] : $a['site'];
            $b_name = isset($b['name']) ? $b['name'] : $b['site'];

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

            <div style="position: relative; min-height: 400px;">
                <div id="watchtower-dashboard-loading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fff; z-index: 100; display: flex; align-items: center; justify-content: center; min-height: 400px;">
                    <div class="watchtower-spinner"></div>
                </div>

                <div id="watchtower-dashboard-content" style="opacity: 0; transition: opacity 0.3s ease;">
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

                <div class="site-search-wrapper">
                    <span class="dashicons dashicons-search site-search-icon"></span>
                    <input type="text" id="site-search" placeholder="Search sites..." autocomplete="off">
                    <span class="dashicons dashicons-no-alt site-search-clear" id="site-search-clear"></span>
                    <div id="search-autocomplete" class="search-autocomplete-dropdown" style="display: none;"></div>
                </div>

                <!-- Sites Table -->
                <div class="sites-table">
                    <?php if (empty($agents)): ?>
                        <div class="empty-state">
                            <span class="dashicons dashicons-admin-site-alt3"></span>
                            <h3>No sites registered yet</h3>
                            <p>Sites will appear here once they are registered with the manager.</p>
                            <p>Install and activate the <strong>Watchtower Agent</strong> plugin on your WordPress sites to get started.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th><a href="#" class="sort-header" data-sort="name">Site</a></th>
                                    <th><a href="#" class="sort-header" data-sort="health">Health</a></th>
                                    <th><a href="#" class="sort-header" data-sort="wordpress">WordPress</a></th>
                                    <th><a href="#" class="sort-header" data-sort="php">PHP</a></th>
                                    <th><a href="#" class="sort-header" data-sort="agent">Agent</a></th>
                                    <th><a href="#" class="sort-header" data-sort="scanned">Scanned</a></th>
                                    <th>Tags</th>
                                    <th style="text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agents as $index => $agent): ?>
                                    <?php
                                    $details_url = add_query_arg(array(
                                        'page' => 'watchtower-manager-site-details',
                                        'site' => urlencode($agent['site'])
                                    ), admin_url('admin.php'));
                                    ?>
                                    <tr data-site-index="<?php echo $index; ?>" data-details-url="<?php echo esc_url($details_url); ?>" data-health-status="<?php echo $agent['health_status']; ?>" data-search-text="<?php echo esc_attr($agent['_search_text']); ?>" data-site-name="<?php echo esc_attr(strtolower($agent['name'] ?? $agent['site'])); ?>" data-site-url="<?php echo esc_attr(strtolower($agent['site'])); ?>" data-search-tags="<?php echo esc_attr($agent['_search_tags']); ?>" data-search-plugins="<?php echo esc_attr($agent['_search_plugins']); ?>" data-search-users="<?php echo esc_attr($agent['_search_users']); ?>" data-search-theme="<?php echo esc_attr($agent['_search_theme']); ?>" data-search-settings="<?php echo esc_attr($agent['_search_settings']); ?>" data-sort-name="<?php echo esc_attr(strtolower($agent['name'] ?? $agent['site'])); ?>" data-sort-health="<?php echo esc_attr($agent['health_status']); ?>" data-sort-wordpress="<?php echo esc_attr($agent['wordpress_version'] ?? ''); ?>" data-sort-php="<?php echo esc_attr($agent['php_version'] ?? ''); ?>" data-sort-agent="<?php echo esc_attr($agent['agent_version'] ?? ''); ?>" data-sort-scanned="<?php echo esc_attr($agent['_sort_scanned']); ?>" class="clickable-row site-row">
                                        <td>
                                            <div class="site-url">
                                                <a href="<?php echo esc_url($agent['site']); ?>" target="_blank">
                                                    <?php if (isset($agent['icon']) && $agent['icon']): ?>
                                                        <img src="<?php echo esc_url($agent['icon']); ?>" alt="" class="site-icon">
                                                    <?php else: ?>
                                                        <div class="site-icon-placeholder">
                                                            <span class="dashicons dashicons-admin-site"></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <strong><?php echo esc_html($agent['name'] ?? $agent['site']); ?></strong>
                                                </a>
                                            </div>
                                            <div style="font-size: 12px; color: #646970; margin-top: 0; margin-left: 34px;">
                                                <?php
                                                if (isset($agent['name'])) {
                                                    echo esc_html($agent['site']) . ' • ';
                                                }
                                                echo esc_html($agent['username']);
                                                ?>
                                                <button type="button" class="button-link copy-credentials-btn"
                                                        data-site="<?php echo esc_attr($agent['site']); ?>"
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
                                            $is_maintenance = isset($agent['mode']) && $agent['mode'] === 'maintenance';

                                            if ($is_maintenance):
                                            ?>
                                                <span class="health-badge health-badge-maintenance">
                                                    <span class="dashicons dashicons-admin-tools"></span> Maintenance
                                                </span>
                                            <?php elseif ($health_status === 'healthy'): ?>
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
                                            $health_age = $this->storage->get_health_data_age($agent['site']);
                                            if ($health_age !== null) {
                                                echo $health_age < 60 ? 'just now' : human_time_diff(current_time('timestamp') - $health_age, current_time('timestamp')) . ' ago';
                                            } else {
                                                echo 'Never';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Tags">
                                            <?php
                                            $tags = $agent['_tags'];
                                            $show = 2;
                                            $tags_url = esc_url(add_query_arg(array(
                                                'page' => 'watchtower-manager-site-details',
                                                'site' => urlencode($agent['site'])
                                            ), admin_url('admin.php'))) . '#tags';
                                            if (!empty($tags)) {
                                                $visible = array_slice($tags, 0, $show);
                                                $remaining = count($tags) - $show;
                                                foreach ($visible as $tag) {
                                                    echo '<span class="tag-pill tag-pill-filter" data-tag="' . esc_attr($tag) . '">' . esc_html($tag) . '</span>';
                                                }
                                                if ($remaining > 0) {
                                                    echo '<a href="' . $tags_url . '" class="tag-pill tag-pill-more">+' . $remaining . '</a>';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Actions" style="text-align: right;">
                                            <div class="actions">
                                                <a href="<?php echo esc_url(add_query_arg(array(
                                                    'page' => 'watchtower-manager-site-details',
                                                    'site' => urlencode($agent['site'])
                                                ), admin_url('admin.php'))); ?>"
                                                   class="button button-small button-primary">
                                                    Details
                                                </a>
                                                <a href="<?php echo esc_url($agent['admin_url'] ?? ($agent['site'] . '/wp-admin')); ?>"
                                                   class="button button-small"
                                                   target="_blank">
                                                    WordPress
                                                </a>
                                                <button class="button button-small scan-site"
                                                        data-site="<?php echo esc_attr($agent['site']); ?>">
                                                    Scan
                                                </button>
                                                <button class="button button-small button-link-delete remove-site"
                                                        data-site="<?php echo esc_attr($agent['site']); ?>">
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
                            <p>Install and activate the <strong>Watchtower Agent</strong> plugin on your WordPress sites to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($agents as $index => $agent): ?>
                            <?php
                            $details_url = add_query_arg(array(
                                'page' => 'watchtower-manager-site-details',
                                'site' => urlencode($agent['site'])
                            ), admin_url('admin.php'));
                            $health_status = $agent['health_status'];
                            $health_age = $this->storage->get_health_data_age($agent['site']);
                            ?>
                            <div class="mobile-site-tile site-row" data-details-url="<?php echo esc_url($details_url); ?>" data-health-status="<?php echo $health_status; ?>" data-search-text="<?php echo esc_attr($agent['_search_text']); ?>" data-site-name="<?php echo esc_attr(strtolower($agent['name'] ?? $agent['site'])); ?>" data-site-url="<?php echo esc_attr(strtolower($agent['site'])); ?>" data-search-tags="<?php echo esc_attr($agent['_search_tags']); ?>" data-search-plugins="<?php echo esc_attr($agent['_search_plugins']); ?>" data-search-users="<?php echo esc_attr($agent['_search_users']); ?>" data-search-theme="<?php echo esc_attr($agent['_search_theme']); ?>" data-search-settings="<?php echo esc_attr($agent['_search_settings']); ?>" onclick="window.location.href='<?php echo esc_url($details_url); ?>'">
                                <div class="mobile-site-header">
                                    <div class="mobile-site-title">
                                        <a href="<?php echo esc_url($agent['site']); ?>" target="_blank" onclick="event.stopPropagation();">
                                            <?php if (isset($agent['icon']) && $agent['icon']): ?>
                                                <img src="<?php echo esc_url($agent['icon']); ?>" alt="" class="site-icon">
                                            <?php else: ?>
                                                <div class="site-icon-placeholder">
                                                    <span class="dashicons dashicons-admin-site"></span>
                                                </div>
                                            <?php endif; ?>
                                            <strong><?php echo esc_html($agent['name'] ?? $agent['site']); ?></strong>
                                        </a>
                                    </div>
                                    <div class="mobile-site-health">
                                        <?php if ($is_maintenance): ?>
                                            <span class="health-badge health-badge-maintenance">
                                                <span class="dashicons dashicons-admin-tools"></span> Maintenance
                                            </span>
                                        <?php elseif ($health_status === 'healthy'): ?>
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
                                        if (isset($agent['name'])) {
                                            echo esc_html($agent['site']) . ' • ';
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
                                    <a href="<?php echo esc_url($agent['admin_url'] ?? ($agent['site'] . '/wp-admin')); ?>"
                                       class="button button-small"
                                       target="_blank"
                                       onclick="event.stopPropagation();">
                                        WordPress
                                    </a>
                                    <button class="button button-small scan-site"
                                            data-site="<?php echo esc_attr($agent['site']); ?>"
                                            onclick="event.stopPropagation();">
                                        Scan
                                    </button>
                                    <button class="button button-small button-link-delete remove-site"
                                            data-site="<?php echo esc_attr($agent['site']); ?>"
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
            </div>
        </div>

        <?php
    }

    /**
     * Render plugins page
     */
    public function render_plugins_page() {
        $agents = $this->storage->get_all_agents();

        $all_plugins = array();
        $total_installations = 0;
        $active_installations = 0;
        $updates_available = 0;

        foreach ($agents as $agent) {
            $plugins_data = $this->storage->get_plugins_data($agent['site']);
            if (!$plugins_data || empty($plugins_data['plugins'])) {
                continue;
            }

            $site_name = isset($agent['name']) ? $agent['name'] : parse_url($agent['site'], PHP_URL_HOST);

            foreach ($plugins_data['plugins'] as $plugin) {
                $slug = $plugin['slug'];
                $version = $plugin['version'];

                if (!isset($all_plugins[$slug])) {
                    $all_plugins[$slug] = array(
                        'name' => $plugin['name'],
                        'slug' => $slug,
                        'description' => $plugin['description'] ?? '',
                        'author' => $plugin['author'] ?? '',
                        'plugin_uri' => $plugin['plugin_uri'] ?? '',
                        'versions' => array(),
                        'sites' => array(),
                        'active_count' => 0,
                        'update_available_count' => 0,
                    );
                }

                if (!isset($all_plugins[$slug]['versions'][$version])) {
                    $all_plugins[$slug]['versions'][$version] = array(
                        'count' => 0,
                        'sites' => array(),
                    );
                }

                $all_plugins[$slug]['versions'][$version]['count']++;
                $all_plugins[$slug]['versions'][$version]['sites'][] = array(
                    'name' => $site_name,
                    'url' => $agent['site'],
                    'active' => !empty($plugin['active']),
                    'update_available' => !empty($plugin['update_available']),
                );

                $all_plugins[$slug]['sites'][] = $site_name;
                $total_installations++;

                if (!empty($plugin['active'])) {
                    $all_plugins[$slug]['active_count']++;
                    $active_installations++;
                }

                if (!empty($plugin['update_available'])) {
                    $all_plugins[$slug]['update_available_count']++;
                    $updates_available++;
                }
            }
        }

        usort($all_plugins, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $unique_plugins = count($all_plugins);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline" style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <span>Plugins</span>
                <span style="font-size: 14px; font-weight: 400; color: #646970;">Manager: <?php echo esc_html(WATCHTOWER_MANAGER_VERSION); ?></span>
            </h1>
            <hr class="wp-header-end">

            <div style="position: relative; min-height: 400px;">
                <div id="watchtower-plugins-loading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fff; z-index: 100; display: flex; align-items: center; justify-content: center; min-height: 400px;">
                    <div class="watchtower-spinner"></div>
                </div>

                <div id="watchtower-plugins-content" style="opacity: 0; transition: opacity 0.3s ease;">
            <div class="watchtower-manager-dashboard">
                <div class="stats-grid">
                    <div class="stat-card stat-total filter-card" data-filter="all" onclick="filterPlugins('all')">
                        <h3>
                            Unique Plugins
                            <span class="dashicons dashicons-admin-plugins"></span>
                        </h3>
                        <div class="stat-value"><?php echo $unique_plugins; ?></div>
                    </div>
                    <div class="stat-card stat-healthy-good filter-card" data-filter="active" onclick="filterPlugins('active')">
                        <h3>
                            Active
                            <span class="dashicons dashicons-yes-alt"></span>
                        </h3>
                        <div class="stat-value"><?php echo $active_installations; ?></div>
                    </div>
                    <div class="stat-card <?php echo $updates_available > 0 ? 'stat-unhealthy-warning' : 'stat-unhealthy-none'; ?> filter-card" data-filter="updates" onclick="filterPlugins('updates')">
                        <h3>
                            Updates
                            <span class="dashicons dashicons-update"></span>
                        </h3>
                        <div class="stat-value"><?php echo $updates_available; ?></div>
                    </div>
                </div>

                <div class="plugins-toolbar">
                    <div class="wt-btn-combo wt-btn-combo-right" id="plugins-export-dropdown">
                        <button class="wt-btn-icon" id="plugins-export-btn" title="Export">
                            <span class="dashicons dashicons-download"></span>
                            <span class="dashicons dashicons-arrow-down-alt2 wt-btn-caret"></span>
                        </button>
                        <div class="wt-btn-combo-menu" id="plugins-export-menu">
                            <a href="#" class="wt-btn-combo-item" data-export="by-plugin">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                By Plugin
                            </a>
                            <a href="#" class="wt-btn-combo-item" data-export="by-site">
                                <span class="dashicons dashicons-admin-site"></span>
                                By Site
                            </a>
                        </div>
                    </div>
                    <button class="wt-btn-icon" onclick="location.reload()" title="Refresh">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </div>
                <style>
                    .plugins-toolbar {
                        display: flex;
                        justify-content: flex-end;
                        margin-bottom: 12px;
                        gap: 8px;
                    }
                    .wt-btn-icon {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        gap: 4px;
                        width: 32px;
                        height: 32px;
                        padding: 0;
                        background: transparent;
                        border: 1px solid #2271b1;
                        border-radius: 4px;
                        color: #2271b1;
                        cursor: pointer;
                        transition: all 0.15s ease;
                    }
                    .wt-btn-icon:hover {
                        background: #2271b1;
                        color: #fff;
                    }
                    .wt-btn-icon .dashicons {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                        line-height: 1;
                    }
                    .wt-btn-icon .wt-btn-caret {
                        font-size: 12px;
                        width: 12px;
                        height: 12px;
                        margin-left: -2px;
                        transition: transform 0.15s ease;
                    }
                    .wt-btn-combo {
                        position: relative;
                        display: inline-flex;
                    }
                    .wt-btn-combo > .wt-btn-icon {
                        width: auto;
                        padding: 0 8px;
                    }
                    .wt-btn-combo.wt-open > .wt-btn-icon .wt-btn-caret {
                        transform: rotate(180deg);
                    }
                    .wt-btn-combo-menu {
                        position: absolute;
                        top: 100%;
                        left: 0;
                        z-index: 100;
                        min-width: 160px;
                        margin-top: 4px;
                        padding: 4px 0;
                        background: #fff;
                        border: 1px solid #ccd0d4;
                        border-radius: 6px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-8px);
                        transition: opacity 0.15s ease, visibility 0.15s ease, transform 0.15s ease;
                    }
                    .wt-btn-combo.wt-open .wt-btn-combo-menu {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0);
                    }
                    .wt-btn-combo-right .wt-btn-combo-menu {
                        left: auto;
                        right: 0;
                    }
                    .wt-btn-combo-item {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        width: 100%;
                        padding: 8px 16px;
                        background: none;
                        border: none;
                        font-size: 13px;
                        color: #1d2327;
                        text-align: left;
                        text-decoration: none !important;
                        cursor: pointer;
                        transition: background-color 0.1s ease, color 0.1s ease;
                    }
                    .wt-btn-combo-item:hover {
                        background: #f0f0f1;
                        color: #2271b1;
                    }
                    .wt-btn-combo-item .dashicons {
                        font-size: 16px;
                        width: 16px;
                        height: 16px;
                        opacity: 0.7;
                    }
                    .wt-btn-combo-item:hover .dashicons {
                        opacity: 1;
                    }
                </style>

                <div class="sites-table">
                    <?php if (empty($all_plugins)): ?>
                        <div class="empty-state">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <h3>No plugins found</h3>
                            <p>Plugin data will appear here once sites are registered and scanned.</p>
                        </div>
                        <script>
                        jQuery(document).ready(function($) {
                            setTimeout(function() {
                                $('#watchtower-plugins-loading').fadeOut(300, function() {
                                    $(this).remove();
                                });
                                $('#watchtower-plugins-content').css('opacity', '1');
                            }, 300);
                        });
                        </script>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="font-weight: bold;">Plugin</th>
                                    <th style="font-weight: bold;">Slug</th>
                                    <th style="font-weight: bold;">Versions</th>
                                    <th style="font-weight: bold; width: 80px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_plugins as $plugin):
                                    $plugin_json = esc_attr(json_encode($plugin));
                                    $has_updates = $plugin['update_available_count'] > 0 ? 'true' : 'false';
                                    $has_active = $plugin['active_count'] > 0 ? 'true' : 'false';
                                ?>
                                    <tr class="global-plugin-row clickable-row" data-plugin="<?php echo $plugin_json; ?>" data-has-updates="<?php echo $has_updates; ?>" data-has-active="<?php echo $has_active; ?>" style="cursor: pointer;">
                                        <td>
                                            <strong><?php echo esc_html($plugin['name']); ?></strong>
                                            <div style="font-size: 12px; margin-top: 4px;">
                                                <span style="background: #e5f3ff; color: #0073aa; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600;"><?php echo count($plugin['sites']); ?> site<?php echo count($plugin['sites']) !== 1 ? 's' : ''; ?></span>
                                                <?php if ($plugin['update_available_count'] > 0): ?>
                                                    <span style="background: #fcf0e3; color: #996800; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; margin-left: 6px;"><?php echo $plugin['update_available_count']; ?> update<?php echo $plugin['update_available_count'] !== 1 ? 's' : ''; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><code><?php echo esc_html($plugin['slug']); ?></code></td>
                                        <td>
                                            <?php
                                            krsort($plugin['versions']);
                                            $version_badges = array();
                                            foreach ($plugin['versions'] as $version => $info) {
                                                $version_badges[] = '<span style="background: #f0f0f1; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-right: 4px; display: inline-block; margin-bottom: 4px;">' . esc_html($version) . '</span>';
                                            }
                                            echo implode('', $version_badges);
                                            ?>
                                        </td>
                                        <td style="text-align: right;"><button class="button global-plugin-details-btn">Details</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div id="global-plugin-details-dialog" class="watchtower-dialog-overlay" style="display: none;">
                            <div class="watchtower-dialog" style="max-width: 700px; width: 90%;">
                                <div class="watchtower-dialog-header">
                                    <span class="dashicons dashicons-admin-plugins watchtower-dialog-icon prompt"></span>
                                    <h3 class="watchtower-dialog-title" id="global-plugin-dialog-title" style="flex: 1;">Plugin Details</h3>
                                </div>
                                <div class="watchtower-dialog-body" id="global-plugin-dialog-body" style="font-weight: normal; max-height: 400px; overflow-y: auto;">
                                </div>
                                <div class="watchtower-dialog-footer">
                                    <button class="watchtower-dialog-button secondary" id="global-plugin-dialog-close">Close</button>
                                </div>
                            </div>
                        </div>

                        <script>
                        var currentPluginFilter = null;

                        function filterPlugins(filter) {
                            var $ = jQuery;
                            var $cards = $('.filter-card');
                            var $rows = $('.global-plugin-row');

                            if (currentPluginFilter === filter) {
                                currentPluginFilter = null;
                                $cards.removeClass('active');
                                $rows.show();
                                return;
                            }

                            currentPluginFilter = filter;
                            $cards.removeClass('active');
                            $cards.filter('[data-filter="' + filter + '"]').addClass('active');

                            $rows.each(function() {
                                var $row = $(this);
                                var show = false;

                                if (filter === 'all') {
                                    show = true;
                                } else if (filter === 'updates') {
                                    show = $row.data('has-updates') === true || $row.data('has-updates') === 'true';
                                } else if (filter === 'active') {
                                    show = $row.data('has-active') === true || $row.data('has-active') === 'true';
                                }

                                $row.toggle(show);
                            });
                        }

                        jQuery(document).ready(function($) {
                            function showGlobalPluginDetails(plugin) {
                                var html = '<div style="line-height: 1.8;">';

                                html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px;">';
                                html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600; width: 120px;">Slug</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;"><code>' + plugin.slug + '</code></td></tr>';
                                if (plugin.author) {
                                    html += '<tr><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-weight: 600;">Author</td><td style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">' + plugin.author + '</td></tr>';
                                }
                                html += '</table>';

                                html += '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
                                html += '<thead><tr><th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Site</th><th style="padding: 8px 0; border-bottom: 1px solid #ccd0d4; text-align: left; font-weight: 600; color: #1d2327; background: none;">Version</th></tr></thead>';
                                html += '<tbody>';
                                var versions = Object.keys(plugin.versions).sort().reverse();
                                versions.forEach(function(version) {
                                    var info = plugin.versions[version];
                                    info.sites.forEach(function(site) {
                                        var updateTag = site.update_available ? '<span style="background: #fcf0e3; color: #996800; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; margin-left: 8px;">update</span>' : '';
                                        html += '<tr>';
                                        html += '<td style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;"><a href="admin.php?page=watchtower-manager-site-details&site=' + encodeURIComponent(site.url) + '">' + site.name + '</a></td>';
                                        html += '<td style="padding: 10px 0; border-bottom: 1px solid #f0f0f1;">' + version + updateTag + '</td>';
                                        html += '</tr>';
                                    });
                                });
                                html += '</tbody></table>';

                                html += '</div>';

                                $('#global-plugin-dialog-title').text(plugin.name);
                                $('#global-plugin-dialog-body').html(html);
                                $('#global-plugin-details-dialog').show();
                            }

                            $(document).on('click', '.global-plugin-row', function(e) {
                                if ($(e.target).is('a')) {
                                    return;
                                }
                                var plugin = $(this).data('plugin');
                                if (plugin) {
                                    showGlobalPluginDetails(plugin);
                                }
                            });

                            $('#global-plugin-dialog-close').on('click', function() {
                                $('#global-plugin-details-dialog').hide();
                            });

                            $('#global-plugin-details-dialog').on('click', function(e) {
                                if ($(e.target).hasClass('watchtower-dialog-overlay')) {
                                    $(this).hide();
                                }
                            });

                            setTimeout(function() {
                                $('#watchtower-plugins-loading').fadeOut(300, function() {
                                    $(this).remove();
                                });
                                $('#watchtower-plugins-content').css('opacity', '1');
                            }, 300);

                            $('#plugins-export-btn').on('click', function(e) {
                                e.stopPropagation();
                                $('#plugins-export-dropdown').toggleClass('wt-open');
                            });

                            $(document).on('click', function(e) {
                                if (!$(e.target).closest('#plugins-export-dropdown').length) {
                                    $('#plugins-export-dropdown').removeClass('wt-open');
                                }
                            });

                            $('.wt-btn-combo-item').on('click', function(e) {
                                e.preventDefault();
                                var exportType = $(this).data('export');
                                $('#plugins-export-dropdown').removeClass('wt-open');
                                exportPluginsCSV(exportType);
                            });

                            function exportPluginsCSV(type) {
                                var rows = [];
                                var now = new Date();
                                var timestamp = now.getFullYear() + '-' +
                                    ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
                                    ('0' + now.getDate()).slice(-2) + '-' +
                                    ('0' + now.getHours()).slice(-2) + '-' +
                                    ('0' + now.getMinutes()).slice(-2) + '-' +
                                    ('0' + now.getSeconds()).slice(-2);

                                $('.global-plugin-row').each(function() {
                                    var plugin = $(this).data('plugin');
                                    if (plugin && plugin.versions) {
                                        var versions = Object.keys(plugin.versions);
                                        versions.forEach(function(version) {
                                            var info = plugin.versions[version];
                                            info.sites.forEach(function(site) {
                                                rows.push({
                                                    plugin: plugin.name,
                                                    version: version,
                                                    site: site.name
                                                });
                                            });
                                        });
                                    }
                                });

                                var csv, filename;
                                if (type === 'by-site') {
                                    rows.sort(function(a, b) {
                                        return a.site.localeCompare(b.site) ||
                                               a.plugin.localeCompare(b.plugin) ||
                                               a.version.localeCompare(b.version);
                                    });
                                    csv = 'site,plugin,version\n';
                                    rows.forEach(function(row) {
                                        csv += '"' + row.site.replace(/"/g, '""') + '","' +
                                               row.plugin.replace(/"/g, '""') + '","' +
                                               row.version.replace(/"/g, '""') + '"\n';
                                    });
                                    filename = 'plugins-by-site-' + timestamp + '.csv';
                                } else {
                                    rows.sort(function(a, b) {
                                        return a.plugin.localeCompare(b.plugin) ||
                                               a.version.localeCompare(b.version) ||
                                               a.site.localeCompare(b.site);
                                    });
                                    csv = 'plugin,version,site\n';
                                    rows.forEach(function(row) {
                                        csv += '"' + row.plugin.replace(/"/g, '""') + '","' +
                                               row.version.replace(/"/g, '""') + '","' +
                                               row.site.replace(/"/g, '""') + '"\n';
                                    });
                                    filename = 'plugins-' + timestamp + '.csv';
                                }

                                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                                var link = document.createElement('a');
                                link.href = URL.createObjectURL(blob);
                                link.download = filename;
                                link.click();
                            }
                        });
                        </script>
                    <?php endif; ?>
                </div>
            </div>
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $result = $this->storage->check_and_update_agent_version($agent, true);

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

        $scan_result = $this->storage->fetch_and_save_health($agent);

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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $result = $this->storage->fetch_and_save_health($agent);

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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'];

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        if ($force_refresh) {
            $backups_data = $this->fetch_backups_from_agent($site_url, $agent);
        } else {
            $backups_data = $this->storage->get_backups_data($site_url);

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
        $this->storage->save_backups_data($site_url, $data);
        return $data;
    }

    /**
     * AJAX: Create backup
     */
    public function ajax_create_backup() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
     * AJAX: Get backup status
     */
    public function ajax_get_backup_status() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
        $backup_id = intval($_POST['backup_id']);

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $status_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/backups/' . $backup_id);

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
     * AJAX: Restore backup
     */
    public function ajax_restore_backup() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
        $backup_id = intval($_POST['backup_id']);

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $restore_url = watchtower_manager_translate_agent_url($site_url, '/watchtower-agent/v1/restore');

        error_log('Watchtower Manager: POST request to: ' . $restore_url);
        error_log('Watchtower Manager: Backup ID: ' . $backup_id);

        $response = wp_remote_post($restore_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('id' => $backup_id)),
            'timeout' => 300, // 5 minutes for restore to complete
            'sslverify' => false,
        ));

        error_log('Watchtower Manager: Restore response status: ' . wp_remote_retrieve_response_code($response));

        if (is_wp_error($response)) {
            error_log('Watchtower Manager: Restore request error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $body = wp_remote_retrieve_body($response);

        error_log('Watchtower Manager: Restore response body: ' . "\n" . $body);

        $data = json_decode($body, true);

        if (!$data) {
            wp_send_json_error(array('message' => 'Invalid JSON response from agent'));
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = sanitize_text_field($_POST['site']);
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
            $backups_data = $this->storage->get_backups_data($site_url);
            if ($backups_data) {
                $backups_data['fetched_at'] = '1970-01-01 00:00:00';
                $this->storage->save_backups_data($site_url, $backups_data);
            }
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => isset($data['error']) ? $data['error'] : 'Unknown error'));
        }
    }

    /**
     * AJAX handler to get agent information
     */
    public function ajax_get_agent() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';

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
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
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

        $translated_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/audit');

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

    public function ajax_get_users() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Site URL required'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $users_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/users?role=administrator');

        $response = wp_remote_get($users_url, array(
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

    public function ajax_create_user() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : 'subscriber';

        if (empty($site_url) || empty($username) || empty($email) || empty($password)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $users_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/users');

        $body_data = array(
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        );

        if (!empty($first_name)) {
            $body_data['first_name'] = $first_name;
        }

        if (!empty($last_name)) {
            $body_data['last_name'] = $last_name;
        }

        $response = wp_remote_post($users_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body_data)
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['error'] ?? 'Failed to create user'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_update_user() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if (empty($site_url) || empty($user_id)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $users_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/users/' . $user_id);

        $body_data = array();

        if (!empty($email)) {
            $body_data['email'] = $email;
        }

        if (!empty($first_name)) {
            $body_data['first_name'] = $first_name;
        }

        if (!empty($last_name)) {
            $body_data['last_name'] = $last_name;
        }

        if (!empty($role)) {
            $body_data['role'] = $role;
        }

        if (!empty($password)) {
            $body_data['password'] = $password;
        }

        $response = wp_remote_request($users_url, array(
            'method' => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($body_data)
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['error'] ?? 'Failed to update user'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_delete_user() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (empty($site_url) || empty($user_id)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $users_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/users/' . $user_id);

        $response = wp_remote_request($users_url, array(
            'method' => 'DELETE',
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['error'] ?? 'Failed to delete user'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_reset_password() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (empty($site_url) || empty($user_id)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $reset_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/users/' . $user_id . '/reset-password');

        $response = wp_remote_post($reset_url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['error'] ?? 'Failed to reset password'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_list_files() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $files_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/files/list');

        $response = wp_remote_post($files_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'path' => $path
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to list files'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_create_file() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        if (empty($site_url) || empty($path) || empty($type)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $endpoint = $type === 'directory' ? '/watchtower-agent/v1/files/create-directory' : '/watchtower-agent/v1/files/save';
        $create_url = watchtower_manager_translate_agent_url($agent['site'], $endpoint);

        $response = wp_remote_post($create_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'path' => $path,
                'content' => ''
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to create ' . $type));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_get_file_content() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        if (empty($site_url) || empty($path)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $content_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/files/content');

        $response = wp_remote_post($content_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'path' => $path
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to get file content'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_save_file() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        if (empty($site_url) || empty($path)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $save_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/files/save');

        $response = wp_remote_post($save_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'path' => $path,
                'content' => $content
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to save file'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_rename_file() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $old_path = isset($_POST['old_path']) ? sanitize_text_field($_POST['old_path']) : '';
        $new_path = isset($_POST['new_path']) ? sanitize_text_field($_POST['new_path']) : '';

        if (empty($site_url) || empty($old_path) || empty($new_path)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $rename_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/files/rename');

        $response = wp_remote_post($rename_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'old_path' => $old_path,
                'new_path' => $new_path
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to rename'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_delete_file() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        if (empty($site_url) || empty($path)) {
            wp_send_json_error(array('message' => 'Missing required fields'));
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error(array('message' => 'Agent not found'));
            return;
        }

        $delete_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/files/delete');

        $response = wp_remote_post($delete_url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
            ),
            'body' => array(
                'path' => $path
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

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error(array('message' => $data['message'] ?? 'Failed to delete'));
            return;
        }

        wp_send_json_success($data);
    }

    public function ajax_toggle_maintenance() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $site_url = isset($_POST['site_url']) ? sanitize_text_field($_POST['site_url']) : '';
        $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($site_url)) {
            wp_send_json_error('Missing site URL');
            return;
        }

        $agent = $this->storage->get_agent_by_url($site_url);

        if (!$agent) {
            wp_send_json_error('Agent not found');
            return;
        }

        $maintenance_url = watchtower_manager_translate_agent_url($agent['site'], '/watchtower-agent/v1/maintenance');

        $response = wp_remote_post($maintenance_url, array(
            'timeout' => 10,
            'sslverify' => false,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($agent['username'] . ':' . $agent['password']),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'enabled' => $enabled
            ))
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            wp_send_json_error('Invalid response from agent');
            return;
        }

        if (isset($data['success']) && !$data['success']) {
            wp_send_json_error($data['error'] ?? 'Failed to toggle maintenance mode');
            return;
        }

        $agent['mode'] = $enabled ? 'maintenance' : 'live';
        $this->storage->save_agent($agent);

        wp_send_json_success($data);
    }

    public function ajax_get_tags() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Site URL required'));
            return;
        }

        $tags = $this->storage->get_tags_data($site_url);

        if ($tags === null) {
            $tags = array();
        }

        wp_send_json_success(array('tags' => $tags));
    }

    public function ajax_save_tags() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $site_url = isset($_POST['site']) ? sanitize_text_field($_POST['site']) : '';

        if (empty($site_url)) {
            wp_send_json_error(array('message' => 'Site URL required'));
            return;
        }

        $tags = isset($_POST['tags']) ? $_POST['tags'] : array();

        if (!is_array($tags)) {
            $tags = array();
        }

        $tags = array_map('sanitize_text_field', $tags);
        $tags = array_values(array_unique(array_filter($tags)));

        $result = $this->storage->save_tags_data($site_url, $tags);

        if ($result) {
            wp_send_json_success(array('tags' => $tags));
        } else {
            wp_send_json_error(array('message' => 'Failed to save tags'));
        }
    }

    private function get_search_terms() {
        $agents = $this->storage->get_all_agents();
        $all = array();
        $names = array();
        $urls = array();
        $plugins = array();
        $tags = array();
        $users = array();
        $themes = array();

        foreach ($agents as $agent) {
            if (isset($agent['name'])) {
                $names[] = $agent['name'];
                $all[] = $agent['name'];
            }
            $urls[] = $agent['site'];
            $all[] = $agent['site'];

            if (isset($agent['theme']['name'])) {
                $themes[] = $agent['theme']['name'];
                $all[] = $agent['theme']['name'];
            }

            $site_tags = $this->storage->get_tags_data($agent['site']);
            if (is_array($site_tags)) {
                $tags = array_merge($tags, $site_tags);
                $all = array_merge($all, $site_tags);
            }

            $plugins_data = $this->storage->get_plugins_data($agent['site']);
            if ($plugins_data && isset($plugins_data['plugins']) && is_array($plugins_data['plugins'])) {
                foreach ($plugins_data['plugins'] as $plugin) {
                    if (isset($plugin['name'])) {
                        $plugins[] = $plugin['name'];
                        $all[] = $plugin['name'];
                    }
                }
            }

            $users_data = $this->storage->get_users_data($agent['site']);
            if ($users_data && isset($users_data['users']) && is_array($users_data['users'])) {
                foreach ($users_data['users'] as $user) {
                    if (isset($user['username'])) {
                        $users[] = $user['username'];
                        $all[] = $user['username'];
                    }
                }
            }
        }

        $dedupe_sort = function($arr) {
            $arr = array_values(array_unique(array_filter($arr)));
            sort($arr);
            return $arr;
        };

        return array(
            'all' => $dedupe_sort($all),
            'names' => $dedupe_sort($names),
            'urls' => $dedupe_sort($urls),
            'plugins' => $dedupe_sort($plugins),
            'tags' => $dedupe_sort($tags),
            'users' => $dedupe_sort($users),
            'themes' => $dedupe_sort($themes),
        );
    }

    public function ajax_get_all_tags() {
        check_ajax_referer('watchtower_manager_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $tags = $this->storage->get_all_tags();
        wp_send_json_success(array('tags' => $tags));
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
    if (!isset($_GET['site'])) {
        wp_die('Site URL parameter missing');
    }

    $site_url = urldecode($_GET['site']);
    $agent = $this->storage->get_agent_by_url($site_url);

    if (!$agent) {
        wp_die('Agent not found');
    }

    $health_data = $this->storage->get_health_data($site_url);
    $health_age = $this->storage->get_health_data_age($site_url);

    if ($this->storage->is_health_data_stale($site_url)) {
        $this->storage->fetch_and_save_health($agent);
        $health_data = $this->storage->get_health_data($site_url);
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

        <div style="position: relative; min-height: 400px; margin-top: 20px;">
            <div id="watchtower-page-loading" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #fff; z-index: 100; display: flex; align-items: center; justify-content: center; min-height: 400px;">
                <div class="watchtower-spinner"></div>
            </div>

            <div id="watchtower-page-content" style="opacity: 0; transition: opacity 0.3s ease;">
                <div class="watchtower-manager-dashboard">
            <!-- Overall Health Status -->
            <?php
            $is_maintenance_mode = isset($agent['mode']) && $agent['mode'] === 'maintenance';
            $display_status = $is_maintenance_mode ? 'warning' : $overall_status;
            ?>
            <div class="health-status-card <?php echo $display_status; ?>">
                <span class="dashicons <?php
                    if ($is_maintenance_mode) {
                        echo 'dashicons-admin-tools';
                    } else {
                        echo $overall_status === 'healthy' ? 'dashicons-yes-alt' :
                            ($overall_status === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss');
                    }
                ?> health-status-icon <?php echo $display_status; ?>"></span>
                <div class="health-status-content">
                    <div class="health-status-info">
                        <div class="health-status-title">
                            <?php echo esc_html($agent['name'] ?? $site_url); ?>
                            <?php if (isset($agent['name'])): ?>
                                <div style="font-size: 14px; font-weight: 400; color: #646970;">
                                    <?php echo esc_html($site_url); ?>
                                    <?php if (isset($agent['agent_version'])): ?>
                                        - <?php echo esc_html($agent['agent_version']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_health_data): ?>
                            <?php
                            $has_intermission = false;
                            $maintenance_enabled = false;
                            if (isset($health_data['plugins']['active_plugins'])) {
                                foreach ($health_data['plugins']['active_plugins'] as $plugin) {
                                    if (isset($plugin['file']) && $plugin['file'] === 'intermission/intermission.php') {
                                        $has_intermission = true;
                                        break;
                                    }
                                }
                            }
                            if ($has_intermission) {
                                $agent_url = $agent['site'];
                                $username = $agent['username'];
                                $password = $agent['password'];
                                $maintenance_status_url = watchtower_manager_translate_agent_url($agent_url, '/watchtower-agent/v1/maintenance');
                                $maintenance_status_response = wp_remote_get(
                                    $maintenance_status_url,
                                    array(
                                        'timeout' => 10,
                                        'headers' => array(
                                            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                                        ),
                                        'sslverify' => false,
                                    )
                                );
                                if (!is_wp_error($maintenance_status_response)) {
                                    $maintenance_body = json_decode(wp_remote_retrieve_body($maintenance_status_response), true);
                                    if (isset($maintenance_body['maintenance_enabled'])) {
                                        $maintenance_enabled = $maintenance_body['maintenance_enabled'];
                                    }
                                }
                            }
                            ?>
                            <?php if ($has_intermission): ?>
                            <div class="maintenance-mode-toggle">
                                <input type="checkbox" id="maintenance-mode-toggle" <?php checked($maintenance_enabled); ?> data-site-url="<?php echo esc_attr($site_url); ?>">
                                <label for="maintenance-mode-toggle" class="maintenance-toggle-switch <?php echo $maintenance_enabled ? 'maintenance' : 'live'; ?>">
                                    <span class="maintenance-toggle-slider"></span>
                                </label>
                                <span class="maintenance-mode-label"><?php echo $maintenance_enabled ? 'Maintenance' : 'Live'; ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="health-status-subtitle <?php echo $display_status; ?>">
                            <span class="health-status-text">
                            <?php
                            if ($is_maintenance_mode) echo 'Site in Maintenance';
                            elseif ($overall_status === 'healthy') echo 'Site is Healthy';
                            elseif ($overall_status === 'warning') echo 'Site Needs Attention';
                            else echo 'Site Has Critical Issues';
                            ?>
                            </span>
                            <?php if ($has_health_data): ?>
                            <button class="health-status-toggle" onclick="toggleHealthDetails(event)" style="margin-top: 2px; background: none; border: none; cursor: pointer; color: inherit; padding: 0; vertical-align: middle;">
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
                    <option value="users">Users</option>
                    <option value="activity">Activity</option>
                    <option value="logs">Logs</option>
                    <option value="files">Files</option>
                    <option value="tags">Tags</option>
                    <option value="actions">Actions</option>
                </select>
            </div>

            <!-- Tab Navigation (Desktop) -->
            <div class="watchtower-tabs-wrapper">
                <div class="watchtower-tabs">
                    <button class="watchtower-tab-btn active" data-tab="overview">
                        <span class="dashicons dashicons-dashboard"></span> Overview
                    </button>
                    <button class="watchtower-tab-btn" data-tab="plugins">
                        <span class="dashicons dashicons-admin-plugins"></span> Plugins
                    </button>
                    <button class="watchtower-tab-btn" data-tab="users">
                        <span class="dashicons dashicons-admin-users"></span> Users
                    </button>
                    <button class="watchtower-tab-btn" data-tab="backups">
                        <span class="dashicons dashicons-database-export"></span> Backups
                    </button>
                    <button class="watchtower-tab-btn" data-tab="activity">
                        <span class="dashicons dashicons-clipboard"></span> Activity
                    </button>
                    <button class="watchtower-tab-btn" data-tab="logs">
                        <span class="dashicons dashicons-media-text"></span> Logs
                    </button>
                    <button class="watchtower-tab-btn" data-tab="files">
                        <span class="dashicons dashicons-media-code"></span> Files
                    </button>
                    <button class="watchtower-tab-btn" data-tab="tags">
                        <span class="dashicons dashicons-tag"></span> Tags
                    </button>
                    <button class="watchtower-tab-btn" data-tab="security">
                        <span class="dashicons dashicons-shield"></span> Security
                    </button>
                    <button class="watchtower-tab-btn" data-tab="performance">
                        <span class="dashicons dashicons-performance"></span> Performance
                    </button>
                    <button class="watchtower-tab-btn" data-tab="settings">
                        <span class="dashicons dashicons-admin-settings"></span> Settings
                    </button>
                    <button class="watchtower-tab-btn" data-tab="database">
                        <span class="dashicons dashicons-database"></span> Database
                    </button>
                    <button class="watchtower-tab-btn" data-tab="advanced">
                        <span class="dashicons dashicons-admin-generic"></span> Advanced
                    </button>
                    <button class="watchtower-tab-btn" data-tab="actions">
                        <span class="dashicons dashicons-admin-tools"></span> Actions
                    </button>
                </div>
                <div class="watchtower-tabs-overflow" style="display: none;">
                    <button class="watchtower-tab-btn watchtower-overflow-btn">
                        <span class="dashicons dashicons-arrow-down-alt2"></span> More
                    </button>
                    <div class="watchtower-overflow-menu">
                    </div>
                </div>
            </div>

            <!-- Tab Content: Overview -->
            <div class="watchtower-tab-content" id="tab-overview">
            <?php if ($has_health_data): ?>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
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
                <?php
                $plugins_data = $this->storage->get_plugins_data($agent['site']);
                $plugins_list = isset($plugins_data['plugins']) ? $plugins_data['plugins'] : array();
                ?>
                <?php if (!empty($plugins_list)): ?>
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Plugins</h2>
                    </div>
                    <table class="wp-list-table widefat fixed striped watchtower-plugins-table">
                        <thead>
                            <tr>
                                <th style="font-weight: bold;">Name</th>
                                <th style="font-weight: bold;">Version</th>
                                <th style="font-weight: bold;" class="plugin-state-col">State</th>
                                <th style="font-weight: bold;" class="plugin-slug-col">Slug</th>
                                <th style="font-weight: bold; width: 80px; text-align: right;" class="plugin-actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sorted_plugins = $plugins_list;
                            usort($sorted_plugins, function($a, $b) {
                                return strcasecmp($a['name'], $b['name']);
                            });
                            foreach ($sorted_plugins as $plugin):
                            $plugin_json = esc_attr(json_encode($plugin));
                            $is_active = !empty($plugin['active']);
                            ?>
                                <tr class="plugin-row" data-plugin="<?php echo $plugin_json; ?>">
                                    <td><?php echo esc_html($plugin['name']); ?></td>
                                    <td><?php echo esc_html($plugin['version']); ?></td>
                                    <td class="plugin-state-col">
                                        <?php if ($is_active): ?>
                                            <span style="background: #d5f3e5; color: #00a32a; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">Active</span>
                                        <?php else: ?>
                                            <span style="background: #f0f0f1; color: #646970; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="plugin-slug-col"><code><?php echo esc_html($plugin['slug']); ?></code></td>
                                    <td class="plugin-actions-col" style="text-align: right;"><button class="button plugin-details-btn">Details</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div id="plugin-details-dialog" class="watchtower-dialog-overlay" style="display: none;">
                        <div class="watchtower-dialog" style="max-width: 600px;">
                            <div class="watchtower-dialog-header">
                                <span class="dashicons dashicons-admin-plugins watchtower-dialog-icon prompt"></span>
                                <h3 class="watchtower-dialog-title" id="plugin-dialog-title" style="flex: 1;">Plugin Details</h3>
                            </div>
                            <div class="watchtower-dialog-body" id="plugin-dialog-body" style="font-weight: normal; max-height: 400px; overflow-y: auto;">
                            </div>
                            <div class="watchtower-dialog-footer">
                                <button class="watchtower-dialog-button secondary" id="plugin-dialog-close">Close</button>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <div style="color: #666; font-size: 13px;">
                            Total: <?php echo count($plugins_list); ?> plugins (<?php echo $plugins_data['active_count'] ?? 0; ?> active)
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div style="background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                    <span class="dashicons dashicons-admin-plugins" style="font-size: 64px; width: 64px; height: 64px; color: #646970; margin-bottom: 15px;"></span>
                    <h2>No Plugin Data Available</h2>
                    <p style="color: #646970;">Plugin information is not available. The agent may be offline or health monitoring may not be configured.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab Content: Users -->
            <div class="watchtower-tab-content" id="tab-users" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Admin Users</h2>
                        <button id="create-user-btn" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> User
                        </button>
                    </div>
                    <div id="users-loading" style="text-align: center; padding: 40px;">
                        <div class="watchtower-spinner" style="margin: 0 auto 15px;"></div>
                        <p>Loading users...</p>
                    </div>
                    <div id="users-container" style="display: none;">
                        <table class="wp-list-table widefat fixed striped users-table" id="users-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%; font-weight: bold;">Username</th>
                                    <th style="width: 20%; font-weight: bold;">Email</th>
                                    <th style="width: 15%; font-weight: bold;">First Name</th>
                                    <th style="width: 15%; font-weight: bold;">Last Name</th>
                                    <th style="width: 15%; font-weight: bold;">Role</th>
                                    <th class="actions-column" style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                            </tbody>
                        </table>
                        <div class="mobile-users-grid" id="mobile-users-grid">
                        </div>
                        <div style="margin-top: 20px;">
                            <div id="users-stats" style="color: #666; font-size: 13px;">
                                Total: <span id="users-count">0</span> users
                            </div>
                        </div>
                    </div>
                    <div id="users-empty" style="display: none; text-align: center; padding: 40px;">
                        <span class="dashicons dashicons-admin-users" style="font-size: 64px; width: 64px; height: 64px; color: #646970; margin-bottom: 15px;"></span>
                        <h3>No Admin Users Found</h3>
                        <p style="color: #646970;">No administrator users were discovered on this site.</p>
                    </div>
                    <div id="users-error" style="display: none; background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                        <span class="dashicons dashicons-warning" style="font-size: 64px; width: 64px; height: 64px; color: #d63638; margin-bottom: 15px;"></span>
                        <h3>Unable to Load Users</h3>
                        <p style="color: #646970;" id="users-error-message">An error occurred while loading users.</p>
                    </div>
                </div>
            </div>

            <!-- Create User Modal -->
            <div id="create-user-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <h2 id="user-modal-title" style="margin-top: 0; margin-bottom: 20px;">Create New User</h2>
                    <div style="margin-bottom: 15px;">
                        <label for="new-username" style="display: block; margin-bottom: 5px; font-weight: 600;">Username *</label>
                        <input type="text" id="new-username" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="new-email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email *</label>
                        <input type="email" id="new-email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label id="new-password-label" for="new-password" style="display: block; margin-bottom: 5px; font-weight: 600;">Password *</label>
                        <input type="password" id="new-password" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="new-first-name" style="display: block; margin-bottom: 5px; font-weight: 600;">First Name</label>
                        <input type="text" id="new-first-name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="new-last-name" style="display: block; margin-bottom: 5px; font-weight: 600;">Last Name</label>
                        <input type="text" id="new-last-name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label for="new-role" style="display: block; margin-bottom: 5px; font-weight: 600;">Role *</label>
                        <select id="new-role" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="administrator">Administrator</option>
                            <option value="editor">Editor</option>
                            <option value="author">Author</option>
                            <option value="contributor">Contributor</option>
                            <option value="subscriber">Subscriber</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button id="cancel-create-user-btn" class="button">Cancel</button>
                        <button id="confirm-create-user-btn" class="button button-primary">Create User</button>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Backups -->
            <div class="watchtower-tab-content" id="tab-backups" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Backups</h2>
                        <button id="create-backup-btn" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> Backup
                        </button>
                    </div>
                    <div id="backups-loading" style="text-align: center; padding: 40px;">
                        <div class="watchtower-spinner" style="margin: 0 auto 15px;"></div>
                        <p>Loading backups...</p>
                    </div>
                    <div id="backups-container" style="display: none;">
                        <table class="wp-list-table widefat fixed striped backups-table" id="backups-table">
                            <thead>
                                <tr>
                                    <th style="width: 25%; font-weight: bold;">Time</th>
                                    <th style="width: 15%; font-weight: bold;">Size</th>
                                    <th style="width: 40%; font-weight: bold;">Components</th>
                                    <th class="actions-column" style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backups-tbody">
                            </tbody>
                        </table>
                        <div class="mobile-backups-grid" style="display: none;">
                        </div>
                    </div>
                    <div id="backups-empty" style="display: none; text-align: center; padding: 40px; color: #646970;">
                        <span class="dashicons dashicons-database-export" style="font-size: 64px; width: 64px; height: 64px; color: #c3c4c7; margin-bottom: 15px;"></span>
                        <h3 style="font-size: 16px; margin-bottom: 8px;">No backups found</h3>
                        <p style="margin: 0;">Create your first backup to get started.</p>
                    </div>
                    <div id="backups-error" style="display: none; padding: 20px; background: #fcdddd; border: 1px solid #d63638; border-radius: 4px; color: #d63638;">
                        <strong>Error loading backups:</strong> <span id="backups-error-message"></span>
                    </div>
                </div>
            </div>

            <!-- Backup Progress Modal -->
            <div id="backup-progress-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <h2 style="margin-top: 0; margin-bottom: 20px;">Creating Backup</h2>

                    <div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden; margin-bottom: 15px;">
                        <div id="backup-progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">
                            <span id="backup-progress-percent">0%</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; min-height: 32px;">
                        <p id="backup-progress-message" style="margin: 0; color: #646970; flex: 1;">Initializing backup...</p>
                        <button id="backup-progress-close" class="button button-secondary" style="display: none; margin-left: 15px;">Close</button>
                    </div>
                </div>
            </div>

            <!-- Restore Progress Modal -->
            <div id="restore-progress-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <h2 style="margin-top: 0; margin-bottom: 20px;">Restoring Backup</h2>

                    <div style="background: #f0f0f1; border-radius: 4px; height: 30px; overflow: hidden; margin-bottom: 15px;">
                        <div id="restore-progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">
                            <span id="restore-progress-percent">0%</span>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; min-height: 32px;">
                        <p id="restore-progress-message" style="margin: 0; color: #646970; flex: 1;">Initializing restore...</p>
                        <button id="restore-progress-close" class="button button-secondary" style="display: none; margin-left: 15px;">Close</button>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Activity -->
            <div class="watchtower-tab-content" id="tab-activity" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; min-height: 40px;">
                        <h2 style="margin: 0;">Activity Log</h2>
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center;">
                                <label for="activity-date-picker" style="margin-right: 8px; margin-bottom: 0; min-width: 60px;"><strong>Date:</strong></label>
                                <input type="text" id="activity-date-picker" class="regular-text" style="width: 150px;" readonly placeholder="Select date...">
                            </div>
                            <div style="position: relative; display: flex; align-items: center;">
                                <label for="activity-action-filter" style="margin-right: 8px; margin-bottom: 0; min-width: 60px;"><strong>Action:</strong></label>
                                <button type="button" id="activity-action-filter" class="button" style="min-width: 120px; text-align: left; border-color: #8c8f94; color: #2c3338; font-size: 14px;">
                                    All Actions <span class="dashicons dashicons-arrow-down-alt2" style="float: right; margin-top: 5px;"></span>
                                </button>
                                <div id="activity-action-dropdown" style="display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 200px; max-height: 300px; overflow-y: auto; margin-top: 4px;">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                            <div style="position: relative; display: flex; align-items: center;">
                                <label for="activity-actor-filter" style="margin-right: 8px; margin-bottom: 0; min-width: 60px;"><strong>Actor:</strong></label>
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

                    <div id="activity-log-viewer" style="background: #fff; min-height: 400px; max-height: 600px; overflow-y: auto; position: relative;">
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
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Log Viewer</h2>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <div>
                                <label for="log-type-selector" style="margin-right: 8px;"><strong>Type:</strong></label>
                                <select id="log-type-selector" class="regular-text" style="width: auto;">
                                    <option value="">Loading...</option>
                                </select>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
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
                    </div>
                    <div id="log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
                        <span style="color: #888;">Select a log type to view logs...</span>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Files -->
            <div class="watchtower-tab-content" id="tab-files" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 20px; min-height: 40px;">
                        <div style="display: flex; align-items: baseline; gap: 15px; flex: 1; min-width: 0;">
                            <h2 style="margin: 0; flex-shrink: 0;">File Browser</h2>
                            <div id="file-path-breadcrumb" style="font-size: 14px; color: #646970; word-wrap: break-word; overflow-wrap: break-word;">
                                /
                            </div>
                        </div>
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <button type="button" class="button button-primary" id="new-file-btn">
                                <span class="dashicons dashicons-plus-alt"></span> File
                            </button>
                            <button type="button" class="button button-primary" id="new-directory-btn">
                                <span class="dashicons dashicons-plus-alt"></span> Directory
                            </button>
                        </div>
                    </div>
                    <div id="file-tree-loading" style="text-align: center; padding: 40px;">
                        <div class="watchtower-spinner" style="margin: 0 auto 15px;"></div>
                        <p>Loading files...</p>
                    </div>
                    <div id="file-tree-container" style="display: none;">
                        <div id="file-tree" class="file-tree">
                        </div>
                        <div style="margin-top: 20px;">
                            <div id="file-stats" style="color: #666; font-size: 13px; display: flex; justify-content: space-between;">
                                <span style="padding-left: 2px;">Size: <span id="file-total-size">0 B</span></span>
                                <span style="padding-right: 2px;">Total: <span id="file-count">0</span> items</span>
                            </div>
                        </div>
                    </div>
                    <div id="file-tree-error" style="display: none; background: #fff; padding: 40px; border: 1px solid #ccd0d4; border-radius: 8px; text-align: center;">
                        <span class="dashicons dashicons-warning" style="font-size: 64px; width: 64px; height: 64px; color: #d63638; margin-bottom: 15px;"></span>
                        <h3>Unable to Load Files</h3>
                        <p style="color: #646970;" id="file-tree-error-message">An error occurred while loading files.</p>
                    </div>
                </div>
            </div>

            <!-- File Editor Modal -->
            <div id="file-editor-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 0; border-radius: 8px; width: 90%; max-width: 1200px; height: 90vh; max-height: 90vh; box-shadow: 0 10px 40px rgba(0,0,0,0.3); display: flex; flex-direction: column;">
                    <div style="padding: 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                        <div>
                            <h2 id="file-editor-title" style="margin: 0;">Edit File</h2>
                            <div id="file-editor-meta" style="color: #646970; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                        <button id="file-editor-close" class="button" style="padding: 4px 12px;">
                            <span class="dashicons dashicons-no-alt" style="margin-top: 3px;"></span>
                        </button>
                    </div>
                    <div style="flex: 1; min-height: 0; padding: 0; display: flex; flex-direction: column;">
                        <textarea id="file-editor-content" style="width: 100%; flex: 1; font-family: monospace; font-size: 13px; padding: 10px; border: none; resize: none;"></textarea>
                    </div>
                    <div style="padding: 20px; border-top: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                        <div id="file-editor-status" style="color: #646970; font-size: 13px;"></div>
                        <div style="display: flex; gap: 10px;">
                            <button id="file-editor-cancel" class="button">Cancel</button>
                            <button id="file-editor-save" class="button button-primary">Save</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Actions -->
            <div class="watchtower-tab-content" id="tab-actions" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Actions</h2>
                    </div>
                    <p>
                        <button class="button button-secondary watchtower-scan-btn" data-site="<?php echo esc_attr($site_url); ?>">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Scan
                        </button>
                        <?php
                        $debug_enabled = false;
                        if (isset($agent['constants']) && isset($agent['constants']['WP_DEBUG_LOG'])) {
                            $debug_enabled = $agent['constants']['WP_DEBUG_LOG'];
                        }
                        ?>
                        <button class="button watchtower-toggle-debug-btn" data-site="<?php echo esc_attr($site_url); ?>" data-debug-enabled="<?php echo $debug_enabled ? '1' : '0'; ?>">
                            <span class="dashicons dashicons-<?php echo $debug_enabled ? 'no' : 'yes'; ?>" style="margin-top: 3px;"></span> <?php echo $debug_enabled ? 'Disable' : 'Enable'; ?> Debug
                        </button>
                        <button class="button button-primary watchtower-update-agent-btn" data-site="<?php echo esc_attr($site_url); ?>">
                            <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Agent
                        </button>
                    </p>
                </div>
            </div>

            <!-- Tab Content: Tags -->
            <div class="watchtower-tab-content" id="tab-tags" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Tags</h2>
                    </div>
                    <div id="tags-loading" style="text-align: center; padding: 40px 20px;">
                        <div class="watchtower-spinner"></div>
                    </div>
                    <div id="tags-error" style="display: none; text-align: center; padding: 40px 20px; color: #d63638;">
                        <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px;"></span>
                        <p id="tags-error-message"></p>
                    </div>
                    <div id="tags-content" style="display: none;">
                        <div style="display: flex; gap: 8px; margin-bottom: 20px; max-width: 400px;">
                            <div class="tag-input-wrapper" style="flex: 1; position: relative;">
                                <input type="text" id="tag-input" placeholder="Enter tag name..." style="width: 100%; height: 36px; padding: 0 12px; border: 1px solid #8c8f94; border-radius: 4px; box-sizing: border-box;">
                                <div id="tag-autocomplete" class="tag-autocomplete-dropdown" style="display: none;"></div>
                            </div>
                            <button id="add-tag-btn" class="button button-primary" style="height: 36px; display: inline-flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> Add
                            </button>
                        </div>
                        <div id="tags-container" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                        <div id="tags-empty" style="display: none; text-align: center; padding: 40px 20px; color: #646970;">
                            <span class="dashicons dashicons-tag" style="font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; margin-bottom: 10px;"></span>
                            <p>No tags yet. Add tags to organize this site.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Security -->
            <div class="watchtower-tab-content" id="tab-security" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Security</h2>
                    </div>
                    <div style="text-align: center; padding: 60px 20px; color: #646970;">
                        <span class="dashicons dashicons-shield" style="font-size: 80px; width: 80px; height: 80px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;">Security Features Coming Soon</h3>
                        <p>Security monitoring and management tools will be available here.</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Performance -->
            <div class="watchtower-tab-content" id="tab-performance" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Performance</h2>
                    </div>
                    <div style="text-align: center; padding: 60px 20px; color: #646970;">
                        <span class="dashicons dashicons-performance" style="font-size: 80px; width: 80px; height: 80px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;">Performance Monitoring Coming Soon</h3>
                        <p>Performance metrics and optimization tools will be available here.</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Settings -->
            <div class="watchtower-tab-content" id="tab-settings" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Settings</h2>
                    </div>
                    <div style="text-align: center; padding: 60px 20px; color: #646970;">
                        <span class="dashicons dashicons-admin-settings" style="font-size: 80px; width: 80px; height: 80px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;">Settings Management Coming Soon</h3>
                        <p>WordPress settings configuration and management will be available here.</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Database -->
            <div class="watchtower-tab-content" id="tab-database" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Database</h2>
                    </div>
                    <div style="text-align: center; padding: 60px 20px; color: #646970;">
                        <span class="dashicons dashicons-database" style="font-size: 80px; width: 80px; height: 80px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;">Database Management Coming Soon</h3>
                        <p>Database tools and optimization features will be available here.</p>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Advanced -->
            <div class="watchtower-tab-content" id="tab-advanced" style="display: none;">
                <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; min-height: 40px;">
                        <h2 style="margin: 0;">Advanced</h2>
                    </div>
                    <div style="text-align: center; padding: 60px 20px; color: #646970;">
                        <span class="dashicons dashicons-admin-generic" style="font-size: 80px; width: 80px; height: 80px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3 style="font-size: 18px; margin-bottom: 10px;">Advanced Features Coming Soon</h3>
                        <p>Advanced configuration and management tools will be available here.</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
        </div>
    </div>

    <?php
}
}
