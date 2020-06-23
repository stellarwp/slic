<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs an npm command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} npm [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} npm install</light_cyan>" );
	return;
}

$using = tric_target();
echo light_cyan( "Using {$using}\n" );

$command = $args( '...' );
$status  = tric_run_npm_command( $command, [ 'common' ] );

exit( $status );


