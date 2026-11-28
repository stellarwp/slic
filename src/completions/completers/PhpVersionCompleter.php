<?php
/**
 * Completer for the 'php-version' command.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class PhpVersionCompleter
 *
 * Returns completions for the 'php-version' command:
 * - Subcommands: set, reset
 * - Common PHP versions for the 'set' subcommand (7.4, 8.0, 8.1, 8.2, 8.3)
 *
 * @package StellarWP\Slic\Completions
 */
class PhpVersionCompleter {
	/**
	 * Available php-version subcommands.
	 *
	 * @var array
	 */
	private const SUBCOMMANDS = [ 'set', 'reset' ];

	/**
	 * Common PHP versions to suggest.
	 *
	 * @var array
	 */
	private const PHP_VERSIONS = [ '7.4', '8.0', '8.1', '8.2', '8.3' ];

	/**
	 * Gets completions for the 'php-version' command.
	 *
	 * @param array  $words        The command words so far.
	 * @param string $current_word The current word being completed.
	 *
	 * @return array List of matching completions.
	 */
	public function get_completions( array $words, string $current_word ): array {
		// Remove 'php-version' from the beginning if present
		if ( isset( $words[0] ) && $words[0] === 'php-version' ) {
			array_shift( $words );
		}

		// If we're completing the first argument after 'php-version'
		// This happens when: no words left, or the first word is empty (trailing space), or we're still typing the first word
		if ( count( $words ) === 0 || empty( $words[0] ) || ( count( $words ) === 1 && $words[0] === $current_word ) ) {
			// Return subcommands
			return $this->filter_by_prefix( self::SUBCOMMANDS, $current_word );
		}

		// If we have a subcommand, check if it's 'set'
		$subcommand = $words[0] ?? '';

		if ( $subcommand === 'set' ) {
			// For 'set', suggest PHP versions if we're completing the second argument
			if ( count( $words ) === 1 || ( count( $words ) === 2 && $words[1] === $current_word ) ) {
				return $this->filter_by_prefix( self::PHP_VERSIONS, $current_word );
			}
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
