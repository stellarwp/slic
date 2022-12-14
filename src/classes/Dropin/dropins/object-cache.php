<?php
/**
 * Object-cache drop-in by slic.
 *
 * This Object Cache Implementation should only be used in testing context.
 */

class Slic_Object_Cache {
	/*
	 * Backwards compatibility to allow reading the cache properties.
	 */
	public function __get( $name ) {
		return $this->$name;
	}

	/**
	 * Backwards compatibility to allow setting the cache properties.
	 */
	public function __set( $name, $value ) {
		$this->$name = $value;
	}

	/**
	 * Backwards compatibility to allow isset() on the cache properties.
	 */
	public function __isset( $name ) {
		return isset( $this->$name );
	}

	/**
	 * Backwards compatibility to allow unset() on the cache properties.
	 */
	public function __unset( $name ) {
		unset( $this->$name );
	}

	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @since                           2.0.0
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents, in seconds.
	 *                           Default 0 (no expiration).
	 *
	 * @return bool True on success, false if cache key and group already exist.
	 * @uses                            WP_Object_Cache::set()     Sets the data after the checking the cache
	 *                                  contents existence.
	 *
	 * @uses                            WP_Object_Cache::_exists() Checks to see if the cache already has data.
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}

		if ( ! $this->is_valid_cache_key( $key ) ) {
			$this->log_violation( 'invalid-key', $key, $this->trace() );
		}
	}

	public function add_multiple( array $data, $group = '', $expire = 0 ) {
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ) {
	}

	public function set_multiple( array $data, $group = '', $expire = 0 ) {
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
	}

	public function get_multiple( $keys, $group = 'default', $force = false ) {
	}

	public function delete( $key, $group = 'default', $deprecated = false ) {
	}

	public function delete_multiple( array $keys, $group = '' ) {
	}

	public function incr( $key, $offset = 1, $group = 'default' ) {
	}

	public function decr( $key, $offset = 1, $group = 'default' ) {
	}

	public function flush() {
	}

	public function flush_group( $group ) {
	}

	public function add_global_groups( $groups ) {
	}

	public function switch_to_blog( $blog_id ) {
	}

	public function reset() {
	}

	public function stats() {
	}

	private function is_valid_cache_key( $key ): bool {
		return ! empty( $key )
		       && ( is_int( $key ) || is_string( $key ) );
	}
}

if ( ! defined( 'SLIC_OBJECT_CACHE_DISABLE' ) || ! SLIC_OBJECT_CACHE_DISABLE ) {
	$GLOBALS['wp_object_cache'] = new Slic_Object_Cache();
}

