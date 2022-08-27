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
	$help = <<< HELP
	Builds the stack containers that require it, or builds a specific service image.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [<service>] [...<args>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand}</light_cyan>
		Builds the stack services.

		<light_cyan>{$cli_name} {$subcommand} wordpress</light_cyan>
		Builds the wordpress container in the stack.
	HELP;

	echo colorize( $help );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service      = $service_args( 'service', '' );

setup_id();

// Run the command in the Codeception container, exit the same status as the process.
$shell_args = array_merge( [ 'build', $service ], $service_args( '...' ) );
$status     = slic_realtime()( $shell_args );

exit( $status );
