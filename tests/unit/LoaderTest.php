<?php
/**
 * Unit tests for Wp_Houla_Loader.
 */

namespace WpHoula\Tests\Unit;

use WpHoula\Tests\TestCase;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

require_once dirname( __DIR__, 2 ) . '/includes/class-wp-houla-loader.php';

class LoaderTest extends TestCase {

    public function test_add_action_stores_hook(): void {
        $loader = new \Wp_Houla_Loader();

        $component = new \stdClass();
        $loader->add_action( 'init', $component, 'do_something', 10, 1 );

        // We verify that run() calls add_action.
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'init', array( $component, 'do_something' ), 10, 1 );

        $loader->run();
    }

    public function test_add_filter_stores_hook(): void {
        $loader = new \Wp_Houla_Loader();

        $component = new \stdClass();
        $loader->add_filter( 'the_content', $component, 'filter_content', 5, 2 );

        Functions\expect( 'add_filter' )
            ->once()
            ->with( 'the_content', array( $component, 'filter_content' ), 5, 2 );

        $loader->run();
    }

    public function test_multiple_hooks_registered(): void {
        $loader = new \Wp_Houla_Loader();

        $comp1 = new \stdClass();
        $comp2 = new \stdClass();

        $loader->add_action( 'init', $comp1, 'init_handler' );
        $loader->add_action( 'admin_init', $comp2, 'admin_init_handler' );
        $loader->add_filter( 'the_title', $comp1, 'filter_title' );

        Functions\expect( 'add_action' )->twice();
        Functions\expect( 'add_filter' )->once();

        $loader->run();
    }

    public function test_default_priority_and_args(): void {
        $loader = new \Wp_Houla_Loader();

        $component = new \stdClass();
        $loader->add_action( 'save_post', $component, 'on_save' );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'save_post', array( $component, 'on_save' ), 10, 1 );

        $loader->run();
    }

    public function test_custom_priority(): void {
        $loader = new \Wp_Houla_Loader();

        $component = new \stdClass();
        $loader->add_action( 'save_post', $component, 'on_save', 20, 3 );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'save_post', array( $component, 'on_save' ), 20, 3 );

        $loader->run();
    }
}
