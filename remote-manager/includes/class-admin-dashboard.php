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
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Enqueue admin styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Handle AJAX actions
        add_action('wp_ajax_watchtower_manager_remove_agent', array($this, 'ajax_remove_agent'));
        add_action('wp_ajax_watchtower_manager_update_agent', array($this, 'ajax_update_agent'));
        add_action('wp_ajax_watchtower_manager_scan_agent', array($this, 'ajax_scan_agent'));
        add_action('wp_ajax_watchtower_manager_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_watchtower_manager_get_available_logs', array($this, 'ajax_get_available_logs'));
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

        // Add submenu items
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

        // Hidden submenu for site details (not shown in menu)
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
        // Only load on our admin pages
        if (strpos($hook, 'watchtower-manager') === false) {
            return;
        }

        // Enqueue WordPress default styles
        wp_enqueue_style('wp-color-picker');

        // Add inline CSS
        wp_add_inline_style('wp-admin', $this->get_admin_css());
    }

    /**
     * Get admin CSS
     */
    private function get_admin_css() {
        return '
            .watchtower-manager-dashboard {
                margin: 20px 0;
            }
            .watchtower-manager-dashboard .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            .watchtower-manager-dashboard .stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 2px 4px rgba(0,0,0,.06);
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            .watchtower-manager-dashboard .stat-card.filter-card {
                cursor: pointer;
            }
            .watchtower-manager-dashboard .stat-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 6px 12px rgba(0,0,0,.12);
            }
            .watchtower-manager-dashboard .stat-card.filter-active {
                border: 2px solid #2271b1;
                box-shadow: 0 4px 8px rgba(34, 113, 177, 0.2);
            }
            .watchtower-manager-dashboard .stat-card::before {
                content: \'\';
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
            }
            .watchtower-manager-dashboard .stat-card.stat-total::before {
                background: linear-gradient(180deg, #2271b1, #135e96);
            }
            .watchtower-manager-dashboard .stat-card.stat-total {
                background: linear-gradient(135deg, #ffffff 0%, #f0f6fc 100%);
            }
            /* Healthy card - Green when majority */
            .watchtower-manager-dashboard .stat-card.stat-healthy-good::before {
                background: linear-gradient(180deg, #00a32a, #007a20);
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-good {
                background: linear-gradient(135deg, #ffffff 0%, #f0f9f3 100%);
            }
            /* Healthy card - Yellow when minority */
            .watchtower-manager-dashboard .stat-card.stat-healthy-warning::before {
                background: linear-gradient(180deg, #dba617, #b98900);
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-warning {
                background: linear-gradient(135deg, #ffffff 0%, #fffbf0 100%);
            }
            /* Healthy card - Red when 0 */
            .watchtower-manager-dashboard .stat-card.stat-healthy-critical::before {
                background: linear-gradient(180deg, #d63638, #b91c1c);
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-critical {
                background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
            }
            /* Unhealthy card - Green when 0 */
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-none::before {
                background: linear-gradient(180deg, #00a32a, #007a20);
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-none {
                background: linear-gradient(135deg, #ffffff 0%, #f0f9f3 100%);
            }
            /* Unhealthy card - Yellow when minority */
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-warning::before {
                background: linear-gradient(180deg, #dba617, #b98900);
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-warning {
                background: linear-gradient(135deg, #ffffff 0%, #fffbf0 100%);
            }
            /* Unhealthy card - Red when majority */
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-critical::before {
                background: linear-gradient(180deg, #d63638, #b91c1c);
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-critical {
                background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
            }
            .watchtower-manager-dashboard .stat-card h3 {
                margin: 0 0 12px 0;
                font-size: 13px;
                color: #646970;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .watchtower-manager-dashboard .stat-card h3 .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
                opacity: 0.4;
            }
            .watchtower-manager-dashboard .stat-card.stat-total h3 .dashicons {
                color: #2271b1;
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-good h3 .dashicons {
                color: #00a32a;
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-warning h3 .dashicons {
                color: #dba617;
            }
            .watchtower-manager-dashboard .stat-card.stat-healthy-critical h3 .dashicons {
                color: #d63638;
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-none h3 .dashicons {
                color: #00a32a;
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-warning h3 .dashicons {
                color: #dba617;
            }
            .watchtower-manager-dashboard .stat-card.stat-unhealthy-critical h3 .dashicons {
                color: #d63638;
            }
            .watchtower-manager-dashboard .stat-card .stat-value {
                font-size: 42px;
                font-weight: 700;
                color: #1d2327;
                line-height: 1;
            }
            .watchtower-manager-dashboard .sites-table {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                display: none; /* Hidden by default, shown on desktop only */
            }
            .watchtower-manager-dashboard .sites-table table {
                width: 100%;
                border-collapse: collapse;
            }
            .watchtower-manager-dashboard .sites-table th {
                background: #f6f7f7;
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ccd0d4;
                font-weight: 600;
                color: #1d2327;
            }
            .watchtower-manager-dashboard .sites-table td {
                padding: 12px;
                border-bottom: 1px solid #f0f0f1;
            }
            .watchtower-manager-dashboard .sites-table tr:last-child td {
                border-bottom: none;
            }
            .watchtower-manager-dashboard .sites-table tr.clickable-row {
                cursor: pointer;
            }
            .watchtower-manager-dashboard .sites-table tr.clickable-row:hover {
                background: #f6f7f7;
            }
            .watchtower-manager-dashboard .sites-table tr:hover {
                background: #f6f7f7;
            }
            .watchtower-manager-dashboard .site-url {
                font-weight: 600;
                color: #2271b1;
            }
            .watchtower-manager-dashboard .site-url a {
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .watchtower-manager-dashboard .site-url a:hover strong {
                text-decoration: underline;
            }
            .watchtower-manager-dashboard .site-url a:focus {
                outline: none;
                box-shadow: none;
            }
            .watchtower-manager-dashboard .site-icon {
                width: 24px;
                height: 24px;
                border-radius: 3px;
                flex-shrink: 0;
                text-decoration: none !important;
            }
            .watchtower-manager-dashboard .site-icon-placeholder {
                width: 24px;
                height: 24px;
                background: #f0f0f1;
                border-radius: 3px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                text-decoration: none !important;
            }
            .watchtower-manager-dashboard .site-icon-placeholder .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                color: #8c8f94;
            }
            .watchtower-manager-dashboard .badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .watchtower-manager-dashboard .badge-success {
                background: #d5f3e5;
                color: #00a32a;
            }
            .watchtower-manager-dashboard .badge-warning {
                background: #fcf0d2;
                color: #996800;
            }
            .watchtower-manager-dashboard .badge-error {
                background: #fcdddd;
                color: #d63638;
            }
            .watchtower-manager-dashboard .health-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }
            .watchtower-manager-dashboard .health-badge .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .watchtower-manager-dashboard .health-badge-healthy {
                background: #d5f3e5;
                color: #00a32a;
            }
            .watchtower-manager-dashboard .health-badge-warning {
                background: #fcf0d2;
                color: #dba617;
            }
            .watchtower-manager-dashboard .health-badge-critical {
                background: #fcdddd;
                color: #d63638;
            }
            .watchtower-manager-dashboard .health-badge-unknown {
                background: #f0f0f1;
                color: #646970;
            }
            .watchtower-manager-dashboard .actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .watchtower-manager-dashboard .button-small {
                padding: 4px 8px;
                font-size: 12px;
                height: auto;
            }
            .watchtower-manager-dashboard .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #646970;
            }
            .watchtower-manager-dashboard .empty-state .dashicons {
                font-size: 80px;
                width: 80px;
                height: 80px;
                color: #c3c4c7;
                margin-bottom: 20px;
            }
            .watchtower-manager-dashboard .empty-state h3 {
                font-size: 18px;
                margin-bottom: 10px;
            }

            /* Mobile Sites Grid - Shown by default, hidden on desktop */
            .watchtower-manager-dashboard .mobile-sites-grid {
                display: block;
            }

            /* Mobile site tile styles - always applied when tiles are visible */
            .mobile-site-tile {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 16px;
                cursor: pointer;
                transition: all 0.2s;
            }

            .mobile-site-tile:hover {
                background: #f6f7f7;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .mobile-site-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 12px;
                gap: 12px;
            }

            .mobile-site-title {
                flex: 1;
                min-width: 0;
            }

            .mobile-site-title a {
                display: flex;
                align-items: center;
                gap: 10px;
                text-decoration: none;
                color: #2271b1;
            }

            .mobile-site-title a:focus {
                outline: none;
                box-shadow: none;
            }

            .mobile-site-title strong {
                font-size: 16px;
                color: #1d2327;
                word-break: break-word;
            }

            .mobile-site-health {
                flex-shrink: 0;
            }

            .mobile-site-info {
                padding: 12px 0;
                border-top: 1px solid #f0f0f1;
                border-bottom: 1px solid #f0f0f1;
                margin-bottom: 12px;
            }

            .mobile-site-meta {
                font-size: 12px;
                color: #646970;
                margin-bottom: 6px;
                word-break: break-word;
            }

            .mobile-site-versions {
                font-size: 12px;
                color: #646970;
                margin-bottom: 6px;
            }

            .mobile-site-scanned {
                font-size: 12px;
                color: #646970;
            }

            .mobile-site-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .mobile-site-actions .button {
                flex: 1;
                min-width: 80px;
                text-align: center;
            }

            /* Desktop layout - show table and stats, hide mobile grid */
            @media (min-width: 783px) {
                .watchtower-manager-dashboard .stats-grid {
                    display: grid;
                }
                .watchtower-manager-dashboard .sites-table {
                    display: block;
                }
                .watchtower-manager-dashboard .mobile-sites-grid {
                    display: none;
                }
            }

            /* Site Details Page Styles */
            .health-status-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 25px 30px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,.05);
                display: flex;
                align-items: flex-start;
                gap: 25px;
            }
            .health-status-card.healthy {
                border-left: 4px solid #00a32a;
            }
            .health-status-card.warning {
                border-left: 4px solid #dba617;
            }
            .health-status-card.critical {
                border-left: 4px solid #d63638;
            }
            .health-status-icon {
                font-size: 64px;
                width: 64px;
                height: 64px;
                flex-shrink: 0;
            }
            .health-status-icon.healthy {
                color: #00a32a;
            }
            .health-status-icon.warning {
                color: #dba617;
            }
            .health-status-icon.critical {
                color: #d63638;
            }
            .health-status-content {
                flex: 1;
                display: flex;
                gap: 20px;
            }
            .health-status-info {
                flex: 1;
            }
            .health-status-actions {
                display: flex;
                flex-direction: row;
                gap: 10px;
                align-items: flex-start;
            }
            .health-status-title {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 12px;
                color: #2271b1;
            }
            .health-status-title > div {
                margin-top: 8px;
            }
            .health-status-subtitle {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 20px;
            }
            .health-status-subtitle.healthy {
                color: #00a32a;
            }
            .health-status-subtitle.warning {
                color: #dba617;
            }
            .health-status-subtitle.critical {
                color: #d63638;
            }
            .health-status-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 8px 16px;
                background: #2271b1;
                color: #fff;
                text-decoration: none;
                border-radius: 4px;
                font-size: 13px;
                font-weight: 500;
                transition: background-color 0.2s;
                white-space: nowrap;
                width: 120px;
            }
            .health-status-btn:hover {
                background: #135e96;
                color: #fff;
            }
            .health-status-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .health-status-meta {
                margin-top: 12px;
            }
            .health-status-meta table {
                border-collapse: collapse;
                font-size: 13px;
            }
            .health-status-meta td {
                padding: 3px 0;
            }
            .health-status-meta td:first-child {
                font-weight: 600;
                color: #1d2327;
                width: 100px;
                padding-right: 15px;
            }
            .health-status-meta td:last-child {
                color: #50575e;
            }
            .health-status-timestamp {
                color: #646970;
                font-size: 12px;
                margin-top: 10px;
            }
            .health-status-actions-mobile {
                display: none;
                gap: 10px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #f0f0f1;
            }

            /* Mobile Responsive */
            @media (max-width: 782px) {
                .health-status-card {
                    flex-direction: column;
                    align-items: flex-start;
                }
                .health-status-content {
                    flex-direction: column;
                    width: 100%;
                }
                .health-status-actions {
                    display: none;
                }
                .health-status-actions-mobile {
                    display: flex;
                    flex-direction: row;
                    flex-wrap: wrap;
                }
                .health-status-actions-mobile .health-status-btn {
                    flex: 1;
                    min-width: 140px;
                }
                .health-status-title {
                    font-size: 22px;
                }
                .health-status-icon {
                    font-size: 48px;
                    width: 48px;
                    height: 48px;
                }
            }

            .metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .metric-tile {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 2px 4px rgba(0,0,0,.06);
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            .metric-tile:hover {
                transform: translateY(-4px);
                box-shadow: 0 6px 12px rgba(0,0,0,.12);
            }
            .metric-tile::before {
                content: \'\';
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
            }
            .metric-tile.healthy::before {
                background: linear-gradient(180deg, #00a32a, #007a20);
            }
            .metric-tile.healthy {
                background: linear-gradient(135deg, #ffffff 0%, #f0f9f3 100%);
            }
            .metric-tile.warning::before {
                background: linear-gradient(180deg, #dba617, #b98900);
            }
            .metric-tile.warning {
                background: linear-gradient(135deg, #ffffff 0%, #fffbf0 100%);
            }
            .metric-tile.critical::before {
                background: linear-gradient(180deg, #d63638, #b91c1c);
            }
            .metric-tile.critical {
                background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
            }
            .metric-header {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }
            .metric-icon {
                font-size: 32px;
                width: 32px;
                height: 32px;
                margin-right: 12px;
                color: #646970;
            }
            .metric-icon.healthy {
                color: #00a32a;
            }
            .metric-icon.warning {
                color: #dba617;
            }
            .metric-icon.critical {
                color: #d63638;
            }
            .metric-title {
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
                margin: 0;
            }
            .metric-value {
                font-size: 36px;
                font-weight: 700;
                color: #1d2327;
                margin-bottom: 8px;
                line-height: 1;
            }
            .metric-subtitle {
                font-size: 12px;
                color: #646970;
                margin-bottom: 15px;
            }
            .metric-details {
                font-size: 13px;
                color: #50575e;
                line-height: 1.6;
            }
            .metric-details div {
                margin-bottom: 5px;
            }
            .progress-bar {
                width: 100%;
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                margin-top: 10px;
            }
            .progress-fill {
                height: 100%;
                border-radius: 4px;
                transition: width 0.3s ease;
            }
            .progress-fill.healthy {
                background: linear-gradient(90deg, #00a32a, #00d84a);
            }
            .progress-fill.warning {
                background: linear-gradient(90deg, #dba617, #ffb900);
            }
            .progress-fill.critical {
                background: linear-gradient(90deg, #d63638, #ff4444);
            }
        ';
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $agents = $this->storage->get_all_agents();
        $agent_count = count($agents);

        // Calculate stats and prepare agents with health status
        $healthy_count = 0;
        $unhealthy_count = 0;
        $agents_with_health = array();

        foreach ($agents as $agent) {
            // Get health data for each agent
            $health_data = $this->health_storage->get_health_data($agent['site_url']);
            $health_status = $this->determine_health_status($health_data);

            // Add health status to agent for sorting
            $agent['health_status'] = $health_status;
            $agents_with_health[] = $agent;

            if ($health_status === 'healthy') {
                $healthy_count++;
            } elseif ($health_status === 'warning' || $health_status === 'critical') {
                $unhealthy_count++;
            }
        }

        // Sort agents: unhealthy first, then alphabetically by site name/URL
        usort($agents_with_health, function($a, $b) {
            // Determine sort priority (unhealthy = 0, healthy/unknown = 1)
            $a_priority = ($a['health_status'] === 'warning' || $a['health_status'] === 'critical') ? 0 : 1;
            $b_priority = ($b['health_status'] === 'warning' || $b['health_status'] === 'critical') ? 0 : 1;

            // If different priorities, sort by priority
            if ($a_priority !== $b_priority) {
                return $a_priority - $b_priority;
            }

            // Same priority, sort alphabetically by site name or URL
            $a_name = isset($a['site_name']) ? $a['site_name'] : $a['site_url'];
            $b_name = isset($b['site_name']) ? $b['site_name'] : $b['site_url'];

            return strcasecmp($a_name, $b_name);
        });

        // Replace agents with sorted version
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
                    // Determine healthy card status (reverse of unhealthy)
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
                    // Determine unhealthy card status
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
                                            </div>
                                        </td>
                                        <td data-label="Health">
                                            <?php
                                            // Use cached health status from sorting
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

        <script>
        var currentFilter = null;

        function filterSites(filterType) {
            var $ = jQuery;

            // Toggle filter - if clicking the same filter, turn it off
            if (currentFilter === filterType) {
                currentFilter = null;
                $('.filter-card').removeClass('filter-active');
                $('.site-row').show();
                return;
            }

            // Set new filter
            currentFilter = filterType;

            // Update active state on cards
            $('.filter-card').removeClass('filter-active');
            $('.filter-card[data-filter="' + filterType + '"]').addClass('filter-active');

            // Show all if "all" filter
            if (filterType === 'all') {
                $('.site-row').show();
                return;
            }

            // Filter rows based on health status
            $('.site-row').each(function() {
                var healthStatus = $(this).data('health-status');

                if (filterType === 'healthy') {
                    if (healthStatus === 'healthy') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                } else if (filterType === 'unhealthy') {
                    if (healthStatus === 'warning' || healthStatus === 'critical') {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                }
            });
        }

        jQuery(document).ready(function($) {
            // Clickable table rows
            $('.clickable-row').on('click', function(e) {
                // Don't trigger if clicking on links or buttons
                if ($(e.target).closest('a, button').length) {
                    return;
                }

                var detailsUrl = $(this).data('details-url');
                if (detailsUrl) {
                    window.location.href = detailsUrl;
                }
            });

            // Change cursor to pointer on hover (except on links/buttons)
            $('.clickable-row').on('mouseenter', function(e) {
                if (!$(e.target).closest('a, button').length) {
                    $(this).css('cursor', 'pointer');
                }
            });

            // Scan site
            $('.scan-site').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var siteUrl = button.data('site-url');
                var originalText = button.text();

                button.text('Scanning...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'watchtower_manager_scan_agent',
                    site_url: siteUrl,
                    nonce: '<?php echo wp_create_nonce('watchtower_manager_scan'); ?>'
                }, function(response) {
                    if (response.success) {
                        button.text('Done!');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        alert('Scan failed: ' + response.data.message);
                        button.text(originalText).prop('disabled', false);
                    }
                });
            });

            // Remove site
            $('.remove-site').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var siteUrl = button.data('site-url');

                if (!confirm('Are you sure you want to remove this site from the manager?')) {
                    return;
                }

                button.text('Removing...').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'watchtower_manager_remove_agent',
                    site_url: siteUrl,
                    nonce: '<?php echo wp_create_nonce('watchtower_manager_remove'); ?>'
                }, function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            // Reload page if no more sites
                            if ($('.sites-table tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert('Failed to remove site: ' + response.data.message);
                        button.text('Remove').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings() {
        // Handle form submission
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
                                When enabled, the manager will automatically update agent plugins during health checks.
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

        // Use the health storage to check and update the agent (force update for manual trigger)
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

        // Automatically rescan the agent to get the latest version and details
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

        // Fetch and save health data (which also fetches and saves info data)
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

        // Call agent's /logs endpoint to get available logs
        $response = wp_remote_get($site_url . '/?rest_route=/watchtower-agent/v1/logs', array(
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

        // Call agent's /logs/{type} endpoint
        $response = wp_remote_get(
            $site_url . '/?rest_route=/watchtower-agent/v1/logs/' . $log_type . '&lines=' . $lines,
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
     * Determine overall health status
     */
    private function determine_health_status($health_data) {
        if (!$health_data || !isset($health_data['success']) || !$health_data['success']) {
            return 'critical';
        }

        $warnings = 0;
        $critical = 0;

        // Check memory usage
        if (isset($health_data['memory']['usage_percentage'])) {
            $usage = floatval(str_replace('%', '', $health_data['memory']['usage_percentage']));
            if ($usage >= 90) $critical++;
            elseif ($usage >= 75) $warnings++;
        }

        // Check disk usage
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

    // Fetch latest health data
    $health_data = $this->health_storage->get_health_data($site_url);
    $health_age = $this->health_storage->get_health_data_age($site_url);

    // If health data is stale or missing, fetch new data
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
                    // Memory Metric
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
                    // Disk Space Metric
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
                    // CPU Load Metric (if available)
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
                                <th>Plugin Name</th>
                                <th>Version</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort plugins alphabetically by name
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
                        <button class="button button-secondary" onclick="location.reload();">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Scan
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

    <script>
    function toggleHealthDetails(event) {
        event.preventDefault();
        var meta = document.querySelector('.health-status-meta');
        var icon = event.currentTarget.querySelector('.dashicons');

        if (meta.style.display === 'none') {
            meta.style.display = 'block';
            icon.classList.remove('dashicons-arrow-down-alt2');
            icon.classList.add('dashicons-arrow-up-alt2');
        } else {
            meta.style.display = 'none';
            icon.classList.remove('dashicons-arrow-up-alt2');
            icon.classList.add('dashicons-arrow-down-alt2');
        }
    }

    jQuery(document).ready(function($) {
        $('.watchtower-update-agent-btn').on('click', function() {
            var button = $(this);
            var siteUrl = button.data('site-url');

            if (!confirm('Are you sure you want to update the agent plugin on this site?')) {
                return;
            }

            button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; animation: rotation 2s infinite linear;"></span> Updating...');

            $.post(ajaxurl, {
                action: 'watchtower_manager_update_agent',
                site_url: siteUrl,
                nonce: '<?php echo wp_create_nonce('watchtower_manager_update_agent'); ?>'
            }, function(response) {
                if (response.success) {
                    button.html('<span class="dashicons dashicons-yes" style="margin-top: 3px;"></span> Update Successful!').css('color', '#00a32a');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    alert('Update failed: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
                }
            }).fail(function() {
                alert('Update request failed. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span> Update Remote Agent');
            });
        });

        // Tab switching (desktop)
        $('.watchtower-tab-btn').on('click', function() {
            var tab = $(this).data('tab');

            // Update active button
            $('.watchtower-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Sync mobile selector
            $('#mobile-tab-selector').val(tab);

            // Show corresponding tab content
            $('.watchtower-tab-content').hide();
            $('#tab-' + tab).show();

            // Load logs if switching to logs tab
            if (tab === 'logs' && !$('#log-type-selector').data('loaded')) {
                loadAvailableLogs();
            }
        });

        // Tab switching (mobile)
        $('#mobile-tab-selector').on('change', function() {
            var tab = $(this).val();

            // Update desktop buttons (for consistency)
            $('.watchtower-tab-btn').removeClass('active');
            $('.watchtower-tab-btn[data-tab="' + tab + '"]').addClass('active');

            // Show corresponding tab content
            $('.watchtower-tab-content').hide();
            $('#tab-' + tab).show();

            // Load logs if switching to logs tab
            if (tab === 'logs' && !$('#log-type-selector').data('loaded')) {
                loadAvailableLogs();
            }
        });

        // Load available log types
        function loadAvailableLogs() {
            $.post(ajaxurl, {
                action: 'watchtower_manager_get_available_logs',
                site_url: '<?php echo esc_js($site_url); ?>',
                nonce: '<?php echo wp_create_nonce('watchtower_manager_logs'); ?>'
            }, function(response) {
                if (response.success && response.data.logs) {
                    var selector = $('#log-type-selector');
                    selector.empty();

                    var hasReadableLogs = false;
                    response.data.logs.forEach(function(log) {
                        if (log.readable) {
                            selector.append('<option value="' + log.type + '">' +
                                log.type.charAt(0).toUpperCase() + log.type.slice(1) +
                                ' (' + formatBytes(log.size) + ')</option>');
                            hasReadableLogs = true;
                        }
                    });

                    if (!hasReadableLogs) {
                        selector.append('<option value="">No readable logs found</option>');
                        $('#log-viewer').html('<span style="color: #888;">No readable log files found on this site.</span>');
                    } else {
                        selector.data('loaded', true);
                        loadLogs();
                    }
                }
            });
        }

        // Format a single log line with syntax highlighting
        function formatLogLine(line) {
            // Escape HTML
            line = $('<div>').text(line).html();

            // Parse common log formats
            // Format: [DD-MMM-YYYY HH:MM:SS UTC] message
            var timestampMatch = line.match(/^(\[[^\]]+\])/);
            var timestamp = '';
            var message = line;

            if (timestampMatch) {
                timestamp = '<span style="color: #858585;">' + timestampMatch[1] + '</span> ';
                message = line.substring(timestampMatch[1].length).trim();
            }

            // Highlight log levels and types
            if (message.match(/PHP Fatal [Ee]rror/i)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/PHP Parse [Ee]rror/i)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/PHP Warning/i)) {
                message = '<span style="color: #ffb86c;">' + message + '</span>';
            } else if (message.match(/PHP Notice/i)) {
                message = '<span style="color: #8be9fd;">' + message + '</span>';
            } else if (message.match(/PHP Deprecated/i)) {
                message = '<span style="color: #bd93f9;">' + message + '</span>';
            } else if (message.match(/\b(ERROR|Error|error)\b/)) {
                message = '<span style="color: #ff6b6b;">' + message + '</span>';
            } else if (message.match(/\b(CRITICAL|Critical)\b/)) {
                message = '<span style="color: #ff5555; font-weight: bold;">' + message + '</span>';
            } else if (message.match(/\b(WARNING|Warning)\b/)) {
                message = '<span style="color: #f1fa8c;">' + message + '</span>';
            } else if (message.match(/\b(INFO|Info)\b/)) {
                message = '<span style="color: #50fa7b;">' + message + '</span>';
            } else if (message.match(/\b(DEBUG|Debug)\b/)) {
                message = '<span style="color: #6272a4;">' + message + '</span>';
            }

            return timestamp + message;
        }

        // Load logs
        function loadLogs() {
            var logType = $('#log-type-selector').val();
            var lines = $('#log-lines-count').val();

            if (!logType) return;

            $('#log-viewer').html('<span style="color: #888;">Loading logs...</span>');
            $('#refresh-logs-btn').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'watchtower_manager_get_logs',
                site_url: '<?php echo esc_js($site_url); ?>',
                log_type: logType,
                lines: lines,
                nonce: '<?php echo wp_create_nonce('watchtower_manager_logs'); ?>'
            }, function(response) {
                $('#refresh-logs-btn').prop('disabled', false);

                if (response.success && response.data.success) {
                    if (response.data.logs && response.data.logs.length > 0) {
                        // Format log lines with syntax highlighting
                        var formattedLogs = response.data.logs.map(function(line) {
                            return formatLogLine(line);
                        }).join('\n');
                        $('#log-viewer').html(formattedLogs);
                        // Scroll to bottom
                        $('#log-viewer').scrollTop($('#log-viewer')[0].scrollHeight);
                    } else {
                        $('#log-viewer').html('<span style="color: #888;">No log entries found.</span>');
                    }
                } else {
                    $('#log-viewer').html('<span style="color: #ff6b6b;">Error: ' +
                        (response.data.error || 'Failed to load logs') + '</span>');
                }
            }).fail(function() {
                $('#refresh-logs-btn').prop('disabled', false);
                $('#log-viewer').html('<span style="color: #ff6b6b;">Failed to load logs. Please try again.</span>');
            });
        }

        // Format bytes to human readable
        function formatBytes(bytes) {
            if (bytes === 0 || bytes === null) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Event handlers for log controls
        $('#log-type-selector').on('change', loadLogs);
        $('#log-lines-count').on('change', loadLogs);
        $('#refresh-logs-btn').on('click', loadLogs);
    });
    </script>
    <style>
    @keyframes rotation {
        from { transform: rotate(0deg); }
        to { transform: rotate(359deg); }
    }

    /* Tab Navigation */
    .watchtower-tabs {
        display: flex;
        gap: 0;
        margin: 20px 0 0 0;
        border-bottom: 1px solid #ccd0d4;
    }

    .watchtower-tab-btn {
        background: #f6f7f7;
        border: 1px solid #ccd0d4;
        border-bottom: none;
        padding: 12px 24px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: #50575e;
        transition: all 0.2s;
        border-radius: 8px 8px 0 0;
        margin-right: -1px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .watchtower-tab-btn .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }

    .watchtower-tab-btn:hover {
        background: #fff;
        color: #2271b1;
    }

    .watchtower-tab-btn.active {
        background: #fff;
        color: #2271b1;
        border-bottom: 2px solid #fff;
        margin-bottom: -1px;
        font-weight: 600;
    }

    .watchtower-tab-content {
        margin-top: 0;
    }

    #tab-overview {
        margin-top: 20px;
    }

    #tab-plugins {
        margin-top: 20px;
    }

    #tab-logs {
        margin-top: 20px;
    }

    #tab-actions {
        margin-top: 20px;
    }

    /* Log Viewer Scrollbar */
    #log-viewer::-webkit-scrollbar {
        width: 10px;
    }

    #log-viewer::-webkit-scrollbar-track {
        background: #2d2d2d;
    }

    #log-viewer::-webkit-scrollbar-thumb {
        background: #555;
        border-radius: 5px;
    }

    #log-viewer::-webkit-scrollbar-thumb:hover {
        background: #777;
    }

    /* Stack action buttons on narrower screens */
    @media (max-width: 1400px) {
        .watchtower-manager-dashboard .actions {
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }
        .watchtower-manager-dashboard .actions .button-small {
            width: 100%;
        }
    }

    /* Mobile responsive tabs */
    @media (max-width: 782px) {
        /* Mobile stats grid - compact horizontal cards */
        .watchtower-manager-dashboard .stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-bottom: 16px;
        }

        .watchtower-manager-dashboard .stat-card {
            padding: 6px 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: auto;
            border-radius: 4px;
        }

        .watchtower-manager-dashboard .stat-card h3 {
            font-size: 9px;
            margin: 0 0 2px 0;
            flex-direction: column;
            gap: 1px;
            line-height: 1.2;
        }

        .watchtower-manager-dashboard .stat-card h3 .dashicons {
            font-size: 12px;
            width: 12px;
            height: 12px;
            margin: 0;
        }

        .watchtower-manager-dashboard .stat-card .stat-value {
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
        }

        .watchtower-manager-dashboard .stat-card::before {
            display: none;
        }

        /* Hide desktop tab buttons on mobile */
        .watchtower-tabs {
            display: none !important;
        }

        /* Show mobile tab selector */
        .watchtower-mobile-tab-selector {
            display: block !important;
        }

        #tab-logs > div > div:first-child {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 15px !important;
        }

        #tab-logs > div > div:first-child h2 {
            width: 100%;
        }

        #tab-logs > div > div:first-child > div {
            width: 100%;
            flex-direction: row !important;
            gap: 8px !important;
            flex-wrap: nowrap !important;
        }

        #tab-logs > div > div:first-child > div > div {
            flex: 1;
            min-width: 0;
        }

        #tab-logs > div > div:first-child label {
            display: none;
        }

        #tab-logs > div > div:first-child select {
            width: 100%;
        }

        #tab-logs > div > div:first-child button {
            width: auto;
            min-width: 40px;
            flex-shrink: 0;
        }
    }
    </style>
    <?php
}
}
