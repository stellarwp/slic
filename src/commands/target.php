<?php
/**
 * Handles the execution of the `target` command.
 *
 * @packag Tribe\Test
 */

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs a command on set of targets.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>tric target</light_cyan>\n" );

	return;
}

$targets = [];
do {
	$last_target = ask( 'Target (return when done): ', null );
	if ( $last_target && ensure_valid_target( $last_target, false ) ) {
		$targets[] = $last_target;
	} else {
		continue;
	}
} while ( $last_target );

$targets = array_unique( $targets );

echo yellow( "\nTargets: " ) . implode( ', ', $targets ) . "\n\n";

// Allow users to enter a command prefixing it with `tric` or not.
do {
	$command_line = trim(
		preg_replace( '/^\\s*tric/', '', ask( 'Command: ' )
		)
	);
} while ( ! $command_line );

echo "\n";

if ( preg_match( '/^n/i', ask(
	colorize(
		sprintf(
			"<bold>Are you sure you want to run</bold> <light_cyan>%s</light_cyan> <bold>on</bold> <light_cyan>%s</light_cyan>?",
			$command_line,
			implode( ', ', $targets )
		)
	),
	'yes'
) ) ) {
	echo "\nDone!";

	return;
}

$command      = preg_split( '/\\s/', $command_line );
$base_command = array_shift( $command );

exit( execute_command_pool( build_targets_command_pool( $targets, $base_command, $command, [ 'common' ] ) ) );
