<?php
/**
 * PHPUnit bootstrap file for WP-Houla tests.
 *
 * Uses Brain\Monkey to mock WordPress functions without loading WordPress.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants used by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
    define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
}
if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
    define( 'SECURE_AUTH_KEY', 'test-secure-auth-key-for-phpunit-testing-only' );
}

// Plugin constants.
if ( ! defined( 'WPHOULA_VERSION' ) ) {
    define( 'WPHOULA_VERSION', '1.0.0' );
}
if ( ! defined( 'WPHOULA_DIR' ) ) {
    define( 'WPHOULA_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'WPHOULA_URL' ) ) {
    define( 'WPHOULA_URL', 'https://example.com/wp-content/plugins/wp-houla/' );
}
if ( ! defined( 'WPHOULA_BASENAME' ) ) {
    define( 'WPHOULA_BASENAME', 'wp-houla/wp-houla.php' );
}
if ( ! defined( 'WPHOULA_OPTIONS' ) ) {
    define( 'WPHOULA_OPTIONS', 'wphoula-options' );
}
if ( ! defined( 'WPHOULA_AUTHORIZED' ) ) {
    define( 'WPHOULA_AUTHORIZED', 'wphoula-authorized' );
}
if ( ! defined( 'WPHOULA_API_URL' ) ) {
    define( 'WPHOULA_API_URL', 'https://api.hou.la' );
}
if ( ! defined( 'WPHOULA_OAUTH_URL' ) ) {
    define( 'WPHOULA_OAUTH_URL', 'https://hou.la/oauth/authorize' );
}
if ( ! defined( 'WPHOULA_OAUTH_TOKEN_URL' ) ) {
    define( 'WPHOULA_OAUTH_TOKEN_URL', WPHOULA_API_URL . '/oauth/token' );
}
if ( ! defined( 'WPHOULA_OAUTH_CLIENT_ID' ) ) {
    define( 'WPHOULA_OAUTH_CLIENT_ID', 'wp-houla' );
}
if ( ! defined( 'WPHOULA_LOG' ) ) {
    define( 'WPHOULA_LOG', '/tmp/wp-houla-debug.txt' );
}
