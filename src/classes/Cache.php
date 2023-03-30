<?php
/**
 * Slic own cache system.
 *
 * @package StellarWP\Slic;
 */

namespace StellarWP\Slic;

/**
 * Class Cache.
 *
 * @package StellarWP\Slic;
 */
class Cache {
	/**
	 * The cache array.
	 *
	 * @var array<string,mixed>
	 */
	private array $cache = [];

	/**
	 * Gets a value from the cache.
	 *
	 * @param string $key
	 * @param bool   $found
	 *
	 * @return mixed|null
	 */
	public function get( string $key, ?bool &$found ) {
		if ( isset( $this->cache[ $key ] ) ) {
			$found = true;

			return $this->cache[ $key ];
		}

		$found = false;

		return null;
	}

	/**
	 * Sets a value in the cache.
	 *
	 * @param string $key
	 * @param        $value
	 *
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->cache[ $key ] = $value;
	}

	/**
	 * Flushes the cache.
	 *
	 * @return void
	 */
	public function flush() {
		$this->cache = [];
	}
}
