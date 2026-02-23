<?php
/**
 * Unit tests for Wp_Houla_Api.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-options.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-auth.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-api.php';

class ApiTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return true;
            return array(
                'access_token'     => \Wp_Houla_Options::encrypt( 'test-token' ),
                'refresh_token'    => \Wp_Houla_Options::encrypt( 'test-refresh' ),
                'token_expires_at' => time() + 3600,
            );
        } );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof \WP_Error;
        } );
        Functions\when( 'add_query_arg' )->alias( function ( $args, $url ) {
            return $url . '?' . http_build_query( $args );
        } );
    }

    public function test_get_request_success(): void {
        $responseBody = json_encode( array( 'id' => '123', 'shortUrl' => 'https://hou.la/abc' ) );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => $responseBody,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $api    = new \Wp_Houla_Api();
        $result = $api->get( '/link/123' );

        $this->assertIsArray( $result );
        $this->assertEquals( '123', $result['id'] );
    }

    public function test_post_request_success(): void {
        $responseBody = json_encode( array( 'id' => 'new-link', 'shortUrl' => 'https://hou.la/xyz' ) );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 201 ),
            'body'     => $responseBody,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 201 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $api    = new \Wp_Houla_Api();
        $result = $api->post( '/link', array( 'url' => 'https://example.com' ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'new-link', $result['id'] );
    }

    public function test_request_returns_wp_error_on_network_failure(): void {
        Functions\when( 'wp_remote_request' )->justReturn( new \WP_Error( 'http_error', 'Connection timeout' ) );

        $api    = new \Wp_Houla_Api();
        $result = $api->get( '/link/123' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'Connection timeout', $result->get_error_message() );
    }

    public function test_request_returns_error_on_4xx(): void {
        $responseBody = json_encode( array( 'message' => 'Not found' ) );

        Functions\when( 'wp_remote_request' )->justReturn( array(
            'response' => array( 'code' => 404 ),
            'body'     => $responseBody,
        ) );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 404 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $api    = new \Wp_Houla_Api();
        $result = $api->get( '/link/nonexistent' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'Not found', $result->get_error_message() );
    }

    public function test_returns_error_when_not_connected(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return false;
            return array();
        } );

        $api    = new \Wp_Houla_Api();
        $result = $api->get( '/link' );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_get_with_query_params(): void {
        $responseBody = json_encode( array( 'data' => array() ) );

        Functions\expect( 'wp_remote_request' )
            ->once()
            ->andReturnUsing( function ( $url, $args ) use ( $responseBody ) {
                // URL should contain query parameters.
                $this->assertStringContainsString( 'page=1', $url );
                $this->assertStringContainsString( 'limit=10', $url );
                return array( 'response' => array( 'code' => 200 ), 'body' => $responseBody );
            } );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $api    = new \Wp_Houla_Api();
        $result = $api->get( '/link', array( 'page' => 1, 'limit' => 10 ) );

        $this->assertIsArray( $result );
    }

    public function test_delete_request(): void {
        Functions\expect( 'wp_remote_request' )
            ->once()
            ->andReturnUsing( function ( $url, $args ) {
                $this->assertEquals( 'DELETE', $args['method'] );
                return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
            } );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );

        $api    = new \Wp_Houla_Api();
        $result = $api->delete( '/link/123' );

        $this->assertIsArray( $result );
    }

    public function test_patch_request(): void {
        $responseBody = json_encode( array( 'id' => '123', 'title' => 'Updated' ) );

        Functions\expect( 'wp_remote_request' )
            ->once()
            ->andReturnUsing( function ( $url, $args ) use ( $responseBody ) {
                $this->assertEquals( 'PATCH', $args['method'] );
                $this->assertStringContainsString( '"title":"Updated"', $args['body'] );
                return array( 'response' => array( 'code' => 200 ), 'body' => $responseBody );
            } );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $api    = new \Wp_Houla_Api();
        $result = $api->patch( '/link/123', array( 'title' => 'Updated' ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'Updated', $result['title'] );
    }
}
