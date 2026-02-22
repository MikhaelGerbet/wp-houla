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
 * Description:       Connect WordPress to Hou.la for automatic short links, QR codes on every post, and WooCommerce product sync with your bio page for social selling via Stripe.
 * Version:           1.0.0
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

define( 'WPHOULA_VERSION', '1.0.0' );
define( 'WPHOULA_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) );
define( 'WPHOULA_URL', plugins_url( '/', __FILE__ ) );
define( 'WPHOULA_BASENAME', plugin_basename( __FILE__ ) );

define( 'WPHOULA_OPTIONS', 'wphoula-options' );
define( 'WPHOULA_AUTHORIZED', 'wphoula-authorized' );

// API URLs - change to staging/local for development
define( 'WPHOULA_API_URL', 'https://api.hou.la' );
define( 'WPHOULA_OAUTH_URL', 'https://hou.la/oauth/authorize' );
define( 'WPHOULA_OAUTH_TOKEN_URL', WPHOULA_API_URL . '/oauth/token' );
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
// WooCommerce dependency check
// =========================================================================

/**
 * Check that WooCommerce is active before running the plugin.
 */
function wphoula_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wphoula_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

function wphoula_woocommerce_missing_notice() {
    echo '<div class="error"><p>';
    echo esc_html__( 'Hou.la Pay requires WooCommerce to be installed and active.', 'wp-houla' );
    echo '</p></div>';
}

// =========================================================================
// Bootstrap
// =========================================================================

require plugin_dir_path( __FILE__ ) . 'includes/class-wp-houla.php';

function run_wp_houla() {
    if ( ! wphoula_check_woocommerce() ) {
        return;
    }

    $plugin = new Wp_Houla();
    $plugin->run();
}

add_action( 'plugins_loaded', 'run_wp_houla' );
