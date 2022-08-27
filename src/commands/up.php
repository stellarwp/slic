<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Starts a container part of the stack.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} up <service></light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} up wordpress</light_cyan>" );
	return;
}

$service = args( [ 'service' ], $args( '...' ), 0 )( 'service' );

if ( ! $service ) {
	ensure_services_running( [ 'wordpress', 'slic' ] );
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