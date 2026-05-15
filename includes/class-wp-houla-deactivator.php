<?php
/**
 * Deactivation routines.
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Deactivator {

    /**
     * Runs on plugin deactivation.
     *
     * We do NOT delete options here (that happens on uninstall).
     * We just clean up transients and flush rewrite rules.
     */
    public static function deactivate() {
        delete_transient( 'wphoula_batch_sync_running' );
        delete_transient( 'wphoula_bg_sync_status' );
        delete_transient( 'wphoula_bg_sync_nonce' );
        flush_rewrite_rules();
    }
}
