<?php
/**
 * Internationalization handler.
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_i18n {

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-houla',
            false,
            dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
        );
    }
}
