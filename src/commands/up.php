<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Starts a container part of the stack.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} up <service></light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} up adminer</light_cyan>" );
	return;
}

$service = args( [ 'service' ], $args( '...' ), 0 )( 'service', 'wordpress' );
$status  = tric_realtime()( [ 'up', '-d', $service ] );

exit( $status );
