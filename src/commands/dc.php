<?php
/**
 * Runs a docker compose command in the stack.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

use function StellarWP\Slic\colorize;
use function StellarWP\Slic\slic_realtime;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a docker compose command in the stack.

	USAGE:

		<yellow>{$cli_name} dc [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} dc ps</light_cyan>
		List the containers in the stack.
		
		<light_cyan>{$cli_name} dc up redis</light_cyan>
		Starts the Redis container.

	HELP;

	echo colorize( $help );

	return;
}

$dc_args = $args( '...' );
exit( slic_realtime()( $dc_args ) );
