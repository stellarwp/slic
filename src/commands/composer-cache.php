<?php
/**
 * Handles the `composer-cache` command.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of slic CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets or displays the composer cache directory setting.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [(set <dir>|unset)]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand}</light_cyan>
		Shows the current composer cache directory setting.

		<light_cyan>{$cli_name} {$subcommand} unset</light_cyan>
		Removes the composer cache directory setting.

		<light_cyan>{$cli_name} {$subcommand} set /home/person/.cache/composer</light_cyan>
		Sets the composer cache directory to a specific directory.
	HELP;

	echo colorize( $help );

	return;
}

$composer_cache_args = args( [ 'toggle', 'value' ], $args( '...' ), 0 );

slic_handle_composer_cache( $composer_cache_args );
