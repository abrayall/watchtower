<?php
/**
 * Admin Settings Class for Watchtower Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Agent_Admin_Settings {

    /**
     * Initialize admin settings
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Handle manual registration
        add_action('admin_post_watchtower_agent_register', array($this, 'handle_manual_registration'));

        // Show admin notice if manager URL not configured
        add_action('admin_notices', array($this, 'show_configuration_notice'));
    }

    /**
     * Show admin notice if manager URL is not configured
     */
    public function show_configuration_notice() {
        // Don't show on the settings page itself
        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_watchtower-agent') {
            return;
        }

        // Check if manager is installed locally (no need for remote URL)
        $manager_active = is_plugin_active('remote-manager/remote-manager.php');
        if ($manager_active) {
            return;
        }

        // Check if manager URL is configured
        $manager_url = get_option('watchtower_agent_manager_url', '');
        if (!empty($manager_url)) {
            return;
        }

        // Check if user can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show the notice
        $settings_url = admin_url('options-general.php?page=watchtower-agent');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Watchtower Agent:</strong>
                Remote manager URL has not been configured.
                <a href="<?php echo esc_url($settings_url); ?>">Configure now</a>
            </p>
        </div>
        <?php
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'WP Remote Agent Settings',
            'Remote Agent',
            'manage_options',
            'watchtower-agent',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('watchtower_agent_settings', 'watchtower_agent_manager_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ));

        register_setting('watchtower_agent_settings', 'watchtower_agent_auto_register', array(
            'type' => 'boolean',
            'default' => true,
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $manager_url = get_option('watchtower_agent_manager_url', '');
        $auto_register = get_option('watchtower_agent_auto_register', true);
        $last_registration = get_option('watchtower_agent_last_registration', '');
        $registration_status = get_option('watchtower_agent_registration_status', '');

        // Check if manager is installed locally
        $manager_local = file_exists(WP_PLUGIN_DIR . '/remote-manager/remote-manager.php');
        $manager_active = is_plugin_active('remote-manager/remote-manager.php');

        ?>
        <div class="wrap">
            <h1>Remote Agent Settings</h1>

            <?php if (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Success!</strong> Agent registered with manager successfully.</p>
                </div>
            <?php elseif (isset($_GET['registration']) && $_GET['registration'] === 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Error!</strong> Failed to register with manager. <?php echo esc_html(urldecode($_GET['message'] ?? '')); ?></p>
                </div>
            <?php endif; ?>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 20px;">
                <h2>Manager Configuration</h2>

                <?php if ($manager_local): ?>
                    <div style="padding: 12px; background: #d5f3e5; border-left: 4px solid #00a32a; margin: 20px 0;">
                        <strong>Local Manager Detected</strong><br>
                        Watchtower Manager is <?php echo $manager_active ? '<strong style="color: #00a32a;">active</strong>' : '<strong style="color: #d63638;">not active</strong>'; ?> on this WordPress instance.
                        <?php if ($manager_active): ?>
                            <br>Registration will happen automatically.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php settings_fields('watchtower_agent_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="watchtower_agent_manager_url">Manager URL</label>
                            </th>
                            <td>
                                <input type="url"
                                       id="watchtower_agent_manager_url"
                                       name="watchtower_agent_manager_url"
                                       value="<?php echo esc_attr($manager_url); ?>"
                                       class="regular-text"
                                       placeholder="https://manager.example.com"
                                       <?php echo $manager_active ? 'disabled' : ''; ?>>
                                <p class="description">
                                    <?php if ($manager_active): ?>
                                        Not needed - using local manager on this WordPress instance.
                                    <?php else: ?>
                                        Enter the URL of your Watchtower Manager installation (e.g., https://manager.example.com)
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>

                        <?php if (!$manager_active): ?>
                        <tr>
                            <th scope="row">
                                <label for="watchtower_agent_auto_register">Auto-Register</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           id="watchtower_agent_auto_register"
                                           name="watchtower_agent_auto_register"
                                           value="1"
                                           <?php checked($auto_register, true); ?>>
                                    Automatically register with manager on plugin activation
                                </label>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php if (!$manager_active): ?>
                        <?php submit_button('Save Settings'); ?>
                    <?php endif; ?>
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 20px;">
                <h2>Registration Status</h2>

                <?php if ($last_registration): ?>
                    <p>
                        <strong>Last Registration:</strong>
                        <?php echo esc_html(human_time_diff(strtotime($last_registration), current_time('timestamp'))) . ' ago'; ?>
                        (<?php echo esc_html($last_registration); ?>)
                    </p>
                <?php else: ?>
                    <p><strong>Status:</strong> Not registered yet</p>
                <?php endif; ?>

                <?php if ($registration_status): ?>
                    <p><strong>Status Message:</strong> <?php echo esc_html($registration_status); ?></p>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="watchtower_agent_register">
                    <?php wp_nonce_field('watchtower_agent_register', 'watchtower_agent_nonce'); ?>

                    <?php if ($manager_active): ?>
                        <p class="description">Registration happens automatically with the local manager.</p>
                        <?php submit_button('Re-register Now', 'secondary', 'submit', false); ?>
                    <?php elseif (empty($manager_url)): ?>
                        <p class="description" style="color: #d63638;">
                            Please configure the Manager URL above before registering.
                        </p>
                        <?php submit_button('Register with Manager', 'primary', 'submit', false, array('disabled' => 'disabled')); ?>
                    <?php else: ?>
                        <p class="description">
                            Click below to register this site with the remote manager.
                        </p>
                        <?php submit_button('Register with Manager', 'primary'); ?>
                    <?php endif; ?>
                </form>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-top: 20px;">
                <h2>Site Information</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Site URL</th>
                        <td><code><?php echo esc_html(get_site_url()); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">WordPress Version</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">PHP Version</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Agent Version</th>
                        <td><?php echo esc_html(WATCHTOWER_AGENT_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Application Password</th>
                        <td>
                            <?php
                            $password_data = get_transient('watchtower_agent_app_password');
                            if ($password_data && isset($password_data['password'])) {
                                echo '<code style="background: #f0f0f1; padding: 4px 8px; border-radius: 3px;">';
                                echo esc_html($password_data['password']);
                                echo '</code>';
                                echo '<p class="description">Available for ' . (10 - floor((time() - strtotime($password_data['created'])) / 60)) . ' more minutes</p>';
                            } else {
                                echo '<span style="color: #646970;">Generated on activation (expires after 10 minutes)</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Handle manual registration
     */
    public function handle_manual_registration() {
        // Check nonce
        if (!isset($_POST['watchtower_agent_nonce']) || !wp_verify_nonce($_POST['watchtower_agent_nonce'], 'watchtower_agent_register')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to perform this action');
        }

        // Get admin user and password
        $admin_users = get_users(array(
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ));

        if (empty($admin_users)) {
            wp_redirect(add_query_arg(array(
                'page' => 'watchtower-agent',
                'registration' => 'error',
                'message' => urlencode('No administrator user found')
            ), admin_url('options-general.php')));
            exit;
        }

        $admin_user = $admin_users[0];

        // Get or create application password
        $passwords = WP_Application_Passwords::get_user_application_passwords($admin_user->ID);
        $password = null;

        foreach ($passwords as $pass) {
            if ($pass['name'] === 'watchtower-agent') {
                // Password exists but we can't retrieve the actual password
                // So we need to check transient
                $password_data = get_transient('watchtower_agent_app_password');
                if ($password_data && isset($password_data['password'])) {
                    $password = $password_data['password'];
                }
                break;
            }
        }

        if (!$password) {
            // Create new password if not found
            $result = WP_Application_Passwords::create_new_application_password($admin_user->ID, array(
                'name' => 'watchtower-agent'
            ));

            if (is_wp_error($result)) {
                wp_redirect(add_query_arg(array(
                    'page' => 'watchtower-agent',
                    'registration' => 'error',
                    'message' => urlencode($result->get_error_message())
                ), admin_url('options-general.php')));
                exit;
            }

            list($password, $password_data) = $result;

            // Store in transient
            set_transient('watchtower_agent_app_password', array(
                'username' => $admin_user->user_login,
                'password' => $password,
                'created' => current_time('mysql')
            ), 600);
        }

        // Attempt registration
        $result = watchtower_agent_register_with_manager($admin_user->user_login, $password);

        if ($result === true) {
            update_option('watchtower_agent_last_registration', current_time('mysql'));
            update_option('watchtower_agent_registration_status', 'Successfully registered');

            wp_redirect(add_query_arg(array(
                'page' => 'watchtower-agent',
                'registration' => 'success'
            ), admin_url('options-general.php')));
        } else {
            update_option('watchtower_agent_registration_status', 'Registration failed: ' . $result);

            wp_redirect(add_query_arg(array(
                'page' => 'watchtower-agent',
                'registration' => 'error',
                'message' => urlencode($result)
            ), admin_url('options-general.php')));
        }
        exit;
    }
}
