<?php
/**
 * Opens and follows the stack logs.
 *
 * @var bool   $is_help  Whether we're handling an `help` request on this command or not.
 * @var string $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Displays the stack logs.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} logs</light_cyan>" );

	return;
}

ensure_service_running( 'slic' );

slic_realtime()( [ 'logs', '--follow' ] );
