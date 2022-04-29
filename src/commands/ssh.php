<?php
/**
 * Opens a bash shell in a running stack service. Differently from the `shell` command, this command will fail if the
 * service is not already running.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Opens a bash shell in a running stack service, defaults to the 'wordpress' one.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} shell [<service>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} shell wordpress</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} shell chrome</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} shell db</light_cyan>\n" );

	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service      = $service_args( 'service', 'tric' );

tric_realtime()( [
	'exec',
	'--user',
	sprintf( '"%s:%s"', getenv( 'DOCKER_RUN_UID' ), getenv( 'DOCKER_RUN_GID' ) ),
	$service,
	'bash'
] );
