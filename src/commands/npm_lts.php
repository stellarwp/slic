<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Runs an npm command in the stack using the node LTS container.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} npm_lts [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} npm_lts install</light_cyan>" );
	return;
}

$using = tric_target_or_fail();
echo light_cyan( "Using {$using}\n" );

$command = $args( '...' );
if ( '--pretty' === end( $command ) ) {
	array_pop( $command );
	$status = tric_realtime()( array_merge( [ 'run', '--rm', 'npm_lts' ], $command ) );

	// If there is a status other than 0, we have an error. Bail.
	if ( $status ) {
		exit( $status );
	}

	if ( ! file_exists( tric_plugins_dir( "{$using}/common" ) ) ) {
		return;
	}

	if ( ask( "\nWould you like to run that npm command against common?", 'no' ) ) {
		tric_switch_target( "{$using}/common" );

		echo light_cyan( "Temporarily using " . tric_target() . "\n" );

		$status = tric_realtime()( array_merge( [ 'run', '--rm', 'npm_lts' ], $npm_command ) );

		tric_switch_target( $using );

		echo light_cyan( "Using " . tric_target() ." once again\n" );
	}

	exit( $status );
} else {
	$pool = build_command_pool( 'npm_lts', $command, [ 'common' ] );
	execute_command_pool( $pool );
}
