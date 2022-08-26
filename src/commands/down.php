<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Tears down the stack, stopping containers and removing volumes.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} down</light_cyan>" );
	return;
}

$status = slic_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );

exit( $status );
