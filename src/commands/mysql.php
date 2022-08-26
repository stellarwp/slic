<?php
/**
 * Opens a mysql shell in the database service.
 *
 * If the database is not currently up, then the command will exit with a failure status.
 *
 * @var bool   $is_help  Whether the current user request is to get help about the command or not.
 * @var string $cli_name The current name of the ClI application, usually `tric`.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Opens a mysql shell in the database service.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} mysql\n" );

	return;
}

ensure_service_running( 'db' );

setup_id();

// Run the command in the container, exit the same status as the process.
$db_root_password = 'password'; // @todo get it from the env
$status           = tric_realtime()( [ 'exec', 'db', 'mysql', "-uroot", "-p{$db_root_password}" ] );

exit( $status );
