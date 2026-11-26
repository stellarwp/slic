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
 * @package StellarWP\Slic\Completions
 */
class ToggleCompleter {
	/**
	 * Available toggle options.
	 *
	 * @var array
	 */
	private const OPTIONS = [ 'on', 'off', 'status' ];

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
