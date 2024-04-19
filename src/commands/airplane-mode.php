<?php
/**
 * Handles a request to toggle the airplane-mode plugin on and off.
 *
 * @see https://github.com/norcross/airplane-mode
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Activates or deactivates the airplane-mode plugin. If the plugin is not installed, it will be installed.

	USAGE:

		<yellow>{$cli_name} airplane-mode (on|off) </yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} airplane-mode on</light_cyan>
		Turns airplane mode on.

		<light_cyan>{$cli_name} airplane-mode off</light_cyan>
		Turns airplane mode off.
	HELP;

	echo colorize( $help );

	return;
}

$toggle = args( [ 'toggle' ], $args( '...' ), 0 )( 'toggle', 'on' );

$activate = $toggle === 'on';

setup_id();
ensure_wordpress_ready();

$ensure_airplane_mode_plugin_present = static function () {
	$mu_plugins_dir = slic_mu_plugins_dir();
	$plugin_dir     = $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode';

	if (
		! is_dir( dirname( $mu_plugins_dir ) )
		&& ! mkdir( $concurrentDirectory = dirname( $mu_plugins_dir ), 0755, true )
		&& ! is_dir( $concurrentDirectory )
	) {
		echo magenta( "Failed to create mu-plugins directory {$mu_plugins_dir}." );
		exit( 1 );
	}

	if ( ! is_dir( $plugin_dir ) ) {
		$cloned = process_realtime( 'git clone https://github.com/norcross/airplane-mode ' . $plugin_dir );
		if ( $cloned !== 0 ) {
			echo magenta( "Failed to clone the airplane-mode plugin." );
			exit( 1 );
		}
	}

	if (
		is_file( $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode.php' )
		&& ! unlink( $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode.php' )
	) {
		echo magenta( "Failed to remove the airplane-mode plugin." );
		exit( 1 );
	}

	$loader_code = <<< PHP
<?php

require_once __DIR__ . '/airplane-mode/airplane-mode.php';
PHP;

	if ( ! file_put_contents( $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode.php', $loader_code, LOCK_EX ) ) {
		echo magenta( "Failed to write the airplane-mode plugin." );
		exit( 1 );
	}
};

$ensure_airplane_mode_plugin_removed = static function(){
	$mu_plugins_dir = slic_mu_plugins_dir();
	$plugin_dir     = $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode';

	if ( is_dir( $plugin_dir ) && ! rrmdir( $plugin_dir ) ) {
		echo magenta( "Failed to remove the airplane-mode plugin." );
		exit( 1 );
	}

	if (
		is_file( $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode.php' )
		&& ! unlink( $mu_plugins_dir . DIRECTORY_SEPARATOR . 'airplane-mode.php' )
	) {
		echo magenta( "Failed to remove the airplane-mode plugin." );
		exit( 1 );
	}
};

if ( $activate ) {
	echo "Installing airplane-mode plugin in the must-use plugins directory..." . PHP_EOL;
	$ensure_airplane_mode_plugin_present();
	echo light_cyan( 'Airplane mode plugin installed: all external data calls are now disabled.' );
} else {
	echo "Removing the airplane-mode plugin from the must-use plugins directory..." . PHP_EOL;
	$ensure_airplane_mode_plugin_removed();
	echo light_cyan( 'Airplane mode plugin removed: external data calls are now enabled.' );
}
