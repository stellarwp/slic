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
 * - Git branch names of the stack target's repository for 'add', 'merge', and 'remove' subcommands
 *
 * @package StellarWP\Slic\Completions
 */
class WorktreeCompleter {
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
			$branches = $this->scan_branches();
			return $this->filter_by_prefix( $branches, $current_word );
		}

		// No further completions needed
		return [];
	}

	/**
	 * Scans the stack target's repository for all available branches (local and remote).
	 *
	 * @return array List of branch names.
	 */
	private function scan_branches(): array {
		// Load slic functions, as the slic command does, to find the repository `worktree add` works on.
		$slic_root = dirname( dirname( dirname( __DIR__ ) ) );
		require_once $slic_root . '/src/utils.php';
		require_once $slic_root . '/src/includes.php';

		// The same repository src/commands/worktree/add.php uses: the stack target in the base stack directory.
		$stack_id = \StellarWP\Slic\slic_current_stack();
		$target   = $stack_id ? ( \StellarWP\Slic\slic_stacks_get( $stack_id )['target'] ?? null ) : null;
		if ( ! $target ) {
			return [];
		}
		$target_full_path = \StellarWP\Slic\slic_stacks_get_base_stack_id( $stack_id ) . '/' . $target;

		// Get all branches including remote branches
		$output = [];
		$return_var = 0;
		exec( 'git -C ' . escapeshellarg( $target_full_path ) . ' branch -a 2>/dev/null', $output, $return_var );

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

			// Remove the marker of the current branch (*) or of a branch checked out in another worktree (+)
			if ( strpos( $line, '* ' ) === 0 || strpos( $line, '+ ' ) === 0 ) {
				$line = substr( $line, 2 );
			}

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
