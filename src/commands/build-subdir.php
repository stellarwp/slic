<?php
/**
 * Handles a request to toggle the sub-directory (e.g. Common) build prompt on and off.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	Activates or deactivates whether or not composer/npm build should apply to sub-directories.

	Usage: <light_cyan>{$cli_name} {$subcommand} (on|off|status)</light_cyan>

	Examples:

	  <light_cyan>{$cli_name} {$subcommand} on</light_cyan>
	    Enable application of composer/npm commands in sub-directories.

	  <light_cyan>{$cli_name} {$subcommand} off</light_cyan>
	    Disable application of composer/npm commands in sub-directories.

	  <light_cyan>{$cli_name} {$subcommand} status</light_cyan>
	    Show the current status of running composer/npm commands in sub-directories.
	HELP;

	echo colorize( $help );

	return;
}

$subdir_args = args( [ 'toggle' ], $args( '...' ), 0 );

slic_handle_build_subdir( $subdir_args );

echo colorize( "\n\nToggle this setting by using: <light_cyan>slic build-subdir [on|off]</light_cyan>\n" );
echo colorize( "- on:  composer/npm commands will apply to sub-directories.\n" );
echo colorize( "- off: composer/npm commands will NOT apply to sub-directories.\n" );
