<?php
/**
 * Opens and follows the stack logs.
 *
 * @var bool   $is_help  Whether we're handling an `help` request on this command or not.
 * @var string $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Displays the stack logs.

	USAGE:

		<yellow>{$cli_name} logs</yell>
	HELP;

	echo colorize( $help );

	return;
}

ensure_service_running( 'slic', [] );

slic_realtime()( [ 'logs', '--follow' ] );
