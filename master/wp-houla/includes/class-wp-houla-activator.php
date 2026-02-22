<?php
/**
 * Activation routines.
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Activator {

    /**
     * Runs on plugin activation.
     *
     * - Checks PHP/WP version requirements
     * - Generates a webhook secret if not present
     * - Flushes rewrite rules for our REST endpoint
     */
    public static function activate() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( WPHOULA_BASENAME );
            wp_die(
                esc_html__( 'Hou.la Pay requires PHP 7.4 or higher.', 'wp-houla' ),
                'Plugin Activation Error',
                array( 'back_link' => true )
            );
        }

        // Generate webhook secret on first activation
        $options = get_option( WPHOULA_OPTIONS, array() );
        if ( empty( $options['webhook_secret'] ) ) {
            $options['webhook_secret'] = wp_generate_password( 64, false );
            update_option( WPHOULA_OPTIONS, $options );
        }

        // Ensure log directory exists
        $log_dir = WPHOULA_DIR . '/log';
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            file_put_contents( $log_dir . '/.htaccess', 'deny from all' );
        }

        // Flush rewrite rules so the REST endpoint is accessible
        flush_rewrite_rules();
    }
}
