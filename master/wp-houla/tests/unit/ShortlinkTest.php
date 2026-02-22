<?php
/**
 * Unit tests for Wp_Houla_Shortlink.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-options.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-api.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-shortlink.php';

class ShortlinkTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof \WP_Error;
        } );
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'update_post_meta' )->justReturn( true );
        Functions\when( 'get_post_status' )->justReturn( 'publish' );
        Functions\when( 'get_post_type' )->justReturn( 'post' );
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/my-post/' );
        Functions\when( 'get_the_title' )->justReturn( 'My Post Title' );
        Functions\when( 'get_the_ID' )->justReturn( 1 );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'get_post_types' )->justReturn( array( 'post', 'page', 'product' ) );
        // Do NOT stub add_shortcode: test_register_shortcode uses expect().
        Functions\when( 'shortcode_atts' )->alias( function ( $defaults, $atts, $tag = '' ) {
            return array_merge( $defaults, (array) $atts );
        } );
    }

    private function mockConnected(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return true;
            return array(
                'access_token'     => \Wp_Houla_Options::encrypt( 'test-token' ),
                'refresh_token'    => \Wp_Houla_Options::encrypt( 'test-refresh' ),
                'token_expires_at' => time() + 3600,
            );
        } );
    }

    private function mockDisconnected(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return false;
            return array();
        } );
    }

    public function test_generate_shortlink_returns_false_when_disconnected(): void {
        $this->mockDisconnected();

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 1 );

        $this->assertFalse( $result );
    }

    public function test_generate_shortlink_returns_false_for_draft(): void {
        $this->mockConnected();
        Functions\when( 'get_post_status' )->justReturn( 'draft' );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 1 );

        $this->assertFalse( $result );
    }

    public function test_generate_shortlink_returns_existing_if_not_forced(): void {
        $this->mockConnected();
        Functions\when( 'get_post_meta' )->justReturn( 'https://hou.la/existing' );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 1, false );

        $this->assertEquals( 'https://hou.la/existing', $result );
    }

    public function test_generate_shortlink_calls_api_and_stores_meta(): void {
        $this->mockConnected();

        $apiResponse = array(
            'id'        => 'link-123',
            'shortUrl'  => 'https://hou.la/abc',
            'qrCodeUrl' => 'https://api.hou.la/api/links/link-123/qrcode',
        );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => json_encode( $apiResponse ),
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( json_encode( $apiResponse ) );

        $metaStored = array();
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'update_post_meta' )->alias( function ( $post_id, $key, $value ) use ( &$metaStored ) {
            $metaStored[ $key ] = $value;
            return true;
        } );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 1 );

        $this->assertEquals( 'https://hou.la/abc', $result );
        $this->assertEquals( 'https://hou.la/abc', $metaStored['_wphoula_shortlink'] );
        $this->assertEquals( 'link-123', $metaStored['_wphoula_link_id'] );
        $this->assertNotEmpty( $metaStored['_wphoula_qrcode'] );
    }

    public function test_generate_shortlink_returns_false_on_api_error(): void {
        $this->mockConnected();

        Functions\when( 'wp_remote_request' )->justReturn( new \WP_Error( 'api_error', 'Server error' ) );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 1 );

        $this->assertFalse( $result );
    }

    public function test_filter_get_shortlink_returns_houla_link(): void {
        $this->mockConnected();
        Functions\when( 'get_post_meta' )->justReturn( 'https://hou.la/existing' );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->filter_get_shortlink( '', 42, 'post', false );

        $this->assertEquals( 'https://hou.la/existing', $result );
    }

    public function test_filter_get_shortlink_returns_original_when_disconnected(): void {
        $this->mockDisconnected();

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->filter_get_shortlink( 'https://example.com/?p=42', 42, 'post', false );

        $this->assertEquals( 'https://example.com/?p=42', $result );
    }

    public function test_register_shortcode(): void {
        Functions\expect( 'add_shortcode' )
            ->once()
            ->with( 'wphoula', \Mockery::type( 'array' ) );

        $shortlink = new \Wp_Houla_Shortlink();
        $shortlink->register_shortcode();
    }

    public function test_render_shortcode_qrcode(): void {
        $this->mockConnected();
        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            if ( $key === '_wphoula_qrcode' ) return 'https://api.hou.la/api/links/x/qrcode';
            return '';
        } );

        $shortlink = new \Wp_Houla_Shortlink();
        $output    = $shortlink->render_shortcode( array( 'qrcode' => true, 'post_id' => 1 ) );

        $this->assertStringContainsString( '<img', $output );
        $this->assertStringContainsString( 'wphoula-qrcode', $output );
        $this->assertStringContainsString( 'qrcode', $output );
    }

    public function test_render_shortcode_link(): void {
        $this->mockConnected();
        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            if ( $key === '_wphoula_shortlink' ) return 'https://hou.la/test';
            return '';
        } );
        Functions\when( 'get_post' )->justReturn( null );

        $shortlink = new \Wp_Houla_Shortlink();
        $output    = $shortlink->render_shortcode( array( 'post_id' => 1, 'text' => 'Click me' ) );

        $this->assertStringContainsString( 'href="https://hou.la/test"', $output );
        $this->assertStringContainsString( 'Click me', $output );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_on_save_post_skips_autosave(): void {
        $this->mockConnected();

        define( 'DOING_AUTOSAVE', true );

        $shortlink = new \Wp_Houla_Shortlink();
        // Should not throw or call generate.
        $post = (object) array( 'post_type' => 'post', 'post_status' => 'publish' );
        $shortlink->on_save_post( 1, $post, true );

        // If DOING_AUTOSAVE is true, no API call should happen.
        $this->assertTrue( true );
    }

    public function test_on_save_post_skips_non_publish(): void {
        $this->mockConnected();

        $post = (object) array( 'post_type' => 'post', 'post_status' => 'draft' );
        $shortlink = new \Wp_Houla_Shortlink();
        $shortlink->on_save_post( 1, $post, true );

        $this->assertTrue( true );
    }
}
