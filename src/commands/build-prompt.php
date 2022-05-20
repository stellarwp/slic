<?php
/**
 * Handles a request to toggle the build prompt on and off.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `tric` CLI application.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Activates or deactivates whether or not composer/npm build prompts should be provided.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} build-prompt (on|off|status)</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-prompt on</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-prompt off</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} build-prompt status</light_cyan>\n" );
	return;
}

$interactive_args = args( [ 'toggle' ], $args( '...' ), 0 );

tric_handle_build_prompt( $interactive_args );

echo colorize( "\n\nToggle this setting by using: <light_cyan>tric build-prompt [on|off]</light_cyan>\n" );
echo colorize( "- on:  commands will prompt for composer/npm installs.\n" );
echo colorize( "- off: commands will NOT prompt for composer/npm installs.\n" );
