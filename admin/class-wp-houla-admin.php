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
        // Load on our settings page + any post edit screen + dashboard + post list
        if ( $this->is_our_page( $hook ) || $this->is_post_edit( $hook ) || 'index.php' === $hook || 'edit.php' === $hook ) {
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
        if ( $this->is_our_page( $hook ) || $this->is_post_edit( $hook ) || 'index.php' === $hook ) {
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
                'currencySymbol'  => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EUR',
                'i18n'            => array(
                    'syncing'     => __( 'Syncing...', 'wp-houla' ),
                    'synced'      => __( 'Synced', 'wp-houla' ),
                    'syncError'   => __( 'Sync failed', 'wp-houla' ),
                    'confirm'     => __( 'Are you sure?', 'wp-houla' ),
                    'loading'     => __( 'Loading...', 'wp-houla' ),
                    'disconnect'  => __( 'Disconnect', 'wp-houla' ),
                    'disconnecting' => __( 'Disconnecting...', 'wp-houla' ),
                    'saveSettings'  => __( 'Save Settings', 'wp-houla' ),
                    'creatingCollections' => __( 'Creating collections...', 'wp-houla' ),
                    'collectionsCreated'  => __( ' collections created, ', 'wp-houla' ),
                    'collectionsMapped'   => __( ' mapped.', 'wp-houla' ),
                    'error'               => __( 'Error', 'wp-houla' ),
                    'networkError'        => __( 'Network error', 'wp-houla' ),
                    'shopNotActive'       => __( 'Your Hou.la shop is not activated. Please connect Stripe on Hou.la first.', 'wp-houla' ),
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
            // WooCommerce present: add under Marketing menu
            add_submenu_page(
                'woocommerce-marketing',
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

        // Category filter for product sync
        $sync_categories = array();
        if ( isset( $_POST['sync_categories'] ) && is_array( $_POST['sync_categories'] ) ) {
            $sync_categories = array_map( 'absint', $_POST['sync_categories'] );
        }

        // Custom API URL (only accepted in dev mode)
        $api_url = '';
        if ( isset( $_POST['api_url'] ) && function_exists( 'wphoula_is_dev_mode' ) && wphoula_is_dev_mode() ) {
            $api_url = esc_url_raw( trim( $_POST['api_url'] ) );
        }

        // Price adjustment
        $price_adj_type  = 'none';
        $price_adj_value = 0;
        if ( isset( $_POST['price_adjustment_type'] ) ) {
            $allowed_types = array( 'none', 'percent_up', 'percent_down', 'fixed_up', 'fixed_down' );
            $price_adj_type = sanitize_text_field( $_POST['price_adjustment_type'] );
            if ( ! in_array( $price_adj_type, $allowed_types, true ) ) {
                $price_adj_type = 'none';
            }
        }
        if ( isset( $_POST['price_adjustment_value'] ) ) {
            $price_adj_value = abs( floatval( $_POST['price_adjustment_value'] ) );
        }

        // Category -> Collection mapping
        $cat_collection_map = array();
        if ( isset( $_POST['category_collection_map'] ) && is_array( $_POST['category_collection_map'] ) ) {
            foreach ( $_POST['category_collection_map'] as $cat_id => $collection_id ) {
                $cat_id = absint( $cat_id );
                $collection_id = sanitize_text_field( $collection_id );
                if ( $cat_id > 0 && ! empty( $collection_id ) ) {
                    $cat_collection_map[ $cat_id ] = $collection_id;
                }
            }
        }

        $this->options->set_many( array(
            'auto_sync'              => $auto_sync,
            'sync_on_publish'        => $sync_on_publish,
            'debug'                  => $debug,
            'allowed_post_types'     => $allowed_post_types,
            'sync_categories'        => $sync_categories,
            'api_url'                => $api_url,
            'price_adjustment_type'  => $price_adj_type,
            'price_adjustment_value' => $price_adj_value,
            'category_collection_map' => $cat_collection_map,
        ) );

        wp_send_json_success( array( 'message' => __( 'Settings saved.', 'wp-houla' ) ) );
    }

    /**
     * AJAX: Get synced products from Hou.la API.
     */
    public function ajax_get_synced_products() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $api    = new Wp_Houla_Api();
        $result = $api->get( '/ecommerce/products?platform=woocommerce' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Enrich with WC product info
        $products = array();
        if ( is_array( $result ) ) {
            foreach ( $result as $product ) {
                $ext_id = isset( $product['externalProductId'] ) ? $product['externalProductId'] : '';
                $wc_price   = '';
                $wc_product = $ext_id ? wc_get_product( intval( $ext_id ) ) : null;
                if ( $wc_product ) {
                    $wc_price = $wc_product->get_price();
                }

                $products[] = array(
                    'id'              => $product['id'] ?? '',
                    'title'           => $product['title'] ?? '',
                    'externalId'      => $ext_id,
                    'priceCents'      => $product['priceCents'] ?? 0,
                    'currency'        => $product['currency'] ?? 'EUR',
                    'status'          => $product['status'] ?? 'draft',
                    'stockQuantity'   => $product['stockQuantity'] ?? null,
                    'trackStock'      => $product['trackStock'] ?? false,
                    'lastSyncedAt'    => $product['lastSyncedAt'] ?? null,
                    'imageUrl'        => $product['imageUrl'] ?? null,
                    'wcPrice'         => $wc_price,
                    'wcCurrency'      => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
                );
            }
        }

        wp_send_json_success( $products );
    }

    /**
     * AJAX: Get collections from Hou.la API.
     */
    public function ajax_get_collections() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $api    = new Wp_Houla_Api();
        $result = $api->get( '/ecommerce/collections' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( is_array( $result ) ? $result : array() );
    }

    /**
     * AJAX: Auto-create collections from WC categories.
     */
    public function ajax_auto_map_collections() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        if ( ! $this->auth->is_connected() ) {
            wp_send_json_error( __( 'Not connected to Hou.la. Please reconnect in the Connection tab.', 'wp-houla' ) );
        }

        // Get all WC product categories
        $product_cats = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $product_cats ) || empty( $product_cats ) ) {
            wp_send_json_error( __( 'No WooCommerce categories found.', 'wp-houla' ) );
        }

        $categories = array();
        foreach ( $product_cats as $cat ) {
            $categories[] = array(
                'id'    => (string) $cat->term_id,
                'name'  => $cat->name,
                'count' => $cat->count,
            );
        }

        $api    = new Wp_Houla_Api();
        $result = $api->post( '/ecommerce/collections/auto-map', array(
            'categories' => $categories,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        // Save the mapping to options
        $mapping = isset( $result['mapping'] ) ? $result['mapping'] : array();
        $cat_collection_map = array();
        $js_mapping         = array();
        foreach ( $mapping as $m ) {
            $cat_collection_map[ intval( $m['external_id'] ) ] = $m['collection_id'];
            $js_mapping[ $m['external_id'] ] = $m['collection_id'];
        }

        $this->options->set( 'category_collection_map', $cat_collection_map );

        wp_send_json_success( array(
            'mapping' => $js_mapping,
            'total'   => count( $mapping ),
            'created' => isset( $result['created'] ) ? $result['created'] : 0,
        ) );
    }

    // =====================================================================
    // Shop status check
    // =====================================================================

    /**
     * AJAX: Check if the Hou.la shop is activated (Stripe Connect active).
     */
    public function ajax_get_shop_status() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! $this->auth->is_connected() ) {
            wp_send_json_error( __( 'Not connected to Hou.la.', 'wp-houla' ) );
        }

        $api    = new Wp_Houla_Api();
        $result = $api->get( '/ecommerce/status' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'shopActive'     => ! empty( $result['shopActive'] ),
            'stripeStatus'   => isset( $result['stripeStatus'] ) ? $result['stripeStatus'] : 'unknown',
            'chargesEnabled' => ! empty( $result['chargesEnabled'] ),
            'payoutsEnabled' => ! empty( $result['payoutsEnabled'] ),
        ) );
    }

    // =====================================================================
    // Dashboard widget
    // =====================================================================

    /**
     * Register the Hou.la dashboard widget.
     */
    public function register_dashboard_widget() {
        if ( ! $this->auth->is_connected() ) {
            return;
        }

        $widget_title = '<img src="' . esc_url( WPHOULA_URL . 'admin/images/houla-icon.svg' ) . '" width="16" height="16" style="vertical-align:text-bottom;margin-right:4px;" alt="">'
                      . esc_html__( 'Hou.la - Short Links', 'wp-houla' );

        wp_add_dashboard_widget(
            'wphoula_dashboard_widget',
            $widget_title,
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Render the dashboard widget content.
     */
    public function render_dashboard_widget() {
        $nonce = wp_create_nonce( 'wphoula_admin' );
        ?>
        <div class="wphoula-dashboard-widget" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <div class="wphoula-dw-loading" id="wphoula-dw-loading">
                <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                <?php esc_html_e( 'Loading stats...', 'wp-houla' ); ?>
            </div>
            <div class="wphoula-dw-content" id="wphoula-dw-content" style="display:none;">
                <div class="wphoula-dw-stats">
                    <div class="wphoula-dw-stat">
                        <span class="wphoula-dw-stat-value" id="wphoula-dw-total-links">-</span>
                        <span class="wphoula-dw-stat-label"><?php esc_html_e( 'Short links', 'wp-houla' ); ?></span>
                    </div>
                    <div class="wphoula-dw-stat">
                        <span class="wphoula-dw-stat-value" id="wphoula-dw-total-clicks">-</span>
                        <span class="wphoula-dw-stat-label"><?php esc_html_e( 'Total clicks', 'wp-houla' ); ?></span>
                    </div>
                    <div class="wphoula-dw-stat">
                        <span class="wphoula-dw-stat-value" id="wphoula-dw-clicks-today">-</span>
                        <span class="wphoula-dw-stat-label"><?php esc_html_e( 'Clicks today', 'wp-houla' ); ?></span>
                    </div>
                </div>
                <div class="wphoula-dw-chart" id="wphoula-dw-chart" style="display:none;margin-top:12px;">
                    <p class="wphoula-chart-label"><?php esc_html_e( '7-day performance', 'wp-houla' ); ?></p>
                    <canvas id="wphoula-dw-sparkline" width="100%" height="60"></canvas>
                </div>
                <div class="wphoula-dw-top" id="wphoula-dw-top" style="display:none;margin-top:12px;">
                    <p class="wphoula-chart-label"><?php esc_html_e( 'Top links', 'wp-houla' ); ?></p>
                    <table class="wphoula-dw-table" id="wphoula-dw-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="wphoula-dw-footer" style="margin-top:12px;text-align:right;">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-houla' ) ); ?>" class="button button-small">
                    <?php esc_html_e( 'Settings', 'wp-houla' ); ?>
                </a>
                <a href="https://hou.la/manager" target="_blank" class="button button-primary button-small" style="margin-left:4px;">
                    <?php esc_html_e( 'Open Hou.la', 'wp-houla' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Get dashboard widget stats.
     * Fetches recent links created by this WP site.
     */
    public function ajax_dashboard_stats() {
        check_ajax_referer( 'wphoula_admin', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $api = new Wp_Houla_Api();

        // Get links created by WordPress (sorted by clicks)
        $links = $api->get( '/manager/link', array(
            'page'   => '1',
            'limit'  => '5',
            'sort'   => 'hitsCount,DESC',
            'filter' => 'createdByType||eq||wordpress',
        ) );

        if ( is_wp_error( $links ) ) {
            wp_send_json_error( $links->get_error_message() );
        }

        $total_links  = isset( $links['total'] ) ? (int) $links['total'] : 0;
        $total_clicks = 0;
        $top_links    = array();

        $data = isset( $links['data'] ) ? $links['data'] : array();
        foreach ( $data as $link ) {
            $clicks = isset( $link['hitsCount'] ) ? (int) $link['hitsCount'] : 0;
            $total_clicks += $clicks;
            $top_links[] = array(
                'title'    => isset( $link['title'] ) ? $link['title'] : $link['key'],
                'shortUrl' => isset( $link['shortUrl'] ) ? $link['shortUrl'] : '',
                'clicks'   => $clicks,
            );
        }

        // Also get today's clicks from byDay data (if available)
        // For now, return totals from the filtered links response
        wp_send_json_success( array(
            'totalLinks'  => $total_links,
            'totalClicks' => $total_clicks,
            'clicksToday' => 0, // Would require separate hit/export call
            'topLinks'    => $top_links,
        ) );
    }

    // =====================================================================
    // Posts list column
    // =====================================================================

    /**
     * Add Hou.la column header to posts list.
     *
     * @param array $columns
     * @return array
     */
    public function add_posts_column( $columns ) {
        if ( ! $this->auth->is_connected() ) {
            return $columns;
        }

        $columns['wphoula_shortlink'] = '<img src="' . esc_url( WPHOULA_URL . 'admin/images/houla-icon.svg' ) . '" width="14" height="14" style="vertical-align:text-bottom;margin-right:2px;" alt=""> '
            . esc_html__( 'Short link', 'wp-houla' );

        return $columns;
    }

    /**
     * Render the Hou.la column content for each post.
     *
     * @param string $column_name
     * @param int    $post_id
     */
    public function render_posts_column( $column_name, $post_id ) {
        if ( 'wphoula_shortlink' !== $column_name ) {
            return;
        }

        $shortlink = get_post_meta( $post_id, '_wphoula_shortlink', true );
        $clicks    = 0;
        $link_id   = get_post_meta( $post_id, '_wphoula_link_id', true );

        if ( ! empty( $shortlink ) ) {
            echo '<div class="wphoula-col-link">';
            echo '<a href="' . esc_url( $shortlink ) . '" target="_blank" class="wphoula-col-url" title="' . esc_attr( $shortlink ) . '">';
            // Show only the path part
            $parts = wp_parse_url( $shortlink );
            echo esc_html( isset( $parts['host'] ) ? $parts['host'] : '' );
            echo esc_html( isset( $parts['path'] ) ? $parts['path'] : '' );
            echo '</a>';
            echo '</div>';
        } else {
            echo '<span class="wphoula-col-none">-</span>';
        }
    }

    /**
     * Make the Hou.la column sortable (by post meta).
     *
     * @param array $columns
     * @return array
     */
    public function sortable_posts_column( $columns ) {
        $columns['wphoula_shortlink'] = 'wphoula_shortlink';
        return $columns;
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
        return in_array( $hook, array(
            'woocommerce_page_wp-houla',
            'woocommerce-marketing_page_wp-houla',
            'marketing_page_wp-houla',
            'settings_page_wp-houla',
            'toplevel_page_wp-houla',
        ), true );
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
