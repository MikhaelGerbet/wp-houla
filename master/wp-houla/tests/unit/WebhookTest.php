<?php
/**
 * Unit tests for Wp_Houla_Webhook.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-options.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-orders.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-webhook.php';

class WebhookTest extends TestCase {

    private $webhookSecret = 'test-webhook-secret-123456';

    protected function setUp(): void {
        parent::setUp();

        $secret = $this->webhookSecret;
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) use ( $secret ) {
            if ( $key === WPHOULA_OPTIONS ) {
                return array( 'webhook_secret' => $secret );
            }
            return $default;
        } );
        Functions\when( 'update_option' )->justReturn( true );
        Functions\when( 'wp_parse_args' )->alias( function ( $args, $defaults ) {
            return array_merge( $defaults, $args );
        } );
        Functions\when( 'apply_filters' )->returnArg( 2 );
        Functions\when( 'register_rest_route' )->justReturn( true );
        Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
            return $thing instanceof \WP_Error;
        } );
    }

    private function createRequest( $body, $signature = null ): object {
        $jsonBody = json_encode( $body );
        if ( null === $signature ) {
            $signature = 'sha256=' . hash_hmac( 'sha256', $jsonBody, $this->webhookSecret );
        }

        return new class( $jsonBody, $body, $signature ) {
            private $body;
            private $params;
            private $signature;

            public function __construct( $body, $params, $signature ) {
                $this->body      = $body;
                $this->params    = $params;
                $this->signature = $signature;
            }

            public function get_body() { return $this->body; }
            public function get_json_params() { return $this->params; }
            public function get_header( $name ) {
                if ( strtolower( $name ) === 'x-houla-signature' ) return $this->signature;
                return null;
            }
        };
    }

    public function test_verify_signature_success(): void {
        $body    = array( 'event' => 'order.paid', 'data' => array( 'houla_order_id' => 'ord-1' ) );
        $request = $this->createRequest( $body );

        $webhook = new \Wp_Houla_Webhook();
        $result  = $webhook->verify_signature( $request );

        $this->assertTrue( $result );
    }

    public function test_verify_signature_rejects_missing_header(): void {
        $request = $this->createRequest( array( 'test' => true ), '' );

        // Override to return empty signature.
        $requestEmpty = new class() {
            public function get_body() { return '{"test":true}'; }
            public function get_header( $name ) { return ''; }
        };

        $webhook = new \Wp_Houla_Webhook();
        $result  = $webhook->verify_signature( $requestEmpty );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'wphoula_missing_signature', $result->get_error_code() );
    }

    public function test_verify_signature_rejects_invalid_hash(): void {
        $body    = array( 'event' => 'order.paid', 'data' => array() );
        $request = $this->createRequest( $body, 'sha256=invalid-hash' );

        $webhook = new \Wp_Houla_Webhook();
        $result  = $webhook->verify_signature( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'wphoula_invalid_signature', $result->get_error_code() );
    }

    public function test_verify_signature_accepts_without_prefix(): void {
        $body    = array( 'event' => 'test' );
        $jsonBody = json_encode( $body );
        $hash    = hash_hmac( 'sha256', $jsonBody, $this->webhookSecret );
        $request = $this->createRequest( $body, $hash );

        $webhook = new \Wp_Houla_Webhook();
        $result  = $webhook->verify_signature( $request );

        $this->assertTrue( $result );
    }

    public function test_handle_webhook_rejects_empty_event(): void {
        $body    = array( 'event' => '', 'data' => array( 'id' => 1 ) );
        $request = $this->createRequest( $body );

        $webhook  = new \Wp_Houla_Webhook();
        $response = $webhook->handle_webhook( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $response );
        $this->assertEquals( 400, $response->get_status() );
    }

    public function test_handle_webhook_rejects_unknown_event(): void {
        $body    = array( 'event' => 'unknown.event', 'data' => array( 'id' => 1 ) );
        $request = $this->createRequest( $body );

        $webhook  = new \Wp_Houla_Webhook();
        $response = $webhook->handle_webhook( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $response );
        $this->assertEquals( 400, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    public function test_register_routes(): void {
        Functions\expect( 'register_rest_route' )
            ->once()
            ->with( 'wp-houla/v1', '/webhook', \Mockery::type( 'array' ) );

        $webhook = new \Wp_Houla_Webhook();
        $webhook->register_routes();
    }

    public function test_verify_rejects_when_no_secret_configured(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            if ( $key === WPHOULA_OPTIONS ) {
                return array( 'webhook_secret' => '' );
            }
            return $default;
        } );

        $body    = array( 'event' => 'order.paid' );
        $request = $this->createRequest( $body, 'sha256=some-hash' );

        $webhook = new \Wp_Houla_Webhook();
        $result  = $webhook->verify_signature( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'wphoula_no_secret', $result->get_error_code() );
    }
}
