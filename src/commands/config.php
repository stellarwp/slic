<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Prints the stack configuration as interpolated from the environment.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} config</light_cyan>" );
	return;
}

$using = tric_target();
setup_id();
$status = tric_realtime()( [ 'config' ] );

exit( $status );
