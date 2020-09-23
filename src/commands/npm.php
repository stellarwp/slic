<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs an npm command in the stack using the node 8.9 container.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} npm [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} npm install</light_cyan>" );
	return;
}

$using = tric_target_or_fail();
echo light_cyan( "Using {$using}\n" );

$command = $args( '...' );
$pool    = build_command_pool( 'npm', $command, [ 'common' ] );
$status  = execute_command_pool( $pool );

exit( $status );


