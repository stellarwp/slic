<?php

namespace Tribe\Test;

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

tric_handle_build_subdir( $subdir_args );

echo colorize( "\n\nToggle this setting by using: <light_cyan>tric build-subdir [on|off]</light_cyan>\n" );
echo colorize( "- on:  composer/npm commands will apply to sub-directories.\n" );
echo colorize( "- off: composer/npm commands will NOT apply to sub-directories.\n" );
