<?php

/**
 * Commands in function form.
 */

namespace StellarWP\Slic;

/**
 * The slic stop command.
 *
 * @return int
 */
function command_stop() : int {
	$status = slic_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );

	if ( $status === 0 ) {
		echo colorize( PHP_EOL . "✅ <green>All services have been stopped.</green>" . PHP_EOL );
	} else {
		echo colorize( PHP_EOL . "❌ <red>Some containers failed to stop.</red> Use <light_cyan>slic ps</light_cyan> to see what is still running." . PHP_EOL );
	}

	return $status;
}