<?php
/**
 * Slic own cache system.
 */

namespace StellarWP\Slic;

/**
 * Gets a value from the cache.
 *
 * @param string $key
 * @param        $default
 *
 * @return mixed|null
 */
function slic_cache_get( string $key, $default = null ) {
	global $slic_cache;

	$value = $slic_cache->get( $key, $found );

	if ( $found ) {
		return $value;
	}

	return $default;
}

/**
 * Sets a value in the cache.
 *
 * @param string $key
 * @param        $value
 *
 * @return void
 */
function slic_cache_set( string $key, $value ) {
	global $slic_cache;

	$slic_cache->set( $key, $value );
}

/**
 * Flushes the cache.
 *
 * @return void
 */
function slic_cache_flush(): void {
	global $slic_cache;
	$slic_cache->flush();
}
