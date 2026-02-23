<?php
/**
 * Integration test: full shortlink generation flow.
 *
 * Simulates the complete pipeline from save_post to API call to meta storage.
 */

namespace WpHoula\Tests\Integration;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-options.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-api.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-shortlink.php';

class ShortlinkFlowTest extends TestCase {

    private $storedMeta = array();

    protected function setUp(): void {
        parent::setUp();

        $this->storedMeta = array();

        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof \WP_Error;
        } );
        Functions\when( 'get_post_types' )->justReturn( array( 'post', 'page' ) );
        Functions\when( 'get_permalink' )->justReturn( 'https://example.com/hello-world/' );
        Functions\when( 'get_the_title' )->justReturn( 'Hello World' );
        Functions\when( 'wp_is_post_revision' )->justReturn( false );
        Functions\when( 'get_post_type' )->justReturn( 'post' );
        Functions\when( 'get_post_status' )->justReturn( 'publish' );

        // Connected state.
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return true;
            return array(
                'access_token'     => \Wp_Houla_Options::encrypt( 'test-token' ),
                'refresh_token'    => \Wp_Houla_Options::encrypt( 'test-refresh' ),
                'token_expires_at' => time() + 3600,
            );
        } );

        $storedMeta = &$this->storedMeta;
        Functions\when( 'update_post_meta' )->alias( function ( $post_id, $key, $value ) use ( &$storedMeta ) {
            $storedMeta[ $key ] = $value;
            return true;
        } );
    }

    public function test_full_shortlink_generation_from_save_post(): void {
        // Mock get_post_meta to return empty (no existing link).
        Functions\when( 'get_post_meta' )->justReturn( '' );

        // Mock API response.
        $apiResponse = json_encode( array(
            'id'        => 'link-abc',
            'shortUrl'  => 'https://hou.la/abc',
            'flashUrl'  => 'https://hou.la/abc/f',
        ) );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => $apiResponse,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $apiResponse );

        // Simulate save_post.
        $post = (object) array( 'post_type' => 'post', 'post_status' => 'publish' );
        $shortlink = new \Wp_Houla_Shortlink();
        $shortlink->on_save_post( 42, $post, true );

        // Verify meta was stored.
        $this->assertEquals( 'https://hou.la/abc', $this->storedMeta['_wphoula_shortlink'] );
        $this->assertEquals( 'link-abc', $this->storedMeta['_wphoula_link_id'] );
        $this->assertNotEmpty( $this->storedMeta['_wphoula_qrcode'] );
    }

    public function test_shortlink_not_generated_for_draft(): void {
        Functions\when( 'get_post_meta' )->justReturn( '' );

        // API should not be called.
        Functions\expect( 'wp_remote_request' )->never();

        $post = (object) array( 'post_type' => 'post', 'post_status' => 'draft' );
        $shortlink = new \Wp_Houla_Shortlink();
        $shortlink->on_save_post( 42, $post, true );

        $this->assertEmpty( $this->storedMeta );
    }

    public function test_shortlink_skips_disallowed_post_type(): void {
        Functions\when( 'get_post_meta' )->justReturn( '' );
        Functions\when( 'get_post_types' )->justReturn( array( 'post' ) ); // Only 'post' allowed.

        Functions\expect( 'wp_remote_request' )->never();

        // 'page' is not in allowed types.
        $post = (object) array( 'post_type' => 'page', 'post_status' => 'publish' );
        $shortlink = new \Wp_Houla_Shortlink();
        $shortlink->on_save_post( 42, $post, true );

        $this->assertEmpty( $this->storedMeta );
    }

    public function test_filter_shortlink_returns_existing_link(): void {
        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            if ( $key === '_wphoula_shortlink' && $post_id === 42 ) return 'https://hou.la/existing';
            return '';
        } );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->filter_get_shortlink( '', 42, 'post', false );

        $this->assertEquals( 'https://hou.la/existing', $result );
    }

    public function test_filter_shortlink_generates_on_first_access(): void {
        $callCount = 0;

        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) use ( &$callCount ) {
            // First call returns empty, subsequent calls return the generated link.
            if ( $key === '_wphoula_shortlink' ) {
                $callCount++;
                return $callCount > 1 ? 'https://hou.la/new' : '';
            }
            return '';
        } );

        $apiResponse = json_encode( array(
            'id'       => 'link-new',
            'shortUrl' => 'https://hou.la/new',
        ) );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => $apiResponse,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $apiResponse );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->filter_get_shortlink( '', 42, 'post', false );

        $this->assertEquals( 'https://hou.la/new', $result );
    }

    public function test_forced_regeneration_creates_new_link(): void {
        // Even with existing link, force=true should call the API.
        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            if ( $key === '_wphoula_shortlink' ) return 'https://hou.la/old';
            return '';
        } );

        $apiResponse = json_encode( array(
            'id'       => 'link-new',
            'shortUrl' => 'https://hou.la/regenerated',
        ) );

        Functions\expect( 'wp_remote_request' )->once()->andReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => $apiResponse,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $apiResponse );

        $shortlink = new \Wp_Houla_Shortlink();
        $result    = $shortlink->generate_shortlink( 42, true );

        $this->assertEquals( 'https://hou.la/regenerated', $result );
        $this->assertEquals( 'https://hou.la/regenerated', $this->storedMeta['_wphoula_shortlink'] );
    }
}
