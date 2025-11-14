<?php

/**
 * Commands in function form.
 */

namespace StellarWP\Slic;

/**
 * The slic stop command.
 *
 * @param string|null $stack_id The stack to stop. If null, uses current stack.
 * @param bool        $unregister Whether to unregister the stack after stopping. Default true.
 * @return int
 */
function command_stop( $stack_id = null, $unregister = true ) : int {
	// Determine which stack to stop
	if ( null === $stack_id ) {
		$stack_id = slic_current_stack();
	}

	if ( null === $stack_id ) {
		echo magenta( "No active stack. Run 'slic here' to create a stack." . PHP_EOL );
		return 1;
	}

	// Get stack state before stopping (for worktree cleanup message)
	require_once __DIR__ . '/stacks.php';
	$stack_state = null;
	if ( $unregister && slic_stacks_is_worktree( $stack_id ) ) {
		$stack_state = slic_stacks_get( $stack_id );
	}

	$status = slic_realtime( $stack_id )( [ 'down', '--volumes', '--remove-orphans' ] );

	if ( $status === 0 ) {
		echo colorize( PHP_EOL . "✅ <green>All services have been stopped.</green>" . PHP_EOL );

		// Unregister the stack if requested
		if ( $unregister ) {
			if ( slic_stacks_unregister( $stack_id ) ) {
				echo colorize( "Stack unregistered: <yellow>{$stack_id}</yellow>" . PHP_EOL );

				// If this was a worktree stack, inform user about cleanup
				if ( $stack_state && slic_stacks_is_worktree( $stack_id ) ) {
					$parsed = slic_stacks_parse_worktree_id( $stack_id );
					if ( $parsed ) {
						echo colorize( PHP_EOL . "<yellow>Note: The git worktree still exists on your filesystem.</yellow>" . PHP_EOL );
						echo colorize( "To remove the worktree and clean up, you can:" . PHP_EOL );

						// Use the branch from state if available, otherwise try to extract it
						$branch_hint = $stack_state['worktree_branch'] ?? null;
						if ( ! $branch_hint ) {
							$worktree_dir = $parsed['worktree_dir'];
							$target = $stack_state['worktree_target'] ?? basename( $parsed['base_path'] );

							$branch_hint = $worktree_dir;
							if ( strpos( $worktree_dir, $target . '-' ) === 0 ) {
								$branch_hint = substr( $worktree_dir, strlen( $target ) + 1 );
								$branch_hint = str_replace( '-', '/', $branch_hint );
							}
						}

						echo colorize( "  • <light_cyan>slic worktree remove {$branch_hint}</light_cyan>" . PHP_EOL );
						echo colorize( "  • <light_cyan>git worktree remove {$parsed['base_path']}/{$parsed['worktree_dir']}</light_cyan>" . PHP_EOL );
					}
				}
			}
		}
	} else {
		echo colorize( PHP_EOL . "❌ <red>Some containers failed to stop.</red> Use <light_cyan>slic ps</light_cyan> to see what is still running." . PHP_EOL );
	}

	slic_cache_flush();

	return $status;
}
