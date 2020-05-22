<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Activates and deactivated interactive mode. While deactivated, prompts will be suppressed and default values will be automatically selected.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} interactive (on|off|status)</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} interactive on</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} interactive off</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} interactive status</light_cyan>\n" );
	return;
}

$interactive_args = args( [ 'toggle' ], $args( '...' ), 0 );

tric_handle_interactive( $interactive_args );

echo colorize( "\n\nToggle this setting by using: <light_cyan>tric interactive [on|off]</light_cyan>\n" );
echo colorize( "- on:  commands with prompts will prompt for input interactively.\n" );
echo colorize( "- off: commands with prompts will NOT prompt and will use defaults.\n" );
