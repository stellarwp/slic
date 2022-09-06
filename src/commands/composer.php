<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a Composer command in the stack.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} composer [...<commands>] [set-version <1|2>] [get-version] [reset-version]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} composer install</light_cyan>
		Run composer install in the current <light_cyan>use</light_cyan> target.

		<light_cyan>{$cli_name} composer install --no-dev</light_cyan>
		Run composer install --no-dev in the current <light_cyan>use</light_cyan> target.

		<light_cyan>{$cli_name} composer update</light_cyan>
		Run composer update in the current <light_cyan>use</light_cyan> target.

		<light_cyan>{$cli_name} composer set-version 1</light_cyan>
		Sets the current Composer version to 1.

		<light_cyan>{$cli_name} composer get-version</light_cyan>
		Gets the current Composer version.

		<light_cyan>{$cli_name} composer reset-version</light_cyan>
		Resets the Composer version to the default.
	HELP;

	echo colorize( $help );

	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_service_running( 'slic', [] );

$default_version = 1;
$command = $args( '...' );
$sub_command = $command[0] ?? null;
$current_version = getenv( 'SLIC_COMPOSER_VERSION' ) ?? $default_version;

if ( in_array( $sub_command, [ 'set-version', 'get-version', 'reset-version' ] ) ) {
	switch ( $sub_command ) {
		case 'set-version':
			$version = $command[1] ?? null;
			if ( $version === null ) {
				echo magenta( "Error: set-version requires a Composer version number, either 1 or 2." . PHP_EOL );
				exit( 1 );
			}
			$run_settings_file = root( '/.env.slic.run' );
			write_env_file( $run_settings_file, [ 'SLIC_COMPOSER_VERSION' => (int) $version ], true );
			echo colorize( "Composer version set to $version\n" );

			exit( 0 );
		case 'get-version':
			$composer_bin = (int) $current_version === 2 ? 'composer' : 'composer1';

			exit( slic_realtime()( [ 'exec', 'slic', $composer_bin, '--version' ] ) );
		case 'reset-version':
			$version = 1;
			$run_settings_file = root( '/.env.slic.run' );
			write_env_file( $run_settings_file, [ 'SLIC_COMPOSER_VERSION' => (int) $version ], true );
			echo colorize( "Composer version reset to default: $default_version\n" );

			exit( 0 );
	}
}

$composer_bin = (int) $current_version === 2 ? 'composer' : 'composer1';
$pool = build_command_pool( $composer_bin, $command, [ 'common' ] );
$status = execute_command_pool( $pool );

exit( $status );

