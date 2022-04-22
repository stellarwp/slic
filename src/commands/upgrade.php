<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Upgrades tric to the latest version.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} upgrade</light_cyan>" );
	return;
}

$today = date( 'Y-m-d' );
chdir( TRIC_ROOT_DIR );
$status = passthru( 'git checkout main && git pull' );

if ( ! $status ) {
	unlink( TRIC_ROOT_DIR . '/.remote-version' );

	$status = passthru( 'php tric update' );
}

exit( $status );
