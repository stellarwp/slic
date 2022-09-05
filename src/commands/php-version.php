<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets or displays the PHP version used by slic.

		When setting a version, only include a single dot. E.g. 8.1, not 8.1.10.

	USAGE:

		<yellow>{$cli_name} php-version [set <version>|reset]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} php-version</light_cyan>
		Displays the current PHP version in use.

		<light_cyan>{$cli_name} php-version set 8.1</light_cyan>
		Sets the PHP version to 8.1.
	HELP;
}

$default_version = '7.4';
$current_version = getenv( 'SLIC_PHP_VERSION' ) ?? $default_version;

$command     = $args( '...' );
$sub_command = $command[0] ?? null;

if ( in_array( $sub_command, [ 'set', 'reset' ] ) ) {
	switch ( $sub_command ) {
		case 'set':
			$version = $command[1] ?? null;
			if ( $version === null || ! preg_match( '/^\d+\.\d+$/', $version ) ) {
				echo magenta( "Error: set-version requires a PHP version number with a single dot, e.g. 8.1" . PHP_EOL );
				exit( 1 );
			}
			$run_settings_file = root( '/.env.slic.run' );
			write_env_file( $run_settings_file, [ 'SLIC_PHP_VERSION' => $version ], true );
			echo colorize( "PHP version set to $version" . PHP_EOL );

			$confirm = ask( "Do you want to restart the stack now? ", 'yes');

			if ( $confirm ) {
				rebuild_stack();
				update_stack_images();
			}

			exit( 0 );
		case 'reset':
			$run_settings_file = root( '/.env.slic.run' );
			write_env_file( $run_settings_file, [ 'SLIC_PHP_VERSION' => $default_version ], true );
			echo colorize( "PHP version reset to default: $default_version" . PHP_EOL );

			exit( 0 );
	}
}

echo colorize( "PHP version currently set to <magenta>{$current_version}</magenta>" . PHP_EOL );

exit( 0 );