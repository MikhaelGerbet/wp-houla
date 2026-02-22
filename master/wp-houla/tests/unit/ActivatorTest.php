<?php
/**
 * Unit tests for Wp_Houla_Activator and Wp_Houla_Deactivator.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-activator.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-deactivator.php';

class ActivatorTest extends TestCase {

    public function test_activate_creates_webhook_secret(): void {
        $savedOptions = null;

        Functions\when( 'get_option' )->justReturn( array() );
        Functions\expect( 'update_option' )
            ->once()
            ->andReturnUsing( function ( $key, $value ) use ( &$savedOptions ) {
                $savedOptions = $value;
                return true;
            } );
        Functions\when( 'is_dir' )->justReturn( true );
        Functions\when( 'flush_rewrite_rules' )->justReturn( true );
        Functions\when( 'deactivate_plugins' )->justReturn( true );

        \Wp_Houla_Activator::activate();

        $this->assertNotNull( $savedOptions );
        $this->assertArrayHasKey( 'webhook_secret', $savedOptions );
        $this->assertNotEmpty( $savedOptions['webhook_secret'] );
    }

    public function test_activate_preserves_existing_secret(): void {
        $existingSecret = 'my-existing-secret';

        Functions\when( 'get_option' )->justReturn( array( 'webhook_secret' => $existingSecret ) );
        Functions\when( 'is_dir' )->justReturn( true );
        Functions\when( 'flush_rewrite_rules' )->justReturn( true );

        // update_option should NOT be called since secret already exists.
        Functions\expect( 'update_option' )->never();

        \Wp_Houla_Activator::activate();
    }

    public function test_activate_creates_log_directory(): void {
        Functions\when( 'get_option' )->justReturn( array( 'webhook_secret' => 'existing' ) );
        Functions\when( 'is_dir' )->justReturn( false );
        Functions\when( 'flush_rewrite_rules' )->justReturn( true );
        Functions\expect( 'wp_mkdir_p' )
            ->once()
            ->with( \Mockery::type( 'string' ) )
            ->andReturn( true );
        Functions\when( 'file_put_contents' )->justReturn( true );

        \Wp_Houla_Activator::activate();
    }

    public function test_deactivate_cleans_transients(): void {
        Functions\expect( 'delete_transient' )
            ->once()
            ->with( 'wphoula_batch_sync_running' )
            ->andReturn( true );
        Functions\when( 'flush_rewrite_rules' )->justReturn( true );

        \Wp_Houla_Deactivator::deactivate();
    }
}
