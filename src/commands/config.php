<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Prints the stack configuration as interpolated from the environment.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} config</light_cyan>" );
	return;
}

$using = slic_target();
setup_id();
$status = slic_realtime()( [ 'config' ] );

exit( $status );
