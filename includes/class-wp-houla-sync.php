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

        // Category filter check
        if ( ! $this->should_sync_product( $product ) ) {
            return;
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
     * Build the WC query args for batch sync (shared by all batch methods).
     *
     * @return array
     */
    private function build_batch_query_args() {
        $query_args = array(
            'status' => 'publish',
            'return' => 'objects',
        );

        $sync_categories = $this->options->get( 'sync_categories' );
        if ( ! empty( $sync_categories ) && is_array( $sync_categories ) ) {
            $slugs = array();
            foreach ( $sync_categories as $cat_id ) {
                $term = get_term( absint( $cat_id ), 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $slugs[] = $term->slug;
                }
            }
            if ( ! empty( $slugs ) ) {
                $query_args['category'] = $slugs;
            }
        }

        return $query_args;
    }

    /**
     * Count how many products will be synced.
     *
     * @return int
     */
    public function count_products_to_sync() {
        $args = $this->build_batch_query_args();
        $args['limit']  = -1;
        $args['return'] = 'ids';
        $ids = wc_get_products( $args );
        return count( $ids );
    }

    /**
     * Sync a single page of products.
     *
     * @param int $page Page number (1-based).
     * @return array { synced, errors, page, has_more }
     */
    public function batch_sync_page( $page = 1 ) {
        if ( ! $this->should_sync() ) {
            return array( 'synced' => 0, 'errors' => 0, 'page' => $page, 'has_more' => false, 'message' => 'Not connected' );
        }

        // Register connection on first page only
        if ( $page === 1 ) {
            $this->register_connection();
        }

        $limit = 20;
        $args  = $this->build_batch_query_args();
        $args['limit'] = $limit;
        $args['page']  = $page;

        $products = wc_get_products( $args );

        $synced = 0;
        $errors = 0;

        foreach ( $products as $product ) {
            $product_id = $product->get_id();
            $houla_id   = get_post_meta( $product_id, '_wphoula_product_id', true );
            $data       = $this->format_product( $product );

            if ( $houla_id ) {
                $result = $this->api->patch( '/ecommerce/products/' . $houla_id, $data );
            } else {
                $result = $this->api->post( '/ecommerce/products', $data );
            }

            if ( is_wp_error( $result ) ) {
                $errors++;
                $this->log( 'Batch sync page error for product #' . $product_id . ': ' . $result->get_error_message() );
                continue;
            }

            if ( ! $houla_id && isset( $result['id'] ) ) {
                update_post_meta( $product_id, '_wphoula_product_id', $result['id'] );
            }

            update_post_meta( $product_id, '_wphoula_synced', 1 );
            update_post_meta( $product_id, '_wphoula_sync_at', current_time( 'mysql' ) );
            $synced++;
        }

        $has_more = count( $products ) === $limit;

        // Update counters on last page
        if ( ! $has_more ) {
            $this->options->set( 'last_full_sync', current_time( 'mysql' ) );
        }

        return array( 'synced' => $synced, 'errors' => $errors, 'page' => $page, 'has_more' => $has_more );
    }

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
        // wc_get_products 'category' expects slugs, so convert term IDs to slugs
        if ( ! empty( $sync_categories ) && is_array( $sync_categories ) ) {
            $slugs = array();
            foreach ( $sync_categories as $cat_id ) {
                $term = get_term( absint( $cat_id ), 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $slugs[] = $term->slug;
                }
            }
            if ( ! empty( $slugs ) ) {
                $query_args['category'] = $slugs;
            }
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
    // Order status sync (WC → Hou.la)
    // =====================================================================

    /**
     * Default concordance: WooCommerce status → Hou.la status.
     * Used as fallback when no custom mapping is configured.
     */
    private static $default_wc_to_houla = array(
        'on-hold'    => 'pending',
        'processing' => 'processing',
        'completed'  => 'delivered',
        'cancelled'  => 'cancelled',
        'refunded'   => 'refunded',
    );

    /**
     * Build the WC → Hou.la status mapping from the saved concordance table.
     * The concordance is stored as houla_status => wc-slug, so we invert it.
     *
     * @return array WC status (without wc- prefix) => Hou.la status.
     */
    private function get_wc_to_houla_map() {
        $saved_map = $this->options->get( 'order_status_map' );
        if ( ! is_array( $saved_map ) || empty( $saved_map ) ) {
            return self::$default_wc_to_houla;
        }

        // Invert: houla_status => 'wc-xxx' becomes 'xxx' => houla_status
        $inverted = array();
        foreach ( $saved_map as $houla_status => $wc_slug ) {
            // Remove 'wc-' prefix if present
            $wc_key = preg_replace( '/^wc-/', '', $wc_slug );
            $inverted[ $wc_key ] = $houla_status;
        }

        return $inverted;
    }

    /**
     * Carrier tracking URL patterns.
     * Each carrier maps to a URL template with {tracking} placeholder.
     */
    private static $carrier_tracking_urls = array(
        'colissimo'    => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
        'la_poste'     => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
        'laposte'      => 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
        'chronopost'   => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}',
        'dpd'          => 'https://trace.dpd.fr/fr/trace/{tracking}',
        'gls'          => 'https://gls-group.com/FR/fr/suivi-colis?match={tracking}',
        'ups'          => 'https://www.ups.com/track?tracknum={tracking}',
        'fedex'        => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
        'dhl'          => 'https://www.dhl.com/fr-fr/home/suivi.html?tracking-id={tracking}',
        'dhl_express'  => 'https://www.dhl.com/fr-fr/home/suivi.html?tracking-id={tracking}',
        'mondialrelay' => 'https://www.mondialrelay.fr/suivi-de-colis/?NumeroExpedition={tracking}',
        'mondial_relay'=> 'https://www.mondialrelay.fr/suivi-de-colis/?NumeroExpedition={tracking}',
        'relais_colis' => 'https://www.relaiscolis.com/suivi-de-colis/index/tracking/{tracking}',
        'tnt'          => 'https://www.tnt.com/express/fr_fr/site/outils-expedition/suivi.html?searchType=con&cons={tracking}',
        'colis_prive'  => 'https://www.colisprive.fr/moncolis/pages/detailColis.aspx?numColis={tracking}',
        'lettre_suivie'=> 'https://www.laposte.fr/outils/suivre-vos-envois?code={tracking}',
        'usps'         => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
        'royal_mail'   => 'https://www.royalmail.com/track-your-item#/tracking-results/{tracking}',
        'postnl'       => 'https://postnl.nl/tracktrace/?B={tracking}',
        'bpost'        => 'https://track.bpost.cloud/btr/web/#/search?itemCode={tracking}',
        'hermes'       => 'https://www.myhermes.co.uk/tracking/{tracking}',
        'dpd_uk'       => 'https://track.dpd.co.uk/parcels/{tracking}',
    );

    /**
     * Build a tracking URL from carrier name and tracking number.
     *
     * @param string $carrier         Carrier identifier (e.g. 'colissimo', 'dpd').
     * @param string $tracking_number Tracking number.
     * @return string|null The tracking URL, or null if carrier is unknown.
     */
    private function build_tracking_url( $carrier, $tracking_number ) {
        $carrier_key = strtolower( sanitize_key( $carrier ) );
        // Try exact match
        if ( isset( self::$carrier_tracking_urls[ $carrier_key ] ) ) {
            return str_replace( '{tracking}', rawurlencode( $tracking_number ), self::$carrier_tracking_urls[ $carrier_key ] );
        }
        // Try partial match (e.g. "colissimo_international" matches "colissimo")
        foreach ( self::$carrier_tracking_urls as $key => $url_template ) {
            if ( strpos( $carrier_key, $key ) !== false || strpos( $key, $carrier_key ) !== false ) {
                return str_replace( '{tracking}', rawurlencode( $tracking_number ), $url_template );
            }
        }
        return null;
    }

    /**
     * Extract tracking information from a WooCommerce order.
     * Supports multiple popular tracking plugins.
     *
     * @param WC_Order $order WooCommerce order.
     * @return array|null { carrier: string, tracking_number: string, tracking_url: string|null } or null.
     */
    private function extract_tracking_info( $order ) {
        $order_id = $order->get_id();

        // 1. Advanced Shipment Tracking (AST) — most popular plugin
        //    Stores data in '_wc_shipment_tracking_items' order meta (serialized array)
        $ast_items = $order->get_meta( '_wc_shipment_tracking_items' );
        if ( ! empty( $ast_items ) && is_array( $ast_items ) ) {
            $item = end( $ast_items ); // Get the last (most recent) tracking entry
            $carrier  = isset( $item['tracking_provider'] ) ? $item['tracking_provider'] : '';
            $number   = isset( $item['tracking_number'] ) ? $item['tracking_number'] : '';
            if ( ! empty( $carrier ) ) {
                $carrier = strtolower( str_replace( ' ', '_', $carrier ) );
            }
            if ( ! empty( $number ) ) {
                return array(
                    'carrier'         => $carrier ?: 'other',
                    'tracking_number' => $number,
                    'tracking_url'    => $this->build_tracking_url( $carrier, $number ),
                );
            }
        }

        // 2. WooCommerce Shipment Tracking (official WooCommerce extension)
        //    Also uses '_wc_shipment_tracking_items' — same format as AST

        // 3. YITH WooCommerce Order Tracking
        $yith_carrier = $order->get_meta( 'ywot_tracking_code' );
        $yith_name    = $order->get_meta( 'ywot_carrier_name' );
        if ( ! empty( $yith_carrier ) ) {
            $carrier = ! empty( $yith_name ) ? strtolower( str_replace( ' ', '_', $yith_name ) ) : 'other';
            return array(
                'carrier'         => $carrier,
                'tracking_number' => $yith_carrier,
                'tracking_url'    => $this->build_tracking_url( $carrier, $yith_carrier ),
            );
        }

        // 4. Generic fallback: check common meta keys
        $tracking_number = '';
        $carrier_name    = '';
        $meta_keys_tracking = array( '_tracking_number', '_shipment_tracking_number', 'tracking_number', '_wc_tracking_number' );
        $meta_keys_carrier  = array( '_tracking_provider', '_shipping_provider', 'carrier', '_carrier', '_wc_tracking_provider' );

        foreach ( $meta_keys_tracking as $key ) {
            $val = $order->get_meta( $key );
            if ( ! empty( $val ) ) {
                $tracking_number = $val;
                break;
            }
        }
        foreach ( $meta_keys_carrier as $key ) {
            $val = $order->get_meta( $key );
            if ( ! empty( $val ) ) {
                $carrier_name = strtolower( str_replace( ' ', '_', $val ) );
                break;
            }
        }

        if ( ! empty( $tracking_number ) ) {
            return array(
                'carrier'         => $carrier_name ?: 'other',
                'tracking_number' => $tracking_number,
                'tracking_url'    => $this->build_tracking_url( $carrier_name, $tracking_number ),
            );
        }

        return null;
    }

    /**
     * Triggered when a WooCommerce order status changes.
     *
     * Sends the new status + tracking info to the Hou.la API so it stays in sync.
     * Only fires for orders that have a _houla_order_id meta.
     * Skips sync if _houla_skip_sync flag is set (loop prevention).
     *
     * @param int      $order_id   WooCommerce order ID.
     * @param string   $old_status Old status (without 'wc-' prefix).
     * @param string   $new_status New status (without 'wc-' prefix).
     * @param WC_Order $order      Order object.
     */
    public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
        if ( ! $this->auth->is_connected() ) {
            return;
        }

        // Prevent infinite loop: skip if this change came from a Hou.la webhook
        $skip = $order->get_meta( '_houla_skip_sync' );
        if ( $skip === '1' ) {
            $this->log( 'Order #' . $order_id . ' status change skipped (from Hou.la webhook).' );
            return;
        }

        // Only sync orders that were created via Hou.la
        $houla_order_id = $order->get_meta( '_houla_order_id' );
        if ( empty( $houla_order_id ) ) {
            return;
        }

        // Map WC status to Hou.la status using configurable concordance
        $wc_to_houla = $this->get_wc_to_houla_map();
        $houla_status = isset( $wc_to_houla[ $new_status ] )
            ? $wc_to_houla[ $new_status ]
            : null;

        if ( ! $houla_status ) {
            $this->log( 'Order #' . $order_id . ': no Hou.la mapping for WC status "' . $new_status . '".' );
            return;
        }

        // Build the API payload
        $payload = array( 'wc_status' => $new_status );

        // Extract and include tracking information if enabled
        if ( $this->options->get( 'sync_tracking' ) ) {
            $tracking = $this->extract_tracking_info( $order );
            if ( $tracking ) {
                $payload['carrier']         = $tracking['carrier'];
                $payload['tracking_number'] = $tracking['tracking_number'];
                if ( ! empty( $tracking['tracking_url'] ) ) {
                    $payload['tracking_url'] = $tracking['tracking_url'];
                }
                $this->log( 'Order #' . $order_id . ': tracking found — ' . $tracking['carrier'] . ' ' . $tracking['tracking_number'] );
            }
        }

        $result = $this->api->patch(
            '/ecommerce/orders/' . $houla_order_id . '/status',
            $payload
        );

        if ( is_wp_error( $result ) ) {
            $this->log( 'Order #' . $order_id . ' status sync failed: ' . $result->get_error_message() );
        } else {
            $this->log( 'Order #' . $order_id . ' status synced to Hou.la: ' . $new_status . ' → ' . $houla_status . '.' );
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
