<?php
/**
 * Admin settings page template.
 *
 * Available variables:
 *   $options - Wp_Houla_Options instance
 *   $auth    - Wp_Houla_Auth instance
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_connected   = $auth->is_connected();
$workspace_name = $options->get( 'workspace_name' );
$user_email     = $options->get( 'user_email' );
$auto_sync      = $options->get( 'auto_sync' );
$sync_on_publish = $options->get( 'sync_on_publish' );
$debug          = $options->get( 'debug' );
$products_synced = $options->get( 'products_synced' );
$last_full_sync  = $options->get( 'last_full_sync' );
$orders_received = $options->get( 'orders_received' );
$last_order_at   = $options->get( 'last_order_at' );
?>

<div class="wrap wphoula-settings">
    <h1 class="wphoula-page-title">
        <img src="<?php echo esc_url( WPHOULA_URL . 'admin/images/houla-icon.svg' ); ?>" alt="Hou.la" class="wphoula-logo-full">
    </h1>

    <?php if ( isset( $_GET['wphoula_connected'] ) && $_GET['wphoula_connected'] === '1' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Successfully connected to Hou.la!', 'wp-houla' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( function_exists( 'wphoula_is_dev_mode' ) && wphoula_is_dev_mode() ) : ?>
        <div class="notice notice-warning" style="border-left-color: #d63638; background: #fff2f2;">
            <p>
                <strong style="color: #d63638;">&#9888; DEV MODE</strong> &mdash;
                <?php
                printf(
                    esc_html__( 'API URL: %s', 'wp-houla' ),
                    '<code>' . esc_html( wphoula_get_api_url() ) . '</code>'
                );
                ?>
                <br>
                <small><?php esc_html_e( 'To disable: remove WPHOULA_DEV_MODE from wp-config.php and clear api_url.', 'wp-houla' ); ?></small>
            </p>
        </div>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- Tab navigation                                                 -->
    <!-- ============================================================= -->
    <nav class="nav-tab-wrapper wphoula-tabs">
        <a href="#tab-connection" class="nav-tab nav-tab-active" data-tab="connection">
            <?php esc_html_e( 'Connection', 'wp-houla' ); ?>
        </a>
        <?php if ( $is_connected ) : ?>
        <a href="#tab-shortlinks" class="nav-tab" data-tab="shortlinks">
            <?php esc_html_e( 'Short Links & QR Code', 'wp-houla' ); ?>
        </a>
        <?php endif; ?>
        <?php if ( wphoula_is_woocommerce_active() ) : ?>
        <a href="#tab-sync" class="nav-tab" data-tab="sync">
            <?php esc_html_e( 'Sync', 'wp-houla' ); ?>
        </a>
        <a href="#tab-orders" class="nav-tab" data-tab="orders">
            <?php esc_html_e( 'Orders', 'wp-houla' ); ?>
        </a>
        <?php endif; ?>
        <a href="#tab-debug" class="nav-tab" data-tab="debug">
            <?php esc_html_e( 'Debug', 'wp-houla' ); ?>
        </a>
    </nav>

    <!-- ============================================================= -->
    <!-- TAB: Connection                                                -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-connection">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Hou.la Account', 'wp-houla' ); ?></h2>

            <?php if ( $is_connected ) : ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Status', 'wp-houla' ); ?></th>
                        <td>
                            <span class="wphoula-status wphoula-status--connected">
                                <?php esc_html_e( 'Connected', 'wp-houla' ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ( $workspace_name ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Workspace', 'wp-houla' ); ?></th>
                        <td><?php echo esc_html( $workspace_name ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $user_email ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Email', 'wp-houla' ); ?></th>
                        <td><?php echo esc_html( $user_email ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <p>
                    <button type="button" class="button button-secondary" id="wphoula-disconnect">
                        <?php esc_html_e( 'Disconnect', 'wp-houla' ); ?>
                    </button>
                </p>

            <?php else : ?>
                <p><?php esc_html_e( 'Connect your Hou.la account to generate short links and QR codes for your posts.', 'wp-houla' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( $auth->get_authorization_url() ); ?>" class="button button-primary button-hero">
                        <?php esc_html_e( 'Connect to Hou.la', 'wp-houla' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- TAB: Short Links & QR Code                                     -->
    <!-- ============================================================= -->
    <?php if ( $is_connected ) : ?>
    <div class="wphoula-tab-content" id="tab-shortlinks" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Shortlink Settings', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'Configure which content types get automatic Hou.la short links and QR codes.', 'wp-houla' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Post types', 'wp-houla' ); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'wp-houla' ); ?></legend>
                            <?php
                            $allowed = $options->get( 'allowed_post_types' );
                            if ( ! is_array( $allowed ) ) {
                                $allowed = array();
                            }
                            $post_types = get_post_types( array( 'public' => true ), 'objects' );
                            foreach ( $post_types as $pt ) :
                                if ( 'attachment' === $pt->name ) continue;
                                $checked = in_array( $pt->name, $allowed, true );
                            ?>
                                <label style="display: block; margin-bottom: 4px;">
                                    <input type="checkbox" class="wphoula-post-type" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( $checked ); ?>>
                                    <?php echo esc_html( $pt->labels->name ); ?> <code>(<?php echo esc_html( $pt->name ); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e( 'Short links and QR codes will be generated for selected post types.', 'wp-houla' ); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary wphoula-save-settings-btn">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- TAB: Sync (WooCommerce only)                                   -->
    <!-- ============================================================= -->
    <?php if ( wphoula_is_woocommerce_active() ) : ?>
    <div class="wphoula-tab-content" id="tab-sync" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Product Sync', 'wp-houla' ); ?></h2>

            <!-- Shop activation check (loaded via AJAX) -->
            <div id="wphoula-shop-status-banner" class="wphoula-shop-status-banner" style="display:none;">
                <span class="dashicons dashicons-warning" style="color:#d63638; margin-right:6px;"></span>
                <div>
                    <strong><?php esc_html_e( 'Shop not activated', 'wp-houla' ); ?></strong>
                    <p style="margin:4px 0 0; color:#666;">
                        <?php esc_html_e( 'Your Hou.la shop is not activated yet. Please connect Stripe on your Hou.la dashboard (Manager > Shop) before syncing products.', 'wp-houla' ); ?>
                    </p>
                </div>
            </div>

            <?php if ( ! $is_connected ) : ?>
                <p class="description"><?php esc_html_e( 'Connect your account first to configure sync options.', 'wp-houla' ); ?></p>
            <?php else : ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Auto-sync', 'wp-houla' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wphoula-auto-sync" <?php checked( $auto_sync ); ?>>
                                <?php esc_html_e( 'Automatically sync product changes to Hou.la', 'wp-houla' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Sync on publish', 'wp-houla' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wphoula-sync-on-publish" <?php checked( $sync_on_publish ); ?>>
                                <?php esc_html_e( 'Sync new products when they are published', 'wp-houla' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Category filter', 'wp-houla' ); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e( 'Category filter', 'wp-houla' ); ?></legend>
                                <?php
                                $sync_categories = $options->get( 'sync_categories' );
                                if ( ! is_array( $sync_categories ) ) {
                                    $sync_categories = array();
                                }
                                $product_cats = get_terms( array(
                                    'taxonomy'   => 'product_cat',
                                    'hide_empty' => false,
                                    'orderby'    => 'name',
                                    'order'      => 'ASC',
                                ) );
                                if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) :
                                    foreach ( $product_cats as $cat ) :
                                        $checked = in_array( (int) $cat->term_id, array_map( 'intval', $sync_categories ), true );
                                ?>
                                    <label style="display: block; margin-bottom: 4px;">
                                        <input type="checkbox" class="wphoula-sync-category" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php checked( $checked ); ?>>
                                        <?php echo esc_html( $cat->name ); ?>
                                        <span class="description">(<?php echo esc_html( $cat->count ); ?>)</span>
                                    </label>
                                <?php
                                    endforeach;
                                else :
                                ?>
                                    <p class="description"><?php esc_html_e( 'No WooCommerce categories found.', 'wp-houla' ); ?></p>
                                <?php endif; ?>
                                <p class="description"><?php esc_html_e( 'Select which categories to sync. Leave all unchecked to sync all products.', 'wp-houla' ); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Products synced', 'wp-houla' ); ?></th>
                        <td><strong><?php echo esc_html( $products_synced ?: '0' ); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Last full sync', 'wp-houla' ); ?></th>
                        <td><?php echo $last_full_sync ? esc_html( $last_full_sync ) : esc_html__( 'Never', 'wp-houla' ); ?></td>
                    </tr>
                </table>

                <p>
                    <button type="button" class="button button-primary" id="wphoula-save-settings">
                        <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                    </button>

                    <button type="button" class="button button-secondary" id="wphoula-batch-sync">
                        <?php esc_html_e( 'Sync Products', 'wp-houla' ); ?>
                    </button>
                    <span id="wphoula-sync-status" class="wphoula-spinner" style="display:none;"></span>
                </p>

                <!-- Progress bar (hidden by default) -->
                <div id="wphoula-sync-progress" class="wphoula-progress" style="display:none;">
                    <div class="wphoula-progress-bar">
                        <div class="wphoula-progress-fill" id="wphoula-progress-fill"></div>
                    </div>
                    <p class="wphoula-progress-text" id="wphoula-progress-text"></p>
                </div>

            <?php endif; ?>
        </div>

        <?php if ( $is_connected ) : ?>
        <!-- ============================================================= -->
        <!-- Price adjustment                                               -->
        <!-- ============================================================= -->
        <div class="wphoula-card" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Price Adjustment', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'Apply a price markup or discount when syncing to Hou.la. Useful to set different prices on your bio page.', 'wp-houla' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Adjustment type', 'wp-houla' ); ?></th>
                    <td>
                        <?php
                        $price_adj_type  = $options->get( 'price_adjustment_type' );
                        $price_adj_value = $options->get( 'price_adjustment_value' );
                        ?>
                        <select id="wphoula-price-adj-type">
                            <option value="none" <?php selected( $price_adj_type, 'none' ); ?>>
                                <?php esc_html_e( 'No adjustment (sync original prices)', 'wp-houla' ); ?>
                            </option>
                            <option value="percent_up" <?php selected( $price_adj_type, 'percent_up' ); ?>>
                                <?php esc_html_e( 'Markup by percentage (+%)', 'wp-houla' ); ?>
                            </option>
                            <option value="percent_down" <?php selected( $price_adj_type, 'percent_down' ); ?>>
                                <?php esc_html_e( 'Discount by percentage (-%)', 'wp-houla' ); ?>
                            </option>
                            <option value="fixed_up" <?php selected( $price_adj_type, 'fixed_up' ); ?>>
                                <?php esc_html_e( 'Markup by fixed amount (+)', 'wp-houla' ); ?>
                            </option>
                            <option value="fixed_down" <?php selected( $price_adj_type, 'fixed_down' ); ?>>
                                <?php esc_html_e( 'Discount by fixed amount (-)', 'wp-houla' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr id="wphoula-price-adj-row" <?php echo $price_adj_type === 'none' ? 'style="display:none;"' : ''; ?>>
                    <th><?php esc_html_e( 'Value', 'wp-houla' ); ?></th>
                    <td>
                        <input type="number" id="wphoula-price-adj-value"
                               value="<?php echo esc_attr( $price_adj_value ); ?>"
                               min="0" step="0.01" class="small-text" style="width: 100px;">
                        <span id="wphoula-price-adj-unit">
                            <?php
                            if ( in_array( $price_adj_type, array( 'percent_up', 'percent_down' ), true ) ) {
                                echo '%';
                            } else {
                                echo esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'EUR' );
                            }
                            ?>
                        </span>
                        <p class="description" id="wphoula-price-example"></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary wphoula-save-settings-btn">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>

        <!-- ============================================================= -->
        <!-- Category -> Collection mapping                                 -->
        <!-- ============================================================= -->
        <div class="wphoula-card" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Category - Collection Mapping', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'Map your WooCommerce categories to Hou.la collections. Products will be automatically placed in the matching collection during sync.', 'wp-houla' ); ?>
            </p>

            <?php
            $cat_collection_map = $options->get( 'category_collection_map' );
            if ( ! is_array( $cat_collection_map ) ) {
                $cat_collection_map = array();
            }
            ?>

            <table class="wphoula-mapping-table widefat" id="wphoula-mapping-table">
                <thead>
                    <tr>
                        <th style="width:40%;"><?php esc_html_e( 'WooCommerce Category', 'wp-houla' ); ?></th>
                        <th style="width:10%; text-align:center;">&#8594;</th>
                        <th style="width:40%;"><?php esc_html_e( 'Hou.la Collection', 'wp-houla' ); ?></th>
                        <th style="width:10%;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) :
                        foreach ( $product_cats as $cat ) :
                            $mapped_collection = isset( $cat_collection_map[ $cat->term_id ] )
                                ? $cat_collection_map[ $cat->term_id ]
                                : '';
                    ?>
                    <tr data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>">
                        <td>
                            <strong><?php echo esc_html( $cat->name ); ?></strong>
                            <span class="description">(<?php echo esc_html( $cat->count ); ?> <?php esc_html_e( 'products', 'wp-houla' ); ?>)</span>
                        </td>
                        <td style="text-align:center; color:#999;">&#8594;</td>
                        <td>
                            <select class="wphoula-collection-select" data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>" style="width:100%;">
                                <option value=""><?php esc_html_e( '- Not mapped -', 'wp-houla' ); ?></option>
                            </select>
                            <input type="hidden" class="wphoula-collection-value" value="<?php echo esc_attr( $mapped_collection ); ?>">
                        </td>
                        <td></td>
                    </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>

            <p style="margin-top: 12px;">
                <button type="button" class="button button-secondary" id="wphoula-auto-map">
                    <span class="dashicons dashicons-admin-generic" style="line-height:28px;"></span>
                    <?php esc_html_e( 'Auto-create collections from categories', 'wp-houla' ); ?>
                </button>
                <span id="wphoula-automap-status" style="display:none; margin-left: 8px;"></span>
            </p>
            <p class="description">
                <?php esc_html_e( 'Auto-create will create a Hou.la collection for each WooCommerce category and map them automatically.', 'wp-houla' ); ?>
            </p>

            <p style="margin-top: 16px;">
                <button type="button" class="button button-primary wphoula-save-settings-btn">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>

        <!-- ============================================================= -->
        <!-- Synced products table                                          -->
        <!-- ============================================================= -->
        <div class="wphoula-card" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Synced Products', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'Products currently synchronized on Hou.la from this WooCommerce store.', 'wp-houla' ); ?>
            </p>

            <div id="wphoula-synced-products-loading" style="padding: 20px; text-align: center;">
                <span class="spinner is-active" style="float:none; margin: 0 8px 0 0;"></span>
                <?php esc_html_e( 'Loading synced products...', 'wp-houla' ); ?>
            </div>

            <table class="wphoula-products-table widefat" id="wphoula-synced-products" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th style="width:30%;"><?php esc_html_e( 'Product', 'wp-houla' ); ?></th>
                        <th style="width:12%;"><?php esc_html_e( 'WC Price', 'wp-houla' ); ?></th>
                        <th style="width:12%;"><?php esc_html_e( 'Hou.la Price', 'wp-houla' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'Status', 'wp-houla' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'Stock', 'wp-houla' ); ?></th>
                        <th style="width:21%;"><?php esc_html_e( 'Last synced', 'wp-houla' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>

            <div id="wphoula-synced-products-empty" style="display:none; padding: 20px; text-align: center; color: #666;">
                <?php esc_html_e( 'No products synced yet. Click "Sync All Products Now" to start.', 'wp-houla' ); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================= -->
    <!-- TAB: Orders (WooCommerce only)                                 -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-orders" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Orders via Hou.la', 'wp-houla' ); ?></h2>

            <?php if ( ! $is_connected ) : ?>
                <p class="description"><?php esc_html_e( 'Connect your account first.', 'wp-houla' ); ?></p>
            <?php else : ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Webhook URL', 'wp-houla' ); ?></th>
                        <td>
                            <code><?php echo esc_html( rest_url( 'wp-houla/v1/webhook' ) ); ?></code>
                            <p class="description"><?php esc_html_e( 'This URL is automatically registered with Hou.la.', 'wp-houla' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Orders received', 'wp-houla' ); ?></th>
                        <td><strong><?php echo esc_html( $orders_received ?: '0' ); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Last order', 'wp-houla' ); ?></th>
                        <td><?php echo $last_order_at ? esc_html( $last_order_at ) : esc_html__( 'None yet', 'wp-houla' ); ?></td>
                    </tr>
                </table>

                <p class="description">
                    <?php esc_html_e( 'Orders placed through your Hou.la bio page appear in WooCommerce > Orders with the payment method "Hou.la Pay (Stripe)".', 'wp-houla' ); ?>
                </p>

            <?php endif; ?>
        </div>

        <?php if ( $is_connected ) : ?>
        <!-- ── Status concordance table ── -->
        <div class="wphoula-card" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Status Concordance', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'Define how each WooCommerce order status maps to a Hou.la status. This mapping is used in both directions (WooCommerce → Hou.la and Hou.la → WooCommerce).', 'wp-houla' ); ?>
            </p>

            <?php
            // Hou.la statuses available as dropdown options
            $houla_statuses = array(
                'pending'    => __( 'Pending', 'wp-houla' ),
                'paid'       => __( 'Paid', 'wp-houla' ),
                'processing' => __( 'Processing', 'wp-houla' ),
                'shipped'    => __( 'Shipped', 'wp-houla' ),
                'delivered'  => __( 'Delivered', 'wp-houla' ),
                'cancelled'  => __( 'Cancelled', 'wp-houla' ),
                'refunded'   => __( 'Refunded', 'wp-houla' ),
            );

            // Get all registered WooCommerce statuses
            $wc_statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();

            // Current saved mapping (wc_slug => houla_status)
            $status_map = $options->get( 'order_status_map' );
            if ( ! is_array( $status_map ) || empty( $status_map ) ) {
                // Default mapping
                $status_map = array(
                    'wc-on-hold'    => 'pending',
                    'wc-processing' => 'processing',
                    'wc-completed'  => 'delivered',
                    'wc-cancelled'  => 'cancelled',
                    'wc-refunded'   => 'refunded',
                );
            }
            ?>

            <table class="wphoula-concordance-table widefat">
                <thead>
                    <tr>
                        <th style="width: 45%;"><?php esc_html_e( 'WooCommerce Status', 'wp-houla' ); ?></th>
                        <th style="width: 10%; text-align:center;">→</th>
                        <th style="width: 45%;"><?php esc_html_e( 'Hou.la Status', 'wp-houla' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $wc_statuses as $wc_slug => $wc_label ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $wc_label ); ?></strong>
                            <code style="font-size: 11px; color: #999; margin-left: 6px;"><?php echo esc_html( $wc_slug ); ?></code>
                        </td>
                        <td style="text-align:center; font-size: 18px; color: #999;">→</td>
                        <td>
                            <select class="wphoula-status-map" data-wc-status="<?php echo esc_attr( $wc_slug ); ?>">
                                <option value=""><?php esc_html_e( '— Not mapped —', 'wp-houla' ); ?></option>
                                <?php foreach ( $houla_statuses as $houla_key => $houla_label ) : ?>
                                <option value="<?php echo esc_attr( $houla_key ); ?>"
                                    <?php selected( isset( $status_map[ $wc_slug ] ) ? $status_map[ $wc_slug ] : '', $houla_key ); ?>>
                                    <?php echo esc_html( $houla_label ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 12px;">
                <button type="button" class="button button-primary wphoula-save-settings-btn">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>

        <!-- ── Tracking auto-sync settings ── -->
        <div class="wphoula-card" style="margin-top: 20px;">
            <h2><?php esc_html_e( 'Tracking Sync', 'wp-houla' ); ?></h2>
            <p class="description" style="margin-bottom: 12px;">
                <?php esc_html_e( 'When a tracking number is added to a WooCommerce order, it will be automatically sent to Hou.la so buyers can track their shipment.', 'wp-houla' ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Auto-sync tracking', 'wp-houla' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="wphoula-sync-tracking" <?php checked( $options->get( 'sync_tracking' ) ); ?>>
                            <?php esc_html_e( 'Automatically push tracking numbers to Hou.la when added in WooCommerce', 'wp-houla' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Supported plugins', 'wp-houla' ); ?></th>
                    <td>
                        <ul style="margin: 0; list-style: disc inside;">
                            <li><?php esc_html_e( 'Advanced Shipment Tracking (AST)', 'wp-houla' ); ?></li>
                            <li><?php esc_html_e( 'WooCommerce Shipment Tracking', 'wp-houla' ); ?></li>
                            <li><?php esc_html_e( 'YITH WooCommerce Order Tracking', 'wp-houla' ); ?></li>
                            <li><?php esc_html_e( 'Or any plugin storing tracking in order meta', 'wp-houla' ); ?></li>
                        </ul>
                        <p class="description" style="margin-top: 8px;">
                            <?php esc_html_e( 'Tracking links are generated automatically for major carriers (Colissimo, DPD, GLS, Chronopost, UPS, FedEx, DHL, etc.).', 'wp-houla' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary wphoula-save-settings-btn">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; /* wphoula_is_woocommerce_active() */ ?>

    <!-- ============================================================= -->
    <!-- TAB: Debug                                                     -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-debug" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Debug Information', 'wp-houla' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Debug logging', 'wp-houla' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="wphoula-debug" <?php checked( $debug ); ?>>
                            <?php esc_html_e( 'Enable debug logging (writes to wp-content/debug.log)', 'wp-houla' ); ?>
                        </label>
                    </td>
                </tr>
                <?php if ( function_exists( 'wphoula_is_dev_mode' ) && wphoula_is_dev_mode() ) : ?>
                <tr>
                    <th><?php esc_html_e( 'API URL', 'wp-houla' ); ?></th>
                    <td>
                        <?php
                        $api_url = $options->get( 'api_url' );
                        $current_url = function_exists( 'wphoula_get_api_url' ) ? wphoula_get_api_url() : WPHOULA_API_URL;
                        ?>
                        <input type="url" id="wphoula-api-url" value="<?php echo esc_attr( $api_url ); ?>"
                               placeholder="<?php echo esc_attr( WPHOULA_DEFAULT_API_URL ); ?>"
                               class="regular-text" style="width: 400px;">
                        <p class="description">
                            <?php esc_html_e( 'Custom API URL for development. Leave empty for production (https://hou.la).', 'wp-houla' ); ?>
                            <br>
                            <?php
                            printf(
                                esc_html__( 'Current: %s', 'wp-houla' ),
                                '<code>' . esc_html( $current_url ) . '</code>'
                            );
                            ?>
                        </p>
                        <p class="description" style="margin-top: 8px; color: #d63638;">
                            <?php esc_html_e( 'OAuth + all API calls will use this URL. Example: https://xxx.ngrok-free.dev', 'wp-houla' ); ?>
                        </p>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Plugin version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( WPHOULA_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'PHP version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( PHP_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WordPress version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WooCommerce version', 'wp-houla' ); ?></th>
                    <td><?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : '---'; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'REST API base', 'wp-houla' ); ?></th>
                    <td><code><?php echo esc_html( rest_url( 'wp-houla/v1/' ) ); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Webhook secret', 'wp-houla' ); ?></th>
                    <td>
                        <?php
                        $secret = $options->get( 'webhook_secret' );
                        echo $secret
                            ? '<code>' . esc_html( substr( $secret, 0, 8 ) ) . '...</code>'
                            : esc_html__( 'Not generated', 'wp-houla' );
                        ?>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary" id="wphoula-save-settings">
                    <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                </button>
            </p>
        </div>
    </div>
</div>
