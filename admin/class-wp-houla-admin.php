<?php
/**
 * Admin area: menu page, settings, styles, scripts.
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/admin
 */

class Wp_Houla_Admin {

    /** @var Wp_Houla_Options */
    private $options;

    /** @var Wp_Houla_Auth */
    private $auth;

    /** @var string Plugin version for cache busting. */
    private $version;

    public function __construct() {
        $this->options = new Wp_Houla_Options();
        $this->auth    = new Wp_Houla_Auth();
        $this->version = WPHOULA_VERSION;
    }

    // =====================================================================
    // Enqueue
    // =====================================================================

    /**
     * Enqueue admin CSS.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_styles( $hook ) {
        // Load on our settings page + any post edit screen
        if ( $this->is_our_page( $hook ) || $this->is_post_edit( $hook ) ) {
            wp_enqueue_style(
                'wp-houla-admin',
                WPHOULA_URL . 'admin/css/wp-houla-admin.css',
                array(),
                $this->version,
                'all'
            );
        }
    }

    /**
     * Enqueue admin JS.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_scripts( $hook ) {
        if ( $this->is_our_page( $hook ) || $this->is_post_edit( $hook ) ) {
            wp_enqueue_script(
                'wp-houla-admin',
                WPHOULA_URL . 'admin/js/wp-houla-admin.js',
                array( 'jquery' ),
                $this->version,
                true
            );

            wp_localize_script( 'wp-houla-admin', 'wphoulaAdmin', array(
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'wphoula_admin' ),
                'metaboxNonce'    => wp_create_nonce( 'wphoula_metabox' ),
                'isConnected'     => $this->auth->is_connected(),
                'i18n'            => array(
                    'syncing'     => __( 'Syncing...', 'wp-houla' ),
                    'synced'      => __( 'Synced', 'wp-houla' ),
                    'syncError'   => __( 'Sync failed', 'wp-houla' ),
                    'confirm'     => __( 'Are you sure?', 'wp-houla' ),
                    'loading'     => __( 'Loading...', 'wp-houla' ),
                    'disconnect'  => __( 'Disconnect', 'wp-houla' ),
                    'disconnecting' => __( 'Disconnecting...', 'wp-houla' ),
                    'saveSettings'  => __( 'Save Settings', 'wp-houla' ),
                ),
            ) );
        }
    }

    // =====================================================================
    // Menu
    // =====================================================================

    /**
     * Register the settings page under the WooCommerce menu.
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __( 'Hou.la Settings', 'wp-houla' ),
            __( 'Hou.la', 'wp-houla' ),
            'manage_woocommerce',
            'wp-houla',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Add a "Settings" link in the plugin list.
     *
     * @param array $links Existing action links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=wp-houla' ),
            __( 'Settings', 'wp-houla' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    // =====================================================================
    // Settings notice
    // =====================================================================

    /**
     * Display admin notice if not connected.
     */
    public function display_settings_notice() {
        if ( $this->auth->is_connected() ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'woocommerce_page_wp-houla' ), true ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__( 'WP-Houla: Connect your Hou.la account to start syncing products.', 'wp-houla' ),
            esc_url( admin_url( 'admin.php?page=wp-houla' ) ),
            esc_html__( 'Connect now', 'wp-houla' )
        );
    }

    // =====================================================================
    // AJAX handlers
    // =====================================================================

    /**
     * AJAX: Disconnect from Hou.la.
     */
    public function ajax_disconnect() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $this->auth->disconnect();

        wp_send_json_success( array( 'message' => __( 'Disconnected from Hou.la.', 'wp-houla' ) ) );
    }

    /**
     * AJAX: Trigger batch sync.
     */
    public function ajax_batch_sync() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $sync   = new Wp_Houla_Sync();
        $result = $sync->batch_sync();

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $auto_sync       = ! empty( $_POST['auto_sync'] );
        $sync_on_publish = ! empty( $_POST['sync_on_publish'] );
        $debug           = ! empty( $_POST['debug'] );

        $this->options->set_many( array(
            'auto_sync'       => $auto_sync,
            'sync_on_publish' => $sync_on_publish,
            'debug'           => $debug,
        ) );

        wp_send_json_success( array( 'message' => __( 'Settings saved.', 'wp-houla' ) ) );
    }

    // =====================================================================
    // Settings page render
    // =====================================================================

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $options = $this->options;
        $auth    = $this->auth;
        include plugin_dir_path( __FILE__ ) . 'partials/settings-page.php';
    }

    // =====================================================================
    // Utilities
    // =====================================================================

    /**
     * Check if the current admin page is our settings page.
     *
     * @param string $hook
     * @return bool
     */
    private function is_our_page( $hook ) {
        return 'woocommerce_page_wp-houla' === $hook;
    }

    /**
     * Check if the current admin page is a post/page/cpt edit page.
     *
     * @param string $hook
     * @return bool
     */
    private function is_post_edit( $hook ) {
        return in_array( $hook, array( 'post.php', 'post-new.php' ), true );
    }
}
