<?php
/**
 * Object-cache drop-in by slic.
 *
 * This Object Cache Implementation should only be used in testing context.
 */

class Slic_Object_Cache {
	public const INVALID_SET_KEY = 'invalid-set-key';
	public const INVALID_GET_KEY = 'invalid-get-key';
	public const INVALID_DELETE_KEY = 'invalid-delete-key';
	public const NOT_INTERNAL_VALUE = 'not-internal-value';

	private array $violations = [];
	private bool $multisite;
	private string $blog_prefix;
	private array $cache = [];
	private int $cache_hits = 0;
	private int $cache_misses = 0;

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

	public function __construct() {
		$this->multisite   = is_multisite();
		$this->blog_prefix = $this->multisite ? get_current_blog_id() . ':' : '';
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
	public function add( $key, $data, $group = 'default', $expire = 0 ): bool {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}

		if ( ! $this->is_valid_key( $key ) ) {
			$this->log_violation( self::INVALID_SET_KEY, $key, $this->trace() );

			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$id = $key;
		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$id = $this->blog_prefix . $key;
		}

		if ( $this->_exists( $id, $group ) ) {
			return false;
		}

		return $this->set( $key, $data, $group, (int) $expire );
	}

	public function add_multiple( array $data, $group = '', $expire = 0 ) {
		$values = array();


		foreach ( $data as $key => $value ) {
			$values[ $key ] = $this->add( $key, $value, $group, $expire );
		}

		return $values;
	}

	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		if ( ! $this->is_valid_key( $key ) ) {
			$this->log_violation( self::INVALID_SET_KEY, $key, $this->trace() );
			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$id = $key;
		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$id = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $id, $group ) ) {
			return false;
		}

		return $this->set( $key, $data, $group, (int) $expire );
	}

	private function check_data( $key, $data, $group = 'default' ): void {
		// If the data is a scalar, it's fine.
		if ( is_scalar( $data ) ) {
			return;
		}

		// If the data is an array, recursively check the data of each element.
		if ( is_array( $data ) ) {
			foreach ( $data as $element_key => $element_value ) {
				$this->check_data( $key . '.' . $element_key, $element_value, $group );
			}

			return;
		}

		// If the data is an object, then it must be of an internal class.
		if ( is_object( $data ) ) {
			$reflection_object = new ReflectionObject( $data );
			if ( $reflection_object->isInternal() ) {
				return;
			}

			$this->log_violation( self::NOT_INTERNAL_VALUE, $key, $this->trace() );
		}
	}

	public function set( $key, $data, $group = 'default', $expire = 0 ): bool {
		if ( ! $this->is_valid_key( $key ) ) {
			$this->log_violation( self::INVALID_SET_KEY, $key, $this->trace() );

			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$this->check_data( $key, $data, $group );

		$this->cache[ $group ][ $key ] = $data;

		return true;
	}

	public function set_multiple( array $data, $group = '', $expire = 0 ): array {
	}

	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		if ( ! $this->is_valid_key( $key ) ) {
			$this->log_violation( self::INVALID_GET_KEY, $key, $this->trace() );
			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( $this->_exists( $key, $group ) ) {
			$found = true;
			++ $this->cache_hits;
			if ( is_object( $this->cache[ $group ][ $key ] ) ) {
				return clone $this->cache[ $group ][ $key ];
			}

			return $this->cache[ $group ][ $key ];
		}

		$found = false;
		++ $this->cache_misses;

		return false;
	}

	public function get_multiple( $keys, $group = 'default', $force = false ) {
	}

	public function delete( $key, $group = 'default', $deprecated = false ) {
		if ( ! $this->is_valid_key( $key ) ) {
			$this->log_violation( self::INVALID_DELETE_KEY, $key, $this->trace() );
			return false;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ) {
			$key = $this->blog_prefix . $key;
		}

		if ( ! $this->_exists( $key, $group ) ) {
			return false;
		}

		unset( $this->cache[ $group ][ $key ] );
		return true;
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

	private function is_valid_key( $key ): bool {
		return ! empty( $key ) && ( is_int( $key ) || is_string( $key ) );
	}

	private function log_violation( string $type, $data, array $trace ): void {
		$this->violations[] = [
			'type'  => $type,
			'data'  => $data,
			'trace' => $trace,
		];
	}

	public function get_violations( string $type = null ): array {
		return $type === null ?
			$this->violations
			: array_filter( $this->violations, static fn( $violation ) => $violation['type'] === $type );
	}

	private function trace(): array {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

		// Remove from the head elements until we get to the first call not coming from this class.
		foreach ( $trace as $trace_entry ) {
			$file = $trace_entry['file'];
			if ( $file === __FILE__ ) {
				// It's ok to shift elements out of the array: the foreach uses a copy of the array.
				array_shift( $trace );
				continue;
			}
			break;
		}

		return $trace;
	}

	private function _exists( $key, string $group ): bool {
		return isset( $this->cache[ $group ] ) && ( isset( $this->cache[ $group ][ $key ] ) || array_key_exists( $key, $this->cache[ $group ] ) );
	}
}

if ( ! defined( 'SLIC_OBJECT_CACHE_DISABLE' ) || ! SLIC_OBJECT_CACHE_DISABLE ) {
	$GLOBALS['wp_object_cache'] = new Slic_Object_Cache();
}

