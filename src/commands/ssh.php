<?php
/**
 * Opens a bash shell in a running stack service. Differently from the `shell` command, this command will fail if the
 * service is not already running.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `tric` CLI application.
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
$service = $service_args( 'service', 'tric' );

ensure_service_running( $service );

$command = sprintf( 'docker exec -it --user "%d:%d" %s bash',
	getenv( 'TRIC_UID' ),
	getenv( 'TRIC_GID' ),
	get_service_id( $service )
);
process_realtime( $command );
