<?php
/**
 * Completer for toggle commands (on/off/status).
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class ToggleCompleter
 *
 * Shared completer for commands that have on/off/status options:
 * - airplane-mode
 * - build-prompt
 * - build-subdir
 * - cache
 * - debug
 * - interactive
 *
 * These are static completions cached for 24 hours.
 *
 * @package StellarWP\Slic\Completions
 */
class ToggleCompleter {
	/**
	 * Cache instance.
	 *
	 * @var CompletionCache
	 */
	private CompletionCache $cache;

	/**
	 * Cache TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 86400;

	/**
	 * Available toggle options.
	 *
	 * @var array
	 */
	private const OPTIONS = [ 'on', 'off', 'status' ];

	/**
	 * ToggleCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets toggle option completions, optionally filtered by a prefix.
	 *
	 * @param string $prefix Optional prefix to filter options.
	 *
	 * @return array List of matching options.
	 */
	public function get_completions( string $prefix = '' ): array {
		if ( empty( $prefix ) ) {
			return self::OPTIONS;
		}

		return array_filter( self::OPTIONS, function( $option ) use ( $prefix ) {
			return strpos( $option, $prefix ) === 0;
		} );
	}
}
