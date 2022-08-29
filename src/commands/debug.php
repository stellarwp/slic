<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Activates or deactivates {$cli_name} debug output or returns the current debug status.

	USAGE:

		<yellow>{$cli_name} {$subcommand} (on|off)</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand} on</light_cyan>
		Turns debug on.

		<light_cyan>{$cli_name} {$subcommand} off</light_cyan>
		Turns debug off.

		<light_cyan>{$cli_name} {$subcommand} status</light_cyan>
		Returns the current debug status.
	HELP;

	echo colorize( $help );
	return;
}

$toggle = args( [ 'toggle' ], $args( '...' ), 0 )( 'toggle', 'status' );
if ( 'status' === $toggle ) {
	$value = getenv( 'XDE' );
	echo 'Debug status is: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );
	return;
}
$value = 'on' === $toggle ? '1' : '0';
write_env_file( $run_settings_file, [ 'CLI_VERBOSITY' => $value ], true );
echo 'Debug status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );
