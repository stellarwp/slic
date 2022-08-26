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
	echo "Activates or deactivates whether or not composer/npm build should apply to sub-directories.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} build-subdir (on|off|status)</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-subdir on</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-subdir off</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-subdir status</light_cyan>\n" );

	return;
}

$subdir_args = args( [ 'toggle' ], $args( '...' ), 0 );

slic_handle_build_subdir( $subdir_args );

echo colorize( "\n\nToggle this setting by using: <light_cyan>slic build-subdir [on|off]</light_cyan>\n" );
echo colorize( "- on:  composer/npm commands will apply to sub-directories.\n" );
echo colorize( "- off: composer/npm commands will NOT apply to sub-directories.\n" );
