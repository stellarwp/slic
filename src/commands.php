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

	$status = slic_realtime( $stack_id )( [ 'down', '--volumes', '--remove-orphans' ] );

	if ( $status === 0 ) {
		echo colorize( PHP_EOL . "✅ <green>All services have been stopped.</green>" . PHP_EOL );

		// Unregister the stack if requested
		if ( $unregister ) {
			require_once __DIR__ . '/stacks.php';
			if ( slic_stacks_unregister( $stack_id ) ) {
				echo colorize( "Stack unregistered: <yellow>{$stack_id}</yellow>" . PHP_EOL );
			}
		}
	} else {
		echo colorize( PHP_EOL . "❌ <red>Some containers failed to stop.</red> Use <light_cyan>slic ps</light_cyan> to see what is still running." . PHP_EOL );
	}

	slic_cache_flush();

	return $status;
}
