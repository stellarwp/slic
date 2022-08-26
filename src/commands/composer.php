<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Runs a Composer command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} composer [...<commands>] [set-version <1|2>] [get-version] [reset-version]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer install</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer install --no-dev</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer update</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer set-version 1</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer get-version</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer reset-version</light_cyan>" );

	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}\n" );

ensure_service_running( 'slic' );

$default_version = 1;
$command = $args( '...' );
$sub_command = $command[0] ?? null;

$version = null;
switch ( $sub_command ) {
	default:
		$version = getenv( 'SLIC_COMPOSER_VERSION' ) ?? $default_version;
		break;
	case 'set-version':
		$version = $command[1] ?? null;
		if ( $version === null ) {
			echo magenta( "Error: set-version requires a Composer version number, either 1 or 2.\n" );
			exit( 1 );
		}
		$run_settings_file = root( '/.env.slic.run' );
		write_env_file( $run_settings_file, [ 'SLIC_COMPOSER_VERSION' => (int) $version ], true );
		echo colorize( "Composer version set to $version" );

		return;
	case 'get-version':
		$command = [ '--version' ];
		break;
	case 'reset-version':
		$version = 1;
		$run_settings_file = root( '/.env.slic.run' );
		write_env_file( $run_settings_file, [ 'SLIC_COMPOSER_VERSION' => (int) $version ], true );
		echo colorize( "Composer version reset to default: $default_version" );

		return;
}

$composer_bin = (int) $version === 2 ? 'composer' : 'composer1';
$pool = build_command_pool( $composer_bin, $command, [ 'common' ] );
$status = execute_command_pool( $pool );

exit( $status );

