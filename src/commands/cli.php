<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs a wp-cli command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} cli [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cli plugin list --status=active</light_cyan>" );
	return;
}

setup_id();
// Runs a wp-cli command in the stack, using the `cli` service.
$command = $args( '...' );
$status  = tric_realtime()( cli_command( $command ) );

exit( $status );
