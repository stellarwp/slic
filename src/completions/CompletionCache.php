<?php
/**
 * File-based cache for terminal completions with TTL support.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class CompletionCache
 *
 * Provides file-based caching with TTL (time-to-live) support for completion data.
 * Cache files are stored in ~/.slic/cache/completions/ directory.
 *
 * @package StellarWP\Slic\Completions
 */
class CompletionCache {
	/**
	 * The cache directory path.
	 *
	 * @var string
	 */
	private string $cache_dir;

	/**
	 * CompletionCache constructor.
	 *
	 * Initializes the cache directory. Uses SLIC_CACHE_DIR environment variable
	 * if set, otherwise defaults to ~/.slic/cache/completions/
	 */
	public function __construct() {
		$base_dir = getenv( 'SLIC_CACHE_DIR' );
		if ( false === $base_dir || empty( $base_dir ) ) {
			$home = getenv( 'HOME' );
			$base_dir = $home . '/.slic/cache';
		}
		$this->cache_dir = $base_dir . '/completions';

		// Ensure cache directory exists
		// If mkdir fails, operations will gracefully degrade (cache reads/writes will fail silently)
		if ( ! file_exists( $this->cache_dir ) ) {
			$created = @mkdir( $this->cache_dir, 0755, true );
			// Verify directory is actually usable after creation attempt
			if ( ! $created || ! is_dir( $this->cache_dir ) || ! is_writable( $this->cache_dir ) ) {
				// Cache will be non-functional, but completions will still work without caching
				$this->cache_dir = '';
			}
		}
	}

	/**
	 * Gets a value from the cache if it exists and is not expired.
	 *
	 * @param string $key The cache key.
	 *
	 * @return mixed|null The cached data or null if not found or expired.
	 */
	public function get( string $key ) {
		$cache_file = $this->get_cache_file( $key );

		if ( empty( $cache_file ) || ! file_exists( $cache_file ) ) {
			return null;
		}

		// Use file locking to prevent reading partially written data
		$fp = @fopen( $cache_file, 'r' );
		if ( false === $fp ) {
			return null;
		}

		$locked = flock( $fp, LOCK_SH );
		if ( ! $locked ) {
			fclose( $fp );
			return null;
		}

		$contents = fread( $fp, filesize( $cache_file ) );
		flock( $fp, LOCK_UN );
		fclose( $fp );

		if ( false === $contents ) {
			return null;
		}

		$data = json_decode( $contents, true );
		if ( ! is_array( $data ) || ! isset( $data['created_at'], $data['ttl'], $data['data'] ) ) {
			// Invalid cache format, delete it
			@unlink( $cache_file );
			return null;
		}

		// Check if expired
		$created_at = (int) $data['created_at'];
		$ttl = (int) $data['ttl'];
		$now = time();

		if ( ( $created_at + $ttl ) < $now ) {
			// Expired, delete it
			@unlink( $cache_file );
			return null;
		}

		return $data['data'];
	}

	/**
	 * Sets a value in the cache with the specified TTL.
	 *
	 * @param string $key  The cache key.
	 * @param mixed  $data The data to cache.
	 * @param int    $ttl  Time-to-live in seconds.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $data, int $ttl ): bool {
		$cache_file = $this->get_cache_file( $key );

		$cache_data = [
			'created_at' => time(),
			'ttl'        => $ttl,
			'data'       => $data,
		];

		$json = json_encode( $cache_data, JSON_PRETTY_PRINT );
		if ( false === $json ) {
			return false;
		}

		// Use file locking to prevent race conditions
		$fp = @fopen( $cache_file, 'w' );
		if ( false === $fp ) {
			return false;
		}

		$locked = flock( $fp, LOCK_EX );
		if ( $locked ) {
			fwrite( $fp, $json );
			flock( $fp, LOCK_UN );
		}
		fclose( $fp );

		return $locked;
	}

	/**
	 * Clears a specific cache entry.
	 *
	 * @param string $key The cache key to clear.
	 *
	 * @return bool True if the file was deleted, false otherwise.
	 */
	public function clear( string $key ): bool {
		$cache_file = $this->get_cache_file( $key );

		if ( file_exists( $cache_file ) ) {
			return @unlink( $cache_file );
		}

		return false;
	}

	/**
	 * Clears all cache entries.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_all(): bool {
		if ( ! is_dir( $this->cache_dir ) ) {
			return true;
		}

		$files = glob( $this->cache_dir . '/*.json' );
		if ( false === $files ) {
			return false;
		}

		$success = true;
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				if ( ! @unlink( $file ) ) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Gets the cache file path for a given key.
	 *
	 * @param string $key The cache key.
	 *
	 * @return string The full path to the cache file, or empty string if cache is disabled.
	 */
	private function get_cache_file( string $key ): string {
		if ( empty( $this->cache_dir ) ) {
			return '';
		}
		// Sanitize the key to prevent directory traversal
		$safe_key = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );
		return $this->cache_dir . '/' . $safe_key . '.json';
	}
}
