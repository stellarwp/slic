<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Restarts a container part of the stack.\n";
	echo "The hard restart mode will restart the container tearing it down and up again.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} restart [...<service>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} restart</light_cyan>" );
	echo colorize( "example: <light_cyan>{$cli_name} restart wordpress</light_cyan>" );
	echo colorize( "example: <light_cyan>{$cli_name} restart wordpress hard</light_cyan>" );
	return;
}

setup_id();
$sub_args = args( [ 'service', 'hard' ], $args( '...' ), 0 );
$service  = $sub_args( 'service', 'wordpress' );
$hard     = 'hard' === $sub_args( 'hard', 'soft' );
restart_service( $service, $service, $hard );
