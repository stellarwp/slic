<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Runs a Composer command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <lightcyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} composer [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer install</light_cyan>" );
	return;
}

$using = tric_target_or_fail();
echo light_cyan( "Using {$using}\n" );

$command = $args( '...' );
$pool    = build_command_pool( 'composer', $command, [ 'common' ] );
$status  = execute_command_pool( $pool );

exit( $status );

