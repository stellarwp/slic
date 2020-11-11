<?php
/**
 * Handles the execution of the `target` command.
 *
 * @packag Tribe\Test
 */

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs a set of commands on a set of targets.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>tric target</light_cyan>\n" );

	return;
}

$targets = [];
do {
	$last_target = ask( 'Target (return when done):', null );
	if ( $last_target && ensure_valid_target( $last_target, false ) ) {
		$targets[] = $last_target;
	}
} while ( ! empty( $last_target ) );

$targets = array_unique( $targets );

$command_lines = [];

echo yellow( "\nTargets: " ) . implode( ', ', $targets ) . "\n\n";

// Allow users to enter a command prefixing it with `tric` or not.
do {
	$last_command_line = trim(
		preg_replace( '/^\\s*tric/', '', ask( 'Command (return when done):', null )
		)
	);
	if ( ! empty( $last_command_line ) ) {
		$command_lines[] = $last_command_line;
	}
} while ( ! empty( $last_command_line ) );

echo yellow( "\nTargets: " ) . implode( ', ', $command_lines ) . "\n\n";

if ( preg_match( '/^n/i', ask(
	colorize(
		sprintf(
			"<bold>Are you sure you want to run these commands on</bold> <light_cyan>%s</light_cyan>?",
			implode( ', ', $targets )
		)
	),
	'yes'
) ) ) {
	echo "\nDone!";

	return;
}

// Store the previous target, if any.
$previous_target = tric_target();
// The command will fail if a target is not set at this time, so we set one.
tric_switch_target( reset( $targets ) );

$status = 0;
foreach ( $command_lines as $command_line ) {
	$command      = preg_split( '/\\s/', $command_line );
	$base_command = array_shift( $command );
	$status       = execute_command_pool( build_targets_command_pool( $targets, $base_command, $command, [ 'common' ] ) );
	if ( 0 !== (int) $status ) {
		// Restore the previous target, if any.
		tric_switch_target( $previous_target );
		// If any previous command fails, then exit.
		exit( $status );
	}
}

// Restore the previous target, if any.
tric_switch_target( $previous_target );

exit( $status );

