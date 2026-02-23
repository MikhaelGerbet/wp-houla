<?php
/**
 * Hou.la Pay - WooCommerce Integration
 *
 * @link              https://hou.la/
 * @since             1.0.0
 * @package           Wp_Houla
 *
 * @wordpress-plugin
 * Plugin Name:       WP-Houla - Short Links, QR Codes & Social Commerce
 * Plugin URI:        https://hou.la/
 * Description:       Connect WordPress to Hou.la for automatic short links, QR codes on every post, and optional WooCommerce product sync with your bio page for social selling via Stripe.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Hou.la
 * Author URI:        https://hou.la/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-houla
 * Domain Path:       /languages
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 */

// Abort if called directly.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =========================================================================
// Constants
// =========================================================================

define( 'WPHOULA_VERSION', '1.2.0' );
define( 'WPHOULA_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) );
define( 'WPHOULA_URL', plugins_url( '/', __FILE__ ) );
define( 'WPHOULA_BASENAME', plugin_basename( __FILE__ ) );

define( 'WPHOULA_OPTIONS', 'wphoula-options' );
define( 'WPHOULA_AUTHORIZED', 'wphoula-authorized' );

// API URLs - change to staging/local for development
define( 'WPHOULA_API_URL', 'https://hou.la' );
define( 'WPHOULA_OAUTH_URL', 'https://hou.la/oauth/authorize' );
define( 'WPHOULA_OAUTH_TOKEN_URL', WPHOULA_API_URL . '/api/oauth/token' );
define( 'WPHOULA_OAUTH_CLIENT_ID', 'wp-houla' );

define( 'WPHOULA_LOG', WPHOULA_DIR . '/log/debug.txt' );

// =========================================================================
// Activation / Deactivation
// =========================================================================

function activate_wp_houla() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-houla-activator.php';
    Wp_Houla_Activator::activate();
}

function deactivate_wp_houla() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-houla-deactivator.php';
    Wp_Houla_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_houla' );
register_deactivation_hook( __FILE__, 'deactivate_wp_houla' );

// =========================================================================
// WooCommerce detection (optional, not required)
// =========================================================================

/**
 * Check whether WooCommerce is active.
 * The plugin works without it (shortlinks + QR codes only).
 * WooCommerce features (product sync, orders, webhook) load only when present.
 *
 * @return bool
 */
function wphoula_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

// =========================================================================
// Bootstrap
// =========================================================================

require plugin_dir_path( __FILE__ ) . 'includes/class-wp-houla.php';

function run_wp_houla() {
    $plugin = new Wp_Houla();
    $plugin->run();
}

add_action( 'plugins_loaded', 'run_wp_houla' );
