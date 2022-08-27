<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	Starts containers in the stack.

	Usage: <yellow>{$cli_name} {$subcommand} [service]</yellow>

	Examples:

	  <light_cyan>{$cli_name} {$subcommand}</light_cyan>
	    Start all containers in the stack.

	  <light_cyan>{$cli_name} {$subcommand} wordpress</light_cyan>
	    Start the wordpress container in the stack.
	HELP;

	echo colorize( $help );
	return;
}

require __DIR__ . '/up.php';

$service = args( [ 'service' ], $args( '...' ), 0 )( 'service' );

if ( ! $service ) {
	ensure_service_running( 'slic' );
	ensure_service_running( 'wordpress' );
	echo colorize( "\n<green>All containers are running.</green>\n" );
	exit;
}

$exit_status = ensure_service_running( $service );

if ( $exit_status !== 0 ) {
	echo colorize( "\n<red>{$service} failed to start.</red>\n" );
} else {
	echo colorize( "\n<green>{$service} is running.</green>\n" );
}

exit( $exit_status );
