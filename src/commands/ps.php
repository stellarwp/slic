<?php
/**
 * Lists the containers part of the stack.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of the main CLI command, e.g. `slic`.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Lists the containers part of the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [...<options>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} ps --filter name=redis</light_cyan>
		Lists containers and filter by name.
	HELP;

	echo colorize( $help );
	return;
}

slic_realtime()( ['ps'] );
