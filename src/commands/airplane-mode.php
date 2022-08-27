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
ensure_service_running( 'slic' );
ensure_wordpress_ready();

$ensure_airplane_mode_plugin = static function () {
	$plugin_dir = slic_plugins_dir( 'airplane-mode' );
	if ( ! is_dir( $plugin_dir ) ) {
		$cloned = process_realtime( 'git clone https://github.com/norcross/airplane-mode ' . $plugin_dir );
		if ( $cloned !== 0 ) {
			echo magenta( "Failed to clone the airplane-mode plugin." );
			exit( 1 );
		}
	}
};

check_status_or(
	slic_process()( cli_command( [ 'plugin', 'is-installed', 'airplane-mode' ] ) ),
	$ensure_airplane_mode_plugin
);

if ( $activate ) {
	echo "Activating the airplane-mode plugin...\n";
	check_status_or_exit( slic_process()( cli_command( [ 'plugin', 'activate', 'airplane-mode' ] ) ) );
	echo light_cyan( 'Airplane mode plugin activated: all external data calls are now disabled.' );
} else {
	echo "Deactivating the airplane-mode plugin...\n";
	check_status_or_exit( slic_process()( cli_command( [ 'plugin', 'deactivate', 'airplane-mode' ] ) ) );
	echo light_cyan( 'Airplane mode plugin deactivated: external data calls are now enabled.' );
}
