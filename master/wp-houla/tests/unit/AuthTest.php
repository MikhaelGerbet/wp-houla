<?php
/**
 * Unit tests for Wp_Houla_Auth.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-options.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-auth.php';

class AuthTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        Functions\when( 'get_option' )->justReturn( array() );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'delete_option' )->justReturn( true );
        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'set_transient' )->justReturn( true );
        Functions\when( 'get_transient' )->justReturn( false );
        Functions\when( 'delete_transient' )->justReturn( true );
    }

    public function test_is_connected_returns_false_by_default(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return false;
            return array();
        } );

        $auth = new \Wp_Houla_Auth();
        $this->assertFalse( $auth->is_connected() );
    }

    public function test_is_connected_returns_true_when_authorized(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return true;
            return array();
        } );

        $auth = new \Wp_Houla_Auth();
        $this->assertTrue( $auth->is_connected() );
    }

    public function test_set_connected(): void {
        Functions\expect( 'update_option' )
            ->once()
            ->with( WPHOULA_AUTHORIZED, true )
            ->andReturn( true );

        $auth = new \Wp_Houla_Auth();
        $auth->set_connected( true );
    }

    public function test_get_authorization_url_contains_required_params(): void {
        $auth = new \Wp_Houla_Auth();
        $url  = $auth->get_authorization_url();

        $this->assertStringStartsWith( WPHOULA_OAUTH_URL, $url );
        $this->assertStringContainsString( 'response_type=code', $url );
        $this->assertStringContainsString( 'client_id=' . WPHOULA_OAUTH_CLIENT_ID, $url );
        $this->assertStringContainsString( 'code_challenge=', $url );
        $this->assertStringContainsString( 'code_challenge_method=S256', $url );
        $this->assertStringContainsString( 'state=', $url );
        $this->assertStringContainsString( 'redirect_uri=', $url );
    }

    public function test_get_callback_url(): void {
        $auth = new \Wp_Houla_Auth();
        $url  = $auth->get_callback_url();

        $this->assertStringContainsString( 'wp-houla/v1/oauth/callback', $url );
    }

    public function test_store_tokens_encrypts_and_sets_connected(): void {
        $setConnectedCalled = false;
        $savedValues        = array();

        Functions\expect( 'update_option' )
            ->andReturnUsing( function ( $key, $value ) use ( &$setConnectedCalled, &$savedValues ) {
                if ( $key === WPHOULA_AUTHORIZED ) {
                    $setConnectedCalled = true;
                }
                if ( $key === WPHOULA_OPTIONS ) {
                    $savedValues = $value;
                }
                return true;
            } );

        $auth = new \Wp_Houla_Auth();
        $auth->store_tokens( array(
            'access_token'  => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in'    => 3600,
            'workspace_id'  => 'ws-123',
            'workspace_name' => 'My Store',
            'user_email'    => 'user@example.com',
        ) );

        $this->assertTrue( $setConnectedCalled );
        $this->assertArrayHasKey( 'access_token', $savedValues );
        $this->assertArrayHasKey( 'refresh_token', $savedValues );
        // Tokens should be encrypted (not stored in plain text).
        $this->assertNotEquals( 'test-access-token', $savedValues['access_token'] );
        $this->assertNotEquals( 'test-refresh-token', $savedValues['refresh_token'] );
        // Other fields in plain text.
        $this->assertEquals( 'ws-123', $savedValues['workspace_id'] );
        $this->assertEquals( 'My Store', $savedValues['workspace_name'] );
    }

    public function test_disconnect_clears_tokens(): void {
        $savedValues = array();

        Functions\expect( 'update_option' )
            ->andReturnUsing( function ( $key, $value ) use ( &$savedValues ) {
                if ( $key === WPHOULA_OPTIONS ) {
                    $savedValues = $value;
                }
                return true;
            } );

        $auth = new \Wp_Houla_Auth();
        $auth->disconnect();

        $this->assertEquals( '', $savedValues['access_token'] );
        $this->assertEquals( '', $savedValues['refresh_token'] );
        $this->assertEquals( 0, $savedValues['token_expires_at'] );
        $this->assertEquals( '', $savedValues['workspace_id'] );
    }

    public function test_get_access_token_returns_false_when_not_connected(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_AUTHORIZED ) return false;
            return array();
        } );

        $auth = new \Wp_Houla_Auth();
        $this->assertFalse( $auth->get_access_token() );
    }

    public function test_exchange_code_returns_error_on_wp_error(): void {
        Functions\when( 'wp_remote_post' )->justReturn( new \WP_Error( 'http_error', 'Connection failed' ) );
        Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof \WP_Error;
        } );

        $auth   = new \Wp_Houla_Auth();
        $result = $auth->exchange_code( 'test-code', 'test-verifier' );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_exchange_code_returns_tokens_on_success(): void {
        $responseBody = json_encode( array(
            'access_token'  => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in'    => 3600,
            'workspace_id'  => 'ws-abc',
        ) );

        Functions\when( 'wp_remote_post' )->justReturn( array(
            'response' => array( 'code' => 200 ),
            'body'     => $responseBody,
        ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $auth   = new \Wp_Houla_Auth();
        $result = $auth->exchange_code( 'test-code', 'test-verifier' );

        $this->assertIsArray( $result );
        $this->assertEquals( 'new-access-token', $result['access_token'] );
        $this->assertEquals( 'ws-abc', $result['workspace_id'] );
    }

    public function test_exchange_code_returns_error_on_non_200(): void {
        $responseBody = json_encode( array( 'message' => 'Invalid code' ) );

        Functions\when( 'wp_remote_post' )->justReturn( array(
            'response' => array( 'code' => 400 ),
            'body'     => $responseBody,
        ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 400 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( $responseBody );

        $auth   = new \Wp_Houla_Auth();
        $result = $auth->exchange_code( 'bad-code', 'verifier' );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }
}
