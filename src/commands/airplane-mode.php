<?php
/**
 * Handles a request to toggle the airplane-mode plugin on and off.
 *
 * @see https://github.com/norcross/airplane-mode
 *
 * @var bool     $is_help Whether we're handling an `help` request on this command or not.
 * @var \Closure $args    The argument map closure, as produced by the `args` function.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Activates or deactivates the airplane-mode plugin.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} airplane-mode (on|off) </light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} airplane-mode on</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} airplane-mode off</light_cyan>\n" );

	return;
}

$toggle = args( [ 'toggle' ], $args( '...' ), 0 )( 'toggle', 'on' );

$activate = $toggle === 'on';

setup_id();

check_status_or(
	tric_process()( cli_command( [ 'plugin', 'is-installed', 'airplane-mode' ] ) ),
	static function () {
		process_realtime( 'git clone https://github.com/norcross/airplane-mode ' . tric_plugins_dir( 'airplane-mode' ) );
	}
);

if ( $activate ) {
	echo "Activating the airplane-mode plugin...\n";
	check_status_or_exit( tric_process()( cli_command( [ 'plugin', 'activate', 'airplane-mode' ] ) ) );
	echo light_cyan( 'Airplane mode plugin activated: all external data calls are now disabled.' );
} else {
	echo "Deactivating the airplane-mode plugin...\n";
	check_status_or_exit( tric_process()( cli_command( [ 'plugin', 'deactivate', 'airplane-mode' ] ) ) );
	echo light_cyan( 'Airplane mode plugin deactivated: external data calls are now enabled.' );
}
