<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'SLIC_OBJECT_CACHE_DISABLE' ) ) {
	// Avoid the slic object cache from being globalized.
	define( 'SLIC_OBJECT_CACHE_DISABLE', true );
}

require_once dirname( __DIR__, 4 ) . '/src/classes/Dropin/dropins/object-cache.php';

class Slic_Object_Cache_Test extends TestCase {
	/**
	 * It should be instantiatable
	 *
	 * @test
	 */
	public function should_be_instantiatable(): void {
		$dropin = new Slic_Object_Cache();

		$this->assertInstanceOf( Slic_Object_Cache::class, $dropin );
	}
}
