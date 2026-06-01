<?php
/**
 * Completer for the 'xdebug' command.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class XdebugCompleter
 *
 * Returns completions for the 'xdebug' command:
 * - on, off, status (toggle options)
 * - port, host, key (configuration options - no further completion needed)
 *
 * These are static completions cached for 24 hours.
 *
 * @package StellarWP\Slic\Completions
 */
class XdebugCompleter {
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
	 * Available xdebug options.
	 *
	 * @var array
	 */
	private const OPTIONS = [ 'on', 'off', 'status', 'port', 'host', 'key' ];

	/**
	 * Options that require a value (no further completion).
	 *
	 * @var array
	 */
	private const VALUE_OPTIONS = [ 'port', 'host', 'key' ];

	/**
	 * XdebugCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets xdebug option completions.
	 *
	 * @param array  $words        The command words so far.
	 * @param string $current_word The current word being completed.
	 *
	 * @return array List of matching options.
	 */
	public function get_completions( array $words, string $current_word ): array {
		// Remove 'xdebug' from the beginning if present
		if ( isset( $words[0] ) && $words[0] === 'xdebug' ) {
			array_shift( $words );
		}

		// If we're completing the first argument after 'xdebug'
		// This happens when: no words left, or the first word is empty (trailing space), or we're still typing the first word
		if ( count( $words ) === 0 || empty( $words[0] ) || ( count( $words ) === 1 && $words[0] === $current_word ) ) {
			// Return all options
			return $this->filter_by_prefix( self::OPTIONS, $current_word );
		}

		// If we have an option that requires a value, no further completion
		$option = $words[0] ?? '';
		if ( in_array( $option, self::VALUE_OPTIONS, true ) ) {
			return [];
		}

		// No further completions needed
		return [];
	}

	/**
	 * Filters an array of items by a prefix.
	 *
	 * @param array  $items  The items to filter.
	 * @param string $prefix The prefix to match.
	 *
	 * @return array Filtered items.
	 */
	private function filter_by_prefix( array $items, string $prefix ): array {
		if ( empty( $prefix ) ) {
			return $items;
		}

		return array_filter( $items, function( $item ) use ( $prefix ) {
			return strpos( $item, $prefix ) === 0;
		} );
	}
}
