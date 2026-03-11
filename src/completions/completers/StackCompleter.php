<?php
/**
 * Completer for the 'stack' command.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class StackCompleter
 *
 * Returns completions for the 'stack' command:
 * - Subcommands: list, stop, info
 * - Stack IDs for 'stop' and 'info' subcommands
 *
 * Stack IDs are cached for 1 minute as they can change frequently.
 *
 * @package StellarWP\Slic\Completions
 */
class StackCompleter {
	/**
	 * Cache instance.
	 *
	 * @var CompletionCache
	 */
	private CompletionCache $cache;

	/**
	 * Cache TTL in seconds (1 minute).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 60;

	/**
	 * Cache key for stack IDs.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'stacks';

	/**
	 * Available stack subcommands.
	 *
	 * @var array
	 */
	private const SUBCOMMANDS = [ 'list', 'stop', 'info' ];

	/**
	 * StackCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets completions for the 'stack' command.
	 *
	 * @param array  $words        The command words so far.
	 * @param string $current_word The current word being completed.
	 *
	 * @return array List of matching completions.
	 */
	public function get_completions( array $words, string $current_word ): array {
		// Remove 'stack' from the beginning if present
		if ( isset( $words[0] ) && $words[0] === 'stack' ) {
			array_shift( $words );
		}

		// If we're completing the first argument after 'stack'
		// This happens when: no words left, or the first word is empty (trailing space), or we're still typing the first word
		if ( count( $words ) === 0 || empty( $words[0] ) || ( count( $words ) === 1 && $words[0] === $current_word ) ) {
			// Return subcommands
			return $this->filter_by_prefix( self::SUBCOMMANDS, $current_word );
		}

		// If we have a subcommand, check if it needs stack IDs
		$subcommand = $words[0] ?? '';

		if ( in_array( $subcommand, [ 'stop', 'info' ], true ) ) {
			// Return stack IDs (plus 'all' for stop command)
			$completions = $this->get_stack_ids();

			// Add 'all' option for 'stop' command
			if ( $subcommand === 'stop' ) {
				$completions[] = 'all';
			}

			return $this->filter_by_prefix( $completions, $current_word );
		}

		// No further completions needed
		return [];
	}

	/**
	 * Gets the list of registered stack IDs.
	 *
	 * Checks cache first, then queries the stack registry if needed.
	 *
	 * @return array List of stack IDs.
	 */
	private function get_stack_ids(): array {
		// Try cache first
		$cached = $this->cache->get( self::CACHE_KEY );
		if ( null !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Query the stack registry
		$stack_ids = $this->scan_stacks();

		// Cache the results
		$this->cache->set( self::CACHE_KEY, $stack_ids, self::CACHE_TTL );

		return $stack_ids;
	}

	/**
	 * Scans the stack registry for all registered stacks.
	 *
	 * @return array List of stack IDs (directory paths).
	 */
	private function scan_stacks(): array {
		// Load slic stack functions
		$slic_root = dirname( dirname( dirname( __DIR__ ) ) );
		require_once $slic_root . '/src/stacks.php';

		$stacks = \StellarWP\Slic\slic_stacks_list();
		if ( ! is_array( $stacks ) ) {
			return [];
		}

		// Stack IDs are the keys of the registry
		$stack_ids = array_keys( $stacks );

		sort( $stack_ids, SORT_NATURAL );

		return $stack_ids;
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
