<?php
/**
 * Base test case for WP-Houla unit tests.
 * Sets up Brain\Monkey for mocking WordPress functions.
 */

namespace WpHoula\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

abstract class TestCase extends PHPUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock common WordPress functions.
        Functions\stubs( [
            'esc_html__'        => function ( $text ) { return $text; },
            'esc_html_e'        => function ( $text ) { echo $text; },
            'esc_attr'          => function ( $text ) { return $text; },
            'esc_attr__'        => function ( $text ) { return $text; },
            'esc_attr_e'        => function ( $text ) { echo $text; },
            'esc_url'           => function ( $url ) { return $url; },
            '__'                => function ( $text ) { return $text; },
            '_e'                => function ( $text ) { echo $text; },
            'sanitize_text_field' => function ( $str ) { return trim( strip_tags( $str ) ); },
            'absint'            => function ( $val ) { return abs( intval( $val ) ); },
            'wp_json_encode'    => function ( $data ) { return json_encode( $data ); },
            'admin_url'         => function ( $path = '' ) { return 'https://example.com/wp-admin/' . $path; },
            'rest_url'          => function ( $path = '' ) { return 'https://example.com/wp-json/' . $path; },
            'plugins_url'       => function ( $path = '', $file = '' ) { return 'https://example.com/wp-content/plugins/wp-houla/' . $path; },
            'plugin_dir_path'   => function ( $file ) { return dirname( $file ) . '/'; },
            'plugin_basename'   => function ( $file ) { return 'wp-houla/wp-houla.php'; },
            'wp_generate_password' => function ( $length = 12, $special_chars = true ) {
                return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 3 ), 0, $length );
            },
        ] );
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
