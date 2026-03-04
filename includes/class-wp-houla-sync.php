<?php
/**
 * WooCommerce product synchronization with Hou.la.
 *
 * Listens to WooCommerce lifecycle hooks and pushes product
 * data to the Hou.la API so they can be listed on bio pages.
 *
 * Hooks handled:
 * - woocommerce_new_product          -> create on Hou.la
 * - woocommerce_update_product       -> update on Hou.la
 * - woocommerce_before_delete_product -> delete on Hou.la
 * - woocommerce_trash_product        -> delete on Hou.la
 * - woocommerce_product_set_stock    -> update stock on Hou.la
 * - woocommerce_variation_set_stock  -> update stock on Hou.la
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Sync {

    /** @var Wp_Houla_Api */
    private $api;

    /** @var Wp_Houla_Options */
    private $options;

    /** @var Wp_Houla_Auth */
    private $auth;

    /** @var bool Prevent recursive hooks during sync. */
    private static $syncing = false;

    public function __construct() {
        $this->api     = new Wp_Houla_Api();
        $this->options = new Wp_Houla_Options();
        $this->auth    = new Wp_Houla_Auth();
    }

    // =====================================================================
    // Hook callbacks (called from class-wp-houla.php)
    // =====================================================================

    /**
     * Triggered when a new WooCommerce product is published.
     *
     * @param int        $product_id WooCommerce product ID.
     * @param WC_Product $product    Product object.
     */
    public function on_product_created( $product_id, $product ) {
        if ( self::$syncing || ! $this->should_sync() ) {
            return;
        }

        $sync_on_publish = $this->options->get( 'sync_on_publish' );
        if ( ! $sync_on_publish ) {
            return;
        }

        // Category filter check
        if ( ! $this->should_sync_product( $product ) ) {
            return;
        }

        self::$syncing = true;
        $data          = $this->format_product( $product );
        $result        = $this->api->post( '/ecommerce/products', $data );

        if ( ! is_wp_error( $result ) && isset( $result['id'] ) ) {
            update_post_meta( $product_id, '_wphoula_product_id', $result['id'] );
            update_post_meta( $product_id, '_wphoula_synced', 1 );
            update_post_meta( $product_id, '_wphoula_sync_at', current_time( 'mysql' ) );
            $this->increment_counter( 'products_synced' );
            $this->log( 'Product #' . $product_id . ' created on Hou.la (ID: ' . $result['id'] . ').' );
        } else {
            $msg = is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error';
            $this->log( 'Failed to create product #' . $product_id . ': ' . $msg );
        }

        self::$syncing = false;
    }

    /**
     * Triggered when a WooCommerce product is updated.
     *
     * @param int        $product_id WooCommerce product ID.
     * @param WC_Product $product    Product object.
     */
    public function on_product_updated( $product_id, $product ) {
        if ( self::$syncing || ! $this->should_sync() ) {
            return;
        }

        // Category filter check
        if ( ! $this->should_sync_product( $product ) ) {
            return;
        }

        $houla_id = get_post_meta( $product_id, '_wphoula_product_id', true );
        if ( empty( $houla_id ) ) {
            // Not yet synced - create it if auto_sync is on
            if ( $this->options->get( 'auto_sync' ) ) {
                $this->on_product_created( $product_id, $product );
            }
            return;
        }

        self::$syncing = true;
        $data          = $this->format_product( $product );
        $result        = $this->api->patch( '/ecommerce/products/' . $houla_id, $data );

        if ( ! is_wp_error( $result ) ) {
            update_post_meta( $product_id, '_wphoula_sync_at', current_time( 'mysql' ) );
            $this->log( 'Product #' . $product_id . ' updated on Hou.la.' );
        } else {
            $this->log( 'Failed to update product #' . $product_id . ': ' . $result->get_error_message() );
        }

        self::$syncing = false;
    }

    /**
     * Triggered when a WooCommerce product is deleted or trashed.
     *
     * @param int $product_id WooCommerce product ID.
     */
    public function on_product_deleted( $product_id ) {
        if ( self::$syncing || ! $this->should_sync() ) {
            return;
        }

        $houla_id = get_post_meta( $product_id, '_wphoula_product_id', true );
        if ( empty( $houla_id ) ) {
            return;
        }

        self::$syncing = true;
        $result        = $this->api->delete( '/ecommerce/products/' . $houla_id );

        if ( ! is_wp_error( $result ) ) {
            delete_post_meta( $product_id, '_wphoula_product_id' );
            delete_post_meta( $product_id, '_wphoula_synced' );
            delete_post_meta( $product_id, '_wphoula_sync_at' );
            $this->log( 'Product #' . $product_id . ' deleted from Hou.la.' );
        } else {
            $this->log( 'Failed to delete product #' . $product_id . ': ' . $result->get_error_message() );
        }

        self::$syncing = false;
    }

    /**
     * Triggered when product stock changes.
     *
     * @param WC_Product $product Product object.
     */
    public function on_stock_changed( $product ) {
        if ( self::$syncing || ! $this->should_sync() ) {
            return;
        }

        $product_id = $product->get_id();

        // For variations, sync the parent product
        if ( $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();
            $parent    = wc_get_product( $parent_id );
            if ( $parent ) {
                $product_id = $parent_id;
                $product    = $parent;
            }
        }

        $houla_id = get_post_meta( $product_id, '_wphoula_product_id', true );
        if ( empty( $houla_id ) ) {
            return;
        }

        self::$syncing = true;
        $result        = $this->api->patch( '/ecommerce/products/' . $houla_id, array(
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status'   => $product->get_stock_status(),
            'manage_stock'   => $product->get_manage_stock(),
        ) );

        if ( ! is_wp_error( $result ) ) {
            update_post_meta( $product_id, '_wphoula_sync_at', current_time( 'mysql' ) );
        }

        self::$syncing = false;
    }

    // =====================================================================
    // Batch sync
    // =====================================================================

    /**
     * Full synchronization of all published WooCommerce products.
     * Called via WP-Cron or admin AJAX.
     *
     * Respects the sync_categories option: when set, only syncs
     * products belonging to the specified WooCommerce categories.
     *
     * @return array Summary (synced, skipped, errors).
     */
    public function batch_sync() {
        if ( ! $this->should_sync() ) {
            return array( 'synced' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Not connected' );
        }

        // Register the connection with webhook info before syncing
        $this->register_connection();

        // Prevent concurrent batch syncs
        $lock = get_transient( 'wphoula_batch_sync_lock' );
        if ( $lock ) {
            return array( 'synced' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'Sync already in progress' );
        }
        set_transient( 'wphoula_batch_sync_lock', true, 600 ); // 10 min lock

        $page    = 1;
        $limit   = 50;
        $synced  = 0;
        $skipped = 0;
        $errors  = 0;

        // Category filtering
        $sync_categories = $this->options->get( 'sync_categories' );
        $query_args = array(
            'status' => 'publish',
            'limit'  => $limit,
            'page'   => $page,
            'return' => 'objects',
        );

        // Filter by WooCommerce categories if configured
        if ( ! empty( $sync_categories ) && is_array( $sync_categories ) ) {
            $query_args['category'] = array_map( 'absint', $sync_categories );
        }

        do {
            $query_args['page'] = $page;
            $products = wc_get_products( $query_args );

            foreach ( $products as $product ) {
                $product_id = $product->get_id();
                $houla_id   = get_post_meta( $product_id, '_wphoula_product_id', true );
                $data       = $this->format_product( $product );

                if ( $houla_id ) {
                    // Update
                    $result = $this->api->patch( '/ecommerce/products/' . $houla_id, $data );
                } else {
                    // Create
                    $result = $this->api->post( '/ecommerce/products', $data );
                }

                if ( is_wp_error( $result ) ) {
                    $errors++;
                    $this->log( 'Batch sync error for product #' . $product_id . ': ' . $result->get_error_message() );
                    continue;
                }

                if ( ! $houla_id && isset( $result['id'] ) ) {
                    update_post_meta( $product_id, '_wphoula_product_id', $result['id'] );
                }

                update_post_meta( $product_id, '_wphoula_synced', 1 );
                update_post_meta( $product_id, '_wphoula_sync_at', current_time( 'mysql' ) );
                $synced++;
            }

            $page++;
        } while ( count( $products ) === $limit );

        // Update counters
        $this->options->set( 'products_synced', $synced );
        $this->options->set( 'last_full_sync', current_time( 'mysql' ) );

        delete_transient( 'wphoula_batch_sync_lock' );

        $this->log( "Batch sync complete: $synced synced, $skipped skipped, $errors errors." );

        return compact( 'synced', 'skipped', 'errors' );
    }

    // =====================================================================
    // Product formatting
    // =====================================================================

    /**
     * Format a WooCommerce product into the Hou.la API payload.
     *
     * @param WC_Product $product
     * @return array
     */
    private function format_product( $product ) {
        $data = array(
            'external_id'     => (string) $product->get_id(),
            'platform'        => 'woocommerce',
            'site_url'        => get_site_url(),
            'name'            => $product->get_name(),
            'description'     => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
            'url'             => $product->get_permalink(),
            'price'           => (float) $product->get_price(),
            'regular_price'   => (float) $product->get_regular_price(),
            'currency'        => get_woocommerce_currency(),
            'status'          => $product->get_status() === 'publish' ? 'active' : 'draft',
            'stock_status'    => $product->get_stock_status(),
            'manage_stock'    => $product->get_manage_stock(),
            'sku'             => $product->get_sku(),
            'weight'          => $product->get_weight(),
            'type'            => $product->get_type(),
        );

        // Apply price adjustment
        $adj_type  = $this->options->get( 'price_adjustment_type' );
        $adj_value = (float) $this->options->get( 'price_adjustment_value' );
        if ( $adj_type !== 'none' && $adj_value > 0 ) {
            $data['price']         = $this->adjust_price( $data['price'], $adj_type, $adj_value );
            $data['regular_price'] = $this->adjust_price( $data['regular_price'], $adj_type, $adj_value );
        }

        // Sale price
        $sale_price = $product->get_sale_price();
        if ( $sale_price !== '' ) {
            $adjusted = (float) $sale_price;
            if ( $adj_type !== 'none' && $adj_value > 0 ) {
                $adjusted = $this->adjust_price( $adjusted, $adj_type, $adj_value );
            }
            $data['sale_price'] = $adjusted;
        }

        // Stock quantity
        if ( $product->get_manage_stock() ) {
            $data['stock_quantity'] = $product->get_stock_quantity();
        }

        // Images
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $data['image'] = wp_get_attachment_url( $image_id );
        }

        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) ) {
            $data['gallery'] = array_filter( array_map( 'wp_get_attachment_url', $gallery_ids ) );
        }

        // Categories
        $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
            $data['categories'] = $categories;
        }

        // Category -> Collection mapping: resolve collection_ids
        $cat_collection_map = $this->options->get( 'category_collection_map' );
        if ( ! empty( $cat_collection_map ) && is_array( $cat_collection_map ) ) {
            $cat_ids = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
            if ( ! is_wp_error( $cat_ids ) && ! empty( $cat_ids ) ) {
                $collection_ids = array();
                foreach ( $cat_ids as $cat_id ) {
                    if ( isset( $cat_collection_map[ $cat_id ] ) && ! empty( $cat_collection_map[ $cat_id ] ) ) {
                        $collection_ids[] = $cat_collection_map[ $cat_id ];
                    }
                }
                $collection_ids = array_unique( $collection_ids );
                if ( ! empty( $collection_ids ) ) {
                    $data['collection_ids'] = array_values( $collection_ids );
                }
            }
        }

        // Tags
        $tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
        if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
            $data['tags'] = $tags;
        }

        // Dimensions
        if ( $product->get_length() || $product->get_width() || $product->get_height() ) {
            $data['dimensions'] = array(
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
                'unit'   => get_option( 'woocommerce_dimension_unit' ),
            );
        }

        // Variations (for variable products)
        if ( $product->is_type( 'variable' ) ) {
            $variations = array();
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) {
                    continue;
                }
                $variations[] = array(
                    'id'             => $variation_id,
                    'sku'            => $variation->get_sku(),
                    'price'          => (float) $variation->get_price(),
                    'regular_price'  => (float) $variation->get_regular_price(),
                    'sale_price'     => $variation->get_sale_price() !== '' ? (float) $variation->get_sale_price() : null,
                    'stock_quantity' => $variation->get_stock_quantity(),
                    'stock_status'   => $variation->get_stock_status(),
                    'attributes'     => $variation->get_attributes(),
                    'image'          => wp_get_attachment_url( $variation->get_image_id() ) ?: null,
                );
            }
            $data['variations'] = $variations;
        }

        // Attributes (for simple products with visible attributes)
        $attributes = $product->get_attributes();
        if ( ! empty( $attributes ) ) {
            $formatted = array();
            foreach ( $attributes as $attr ) {
                if ( is_a( $attr, 'WC_Product_Attribute' ) && $attr->get_visible() ) {
                    $formatted[] = array(
                        'name'    => $attr->get_name(),
                        'options' => $attr->get_options(),
                    );
                }
            }
            if ( ! empty( $formatted ) ) {
                $data['attributes'] = $formatted;
            }
        }

        return $data;
    }

    // =====================================================================
    // Connection registration
    // =====================================================================

    /**
     * Register (or update) the ecommerce connection with Hou.la.
     * Sends the webhook callback URL and secret so Hou.la can
     * push order events (order.paid, order.refunded) back to WP.
     */
    public function register_connection() {
        $webhook_secret = $this->options->get( 'webhook_secret' );

        // Auto-generate webhook secret if not yet set
        if ( empty( $webhook_secret ) ) {
            $webhook_secret = wp_generate_password( 40, false );
            $this->options->set( 'webhook_secret', $webhook_secret );
        }

        $data = array(
            'platform'       => 'woocommerce',
            'site_url'       => get_site_url(),
            'webhook_url'    => rest_url( 'wp-houla/v1/webhook' ),
            'webhook_secret' => $webhook_secret,
            'push_orders'    => true,
            'push_refunds'   => true,
            'push_stock'     => false,
        );

        $result = $this->api->post( '/ecommerce/connect', $data );

        if ( is_wp_error( $result ) ) {
            $this->log( 'Failed to register connection: ' . $result->get_error_message() );
        } else {
            $this->log( 'Ecommerce connection registered successfully.' );
        }
    }

    // =====================================================================
    // Utilities
    // =====================================================================

    /**
     * Can we sync? Checks auth + auto_sync option.
     *
     * @return bool
     */
    private function should_sync() {
        return $this->auth->is_connected() && $this->options->get( 'auto_sync' );
    }

    /**
     * Check if a specific product should be synced based on category filters.
     *
     * @param int|WC_Product $product Product ID or object.
     * @return bool
     */
    private function should_sync_product( $product ) {
        $sync_categories = $this->options->get( 'sync_categories' );
        if ( empty( $sync_categories ) || ! is_array( $sync_categories ) ) {
            return true; // No filter = sync everything
        }

        $product_id = is_object( $product ) ? $product->get_id() : $product;
        $product_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

        if ( is_wp_error( $product_cats ) || empty( $product_cats ) ) {
            return false; // No categories and we have a filter = skip
        }

        // Check if any of the product's categories match the filter
        return ! empty( array_intersect( $product_cats, array_map( 'absint', $sync_categories ) ) );
    }

    /**
     * Increment a numeric counter in options.
     *
     * @param string $key Option key.
     */
    private function increment_counter( $key ) {
        $val = (int) $this->options->get( $key );
        $this->options->set( $key, $val + 1 );
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WP-Houla Sync] ' . $message );
        }
    }

    /**
     * Apply a price adjustment (markup or discount).
     *
     * @param float  $price    Original price.
     * @param string $type     Adjustment type (percent_up, percent_down, fixed_up, fixed_down).
     * @param float  $value    Adjustment value.
     * @return float Adjusted price (never negative).
     */
    private function adjust_price( $price, $type, $value ) {
        if ( $price <= 0 || $value <= 0 ) {
            return $price;
        }

        switch ( $type ) {
            case 'percent_up':
                return round( $price * ( 1 + $value / 100 ), 2 );
            case 'percent_down':
                return max( 0, round( $price * ( 1 - $value / 100 ), 2 ) );
            case 'fixed_up':
                return round( $price + $value, 2 );
            case 'fixed_down':
                return max( 0, round( $price - $value, 2 ) );
            default:
                return $price;
        }
    }
}
