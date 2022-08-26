<?php
/**
 * Handles the `cache` command.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Activates and deactivates object cache support, returns the current object cache status.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} cache (status|on|off)</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cache status</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cache on</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cache off</light_cyan>" );

	return;
}

$cache_args = args( [ 'toggle' ], $args( '...' ), 0 );

$toggle = $cache_args( 'toggle', 'status' );

setup_id();
ensure_service_running( 'redis' );
ensure_service_running( 'slic' );
ensure_wordpress_ready();

// Ensure the plugin is installed.
check_status_or(
	slic_process()( cli_command( [ 'plugin', 'is-installed', 'redis-cache' ] ) ),
	static function () {
		$status = slic_realtime()( cli_command( [ 'plugin', 'install', 'redis-cache' ] ) );
		if ( 0 !== $status ) {
			echo magenta( "Installation of redis-cache plugin failed; see above.\n" );
			exit( 1 );
		}
	}
);

// Ensure the plugin is activated.
check_status_or(
	slic_process()( cli_command( [ 'plugin', 'is-active', 'redis-cache' ] ) ),
	static function () {
		check_status_or_exit( slic_process()( cli_command( [ 'plugin', 'activate', 'redis-cache' ] ) ) );
	}
);

switch ( $toggle ) {
	default:
	case 'status':
		slic_realtime()( cli_command( [ 'redis', 'status' ] ) );
		break;
	case 'on':
		check_status_or_exit( slic_process()( cli_command( [ 'redis', 'enable' ] ) ) );
		slic_realtime()( cli_command( [ 'redis', 'status' ] ) );
		break;
	case 'off':
		check_status_or_exit( slic_process()( cli_command( [ 'redis', 'disable' ] ) ) );
		slic_realtime()( cli_command( [ 'redis', 'status' ] ) );
		break;
}
