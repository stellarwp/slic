<?php
/**
 * Completer for the 'worktree' command.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class WorktreeCompleter
 *
 * Returns completions for the 'worktree' command:
 * - Subcommands: add, list, merge, remove, sync
 * - Git branch names for 'add', 'merge', and 'remove' subcommands
 *
 * Branch names are cached for 2 minutes as they can change during development.
 *
 * @package StellarWP\Slic\Completions
 */
class WorktreeCompleter {
	/**
	 * Cache instance.
	 *
	 * @var CompletionCache
	 */
	private CompletionCache $cache;

	/**
	 * Cache TTL in seconds (2 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 120;

	/**
	 * Cache key for git branches.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'branches';

	/**
	 * Available worktree subcommands.
	 *
	 * @var array
	 */
	private const SUBCOMMANDS = [ 'add', 'list', 'merge', 'remove', 'sync' ];

	/**
	 * Subcommands that require branch name completion.
	 *
	 * @var array
	 */
	private const BRANCH_SUBCOMMANDS = [ 'add', 'merge', 'remove' ];

	/**
	 * WorktreeCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets completions for the 'worktree' command.
	 *
	 * @param array  $words        The command words so far.
	 * @param string $current_word The current word being completed.
	 *
	 * @return array List of matching completions.
	 */
	public function get_completions( array $words, string $current_word ): array {
		// Remove 'worktree' from the beginning if present
		if ( isset( $words[0] ) && $words[0] === 'worktree' ) {
			array_shift( $words );
		}

		// If we're completing the first argument after 'worktree'
		// This happens when: no words left, or the first word is empty (trailing space), or we're still typing the first word
		if ( count( $words ) === 0 || empty( $words[0] ) || ( count( $words ) === 1 && $words[0] === $current_word ) ) {
			// Return subcommands
			return $this->filter_by_prefix( self::SUBCOMMANDS, $current_word );
		}

		// If we have a subcommand, check if it needs branch names
		$subcommand = $words[0] ?? '';

		if ( in_array( $subcommand, self::BRANCH_SUBCOMMANDS, true ) ) {
			// Return branch names
			$branches = $this->get_branches();
			return $this->filter_by_prefix( $branches, $current_word );
		}

		// No further completions needed
		return [];
	}

	/**
	 * Gets the list of git branches.
	 *
	 * Checks cache first, then queries git if needed.
	 *
	 * @return array List of branch names.
	 */
	private function get_branches(): array {
		// Try cache first
		$cached = $this->cache->get( self::CACHE_KEY );
		if ( null !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Query git for branches
		$branches = $this->scan_branches();

		// Cache the results
		$this->cache->set( self::CACHE_KEY, $branches, self::CACHE_TTL );

		return $branches;
	}

	/**
	 * Scans git for all available branches (local and remote).
	 *
	 * @return array List of branch names.
	 */
	private function scan_branches(): array {
		// Get all branches including remote branches
		$output = [];
		$return_var = 0;
		exec( 'git branch -a 2>/dev/null', $output, $return_var );

		if ( $return_var !== 0 || empty( $output ) ) {
			// Not in a git repository or git command failed
			return [];
		}

		$branches = [];
		foreach ( $output as $line ) {
			$line = trim( $line );

			// Skip empty lines
			if ( empty( $line ) ) {
				continue;
			}

			// Remove the current branch indicator (*)
			$line = ltrim( $line, '* ' );

			// Skip HEAD references
			if ( strpos( $line, 'HEAD ->' ) !== false ) {
				continue;
			}

			// Clean up remote branch names (remotes/origin/branch-name -> branch-name)
			if ( strpos( $line, 'remotes/' ) === 0 ) {
				$line = preg_replace( '#^remotes/[^/]+/(.+)$#', '$1', $line );
			}

			// Add if not already in the list
			if ( ! in_array( $line, $branches, true ) ) {
				$branches[] = $line;
			}
		}

		sort( $branches, SORT_NATURAL );

		return $branches;
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
