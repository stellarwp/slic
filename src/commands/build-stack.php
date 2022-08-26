<?php
/**
 * Handles a request to build the current stack services.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Builds the stack containers that require it, or builds a specific service image.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} build-stack [<service>] [...<args>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-stack</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-stack npm</light_cyan>\n" );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service      = $service_args( 'service', '' );

setup_id();

// Run the command in the Codeception container, exit the same status as the process.
$shell_args = array_merge( [ 'build', $service ], $service_args( '...' ) );
$status     = slic_realtime()( $shell_args );

exit( $status );
