<?php

namespace StellarWP\Slic;

/**
 * @var \Closure $args
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of the main CLI command, e.g. `slic`.
 */
if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets or displays the PHP version used by slic.

		When setting a version, only include a single dot. E.g. 8.1, not 8.1.10.

	USAGE:

		<yellow>$cli_name php-version [set <version>|reset] [--skip-rebuild]</yellow>

	EXAMPLES:

		<light_cyan>$cli_name php-version</light_cyan>
		Displays the current PHP version in use.

		<light_cyan>$cli_name php-version set 8.1</light_cyan>
		Sets the PHP version to 8.1.

		<light_cyan>$cli_name php-version set 8.1 --skip-rebuild</light_cyan>
		Sets the PHP version to 8.1 and doesn't ask to rebuild the stack.
	HELP;

    echo colorize( $help );
    return;
}

$default_version = '7.4';
$current_version = getenv( 'SLIC_PHP_VERSION' ) ?: $default_version;

$command      = $args( '...' );
$sub_command  = $command[0] ?? null;
$skip_rebuild = in_array( '--skip-rebuild', $command, true );
$confirm      = in_array( '-y', $command, true );

if ( in_array( $sub_command, [ 'set', 'reset' ] ) ) {
	switch ( $sub_command ) {
		case 'set':
			$version = $command[1] ?? null;
			if ( $version === null || ! preg_match( '/^\d+\.\d+$/', $version ) ) {
				echo magenta( "Error: set-version requires a PHP version number with a single dot, e.g. 8.1" . PHP_EOL );
				exit( 1 );
			}

			slic_set_php_version( $version, ! $confirm, $skip_rebuild );

			exit( 0 );
		case 'reset':
			echo colorize( "Resetting PHP version to: <yellow>$default_version</yellow>" . PHP_EOL );
			slic_clear_staged_php_flag();
			slic_set_php_version( $default_version, ! $confirm, $skip_rebuild );

			exit( 0 );
	}
}

// Read .env.slic.run to get runtime value.
$run_env_file    = root( '/.env.slic.run' );
$runtime_version = null;
if ( file_exists( $run_env_file ) ) {
	$run_env         = read_env_file( $run_env_file );
	$runtime_version = $run_env['SLIC_PHP_VERSION'] ?? null;
}

$php_message = "PHP version currently set to <yellow>$current_version</yellow>";

// Show mismatch if runtime differs from effective.
if ( $runtime_version && $runtime_version !== $current_version ) {
	$php_message = "PHP version: <yellow>$runtime_version</yellow> [runtime] <yellow>⚠ $current_version [configured]</yellow>";
}

if ( getenv( 'SLIC_PHP_VERSION_STAGED' ) === '1' ) {
	$php_message = "PHP version is staged to switch to <yellow>$current_version</yellow> on the next <light_green>$cli_name use <project></light_green>";
}

echo colorize( $php_message . PHP_EOL );

exit( 0 );
