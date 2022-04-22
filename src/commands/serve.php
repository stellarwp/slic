<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Starts the stack and serves it on http://localhost\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} serve [<port>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} serve 8923</light_cyan>" );
	return;
}

setup_id();
$sub_args = args( [ 'port' ], $args( '...' ), 0 );
$port     = $sub_args( 'port', '8888' );
putenv( 'WORDPRESS_HTTP_PORT=' . $port );
$tric = tric_process();
check_status_or_exit( $tric( [ 'up', '-d', 'wordpress' ] ) );
check_status_or_exit(
	$tric( [ 'run', '--rm', 'site_waiter' ] ),
	magenta( "WordPress site is not available at http://localhost:" . $port
		. "; please check the container health." )
);
echo light_cyan( "WordPress site up and running at http://localhost:{$port}\n" );
