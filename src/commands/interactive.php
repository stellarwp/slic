<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Activates and deactivated interactive mode. While deactivated, prompts will be suppressed and default values will be automatically selected.

	USAGE:

		<yellow>{$cli_name} {$subcommand} (on|off|status)</yellow>

	EXAMPLES:

	<light_cyan>{$cli_name} interactive on</light_cyan>
	Enable prompting for user input.

	<light_cyan>{$cli_name} interactive off</light_cyan>
	Disable prompting for user input.

	<light_cyan>{$cli_name} interactive status</light_cyan>
	Gets the current prompt status.

	HELP;

	echo colorize( $help );
	return;
}

$interactive_args = args( [ 'toggle' ], $args( '...' ), 0 );

slic_handle_interactive( $interactive_args );

echo colorize( PHP_EOL . PHP_EOL . "Toggle this setting by using: <light_cyan>slic interactive [on|off]</light_cyan>" . PHP_EOL );
echo colorize( "- on:  commands with prompts will prompt for input interactively." . PHP_EOL );
echo colorize( "- off: commands with prompts will NOT prompt and will use defaults." . PHP_EOL );
