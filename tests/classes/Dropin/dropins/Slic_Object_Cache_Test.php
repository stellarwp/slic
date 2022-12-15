<?php

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use StellarWP\Slic\Tests\Uopz_Functions;

if ( ! defined( 'SLIC_OBJECT_CACHE_DISABLE' ) ) {
	// Avoid the slic object cache from being globalized.
	define( 'SLIC_OBJECT_CACHE_DISABLE', true );
}

// Define some functions defined by WordPress normally.
if ( ! function_exists( 'wp_suspend_cache_addition' ) ) {
	function wp_suspend_cache_addition( $suspend = null ) {
		return false;
	}
}

require_once dirname( __DIR__, 4 ) . '/src/classes/Dropin/dropins/object-cache.php';

class Slic_Object_Cache_Test extends TestCase {
	use Uopz_Functions;
	use MatchesSnapshots;

	/**
	 * It should be instantiatable
	 *
	 * @test
	 */
	public function should_be_instantiatable(): void {
		$dropin = new Slic_Object_Cache();

		$this->assertInstanceOf( Slic_Object_Cache::class, $dropin );
	}

	/**
	 * It should not add value to cache the addition suspended
	 *
	 * @test
	 */
	public function should_not_add_value_to_cache_the_addition_suspended(): void {
		$this->uopz_set_fn_return( 'wp_suspend_cache_addition', true );

		$added = ( new Slic_Object_Cache() )->add( 'key', 'data' );

		$this->assertFalse( $added );
	}

	public function invalid_key_provider():array{
		return [
			'empty string' => [''],
			'null' => [null],
			'false' => [false],
			'true' => [true],
			'array' => [['key']],
			'object' => [(object)['key']],
		];
	}

	/**
	 * It should log violation if key is not valid
	 *
	 * @test
	 * @dataProvider invalid_key_provider
	 */
	public function should_log_violation_if_key_is_not_valid($key): void {
		$cache = new Slic_Object_Cache();

		$cache->add($key,'data');

		$this->assertCount( 1, $cache->get_violations( Slic_Object_Cache::INVALID_KEY ) );
		$this->assertMatchesSnapshot( $cache->get_violations() );
	}
}
