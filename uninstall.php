<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up all data stored by wp-houla:
 * - wp_options entries
 * - product post meta
 * - order meta
 * - transients
 *
 * @since      1.0.0
 * @package    Wp_Houla
 */

// Abort if not called by WordPress uninstall mechanism
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// =====================================================================
// Options
// =====================================================================

delete_option( 'wphoula-options' );
delete_option( 'wphoula-authorized' );

// =====================================================================
// Post meta (shortlinks, QR codes)
// =====================================================================

$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN (
        '_wphoula_shortlink',
        '_wphoula_link_id',
        '_wphoula_qrcode'
     )"
);

// =====================================================================
// Product post meta
// =====================================================================

$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN (
        '_wphoula_product_id',
        '_wphoula_synced',
        '_wphoula_sync_at'
     )"
);

// =====================================================================
// Order meta
// =====================================================================

$order_meta_table = $wpdb->prefix . 'wc_orders_meta';
$orders_table     = $wpdb->prefix . 'wc_orders';

// HPOS (WooCommerce High-Performance Order Storage)
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$order_meta_table}'" ) === $order_meta_table ) {
    $wpdb->query(
        "DELETE FROM {$order_meta_table}
         WHERE meta_key IN ('_houla_order_id', '_houla_transaction_id')"
    );
}

// Legacy post meta orders
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta}
     WHERE meta_key IN ('_houla_order_id', '_houla_transaction_id')"
);

// =====================================================================
// Transients
// =====================================================================

$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_wphoula_%'
        OR option_name LIKE '_transient_timeout_wphoula_%'"
);
