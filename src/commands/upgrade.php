<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Upgrades slic to the latest version.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} upgrade</light_cyan>" );
	return;
}

$today = date( 'Y-m-d' );
chdir( SLIC_ROOT_DIR );
$status = passthru( 'git checkout main && git pull' );

if ( ! $status ) {
	unlink( SLIC_ROOT_DIR . '/.remote-version' );

	$status = passthru( 'php slic update' );
}

exit( $status );
