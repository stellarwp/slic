<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Builds the stack containers that require it, or builds a specific service image.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} build [<service>] [...<args>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build npm</light_cyan>\n" );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service      = $service_args( 'service', '' );

setup_id();
// Run the command in the Codeception container, exit the same status as the process.
$shell_args = array_merge( [ 'build', $service ], $service_args( '...' ) );
$status     = tric_realtime()( $shell_args );

exit( $status );
