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
     * Register the settings page in the admin menu.
     */
    public function add_menu_page() {
        // Base64-encoded Hou.la logo for the admin menu icon.
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 466 502" fill="none">'
            . '<path fill-rule="evenodd" fill="black" d="M425.746,225.447 L382.137,276.875 L318.859,223.219 L365.715,167.961 C390.969,138.18 387.887,115.985 367.04,98.308 C346.193,80.63 321.114,78.361 295.861,108.142 L234.489,180.519 C209.235,210.3 214.812,235.568 235.659,253.245 C244.437,260.689 253.656,264.685 263.271,264.613 L209.085,328.514 C200.384,323.451 191.705,317.28 183.083,309.969 C118.755,255.421 115.937,187.502 172.505,120.792 L226.807,56.754 C283.374,-9.956 350.841,-18.277 415.168,36.27 C479.496,90.818 482.314,158.737 425.746,225.447 ZM140.185,293.353 L105.117,335.382 C79.391,365.721 83.478,387.962 104.716,405.971 C125.953,423.979 148.563,424.376 174.289,394.038 L310.804,233.046 L373.908,286.556 L240.996,443.3 C183.369,511.259 116.46,521.28 50.928,465.711 C-14.603,410.143 -15.653,342.496 41.973,274.537 L124.624,177.718 C94.04,237.107 140.185,293.353 140.185,293.353 ZM442.239,406.313 C464.678,425.341 467.444,458.957 448.416,481.397 C429.388,503.836 395.772,506.602 373.333,487.574 C350.893,468.546 348.127,434.93 367.155,412.49 C386.183,390.051 419.799,387.285 442.239,406.313 Z"/>'
            . '</svg>'
        );

        if ( wphoula_is_woocommerce_active() ) {
            // WooCommerce present: add under WooCommerce menu
            add_submenu_page(
                'woocommerce',
                __( 'Hou.la Settings', 'wp-houla' ),
                __( 'Hou.la', 'wp-houla' ),
                'manage_woocommerce',
                'wp-houla',
                array( $this, 'render_settings_page' )
            );
        } else {
            // Standalone mode: top-level menu with custom icon
            add_menu_page(
                __( 'Hou.la Settings', 'wp-houla' ),
                __( 'Hou.la', 'wp-houla' ),
                'manage_options',
                'wp-houla',
                array( $this, 'render_settings_page' ),
                $icon_svg,
                81 // Position after Settings (80)
            );
        }
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
        $allowed_screens = array( 'plugins', 'woocommerce_page_wp-houla', 'settings_page_wp-houla', 'toplevel_page_wp-houla' );
        if ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
            esc_html__( 'WP-Houla: Connect your Hou.la account to enable short links and QR codes.', 'wp-houla' ),
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

        if ( ! current_user_can( 'manage_options' ) ) {
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

        if ( ! current_user_can( 'manage_options' ) ) {
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

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $auto_sync       = ! empty( $_POST['auto_sync'] );
        $sync_on_publish = ! empty( $_POST['sync_on_publish'] );
        $debug           = ! empty( $_POST['debug'] );

        // Post types selection
        $allowed_post_types = array();
        if ( isset( $_POST['allowed_post_types'] ) && is_array( $_POST['allowed_post_types'] ) ) {
            $allowed_post_types = array_map( 'sanitize_key', $_POST['allowed_post_types'] );
        }

        $this->options->set_many( array(
            'auto_sync'          => $auto_sync,
            'sync_on_publish'    => $sync_on_publish,
            'debug'              => $debug,
            'allowed_post_types' => $allowed_post_types,
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
        return in_array( $hook, array( 'woocommerce_page_wp-houla', 'settings_page_wp-houla', 'toplevel_page_wp-houla' ), true );
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
