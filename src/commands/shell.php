<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Opens a shell in a stack service, defaults to the 'codeception' one.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a  set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} shell [<service>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} shell chrome</light_cyan>\n" );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service      = $service_args( 'service', 'codeception' );

$using = tric_target();
echo light_cyan( "Using {$using}\n" );

setup_id();

if ( 'codeception' === $service ) {
	// If the shell is for the `codeception` container, then use its built-in shell support.
	$shell_args = array_merge( [ 'run', '--rm', $service, 'shell' ], $service_args( '...' ) );
} else {
	$shell_args = array_merge( [ 'run', '--rm', '--entrypoint', 'shell', $service ], $service_args( '...' ) );
}

// Run the command in the container, exit the same status as the process.
$status = tric_realtime()( $shell_args );

exit( $status );
