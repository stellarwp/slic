<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Tears down the stack, stopping containers and removing volumes.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} down</light_cyan>" );
	return;
}

$status = tric_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );

exit( $status );
