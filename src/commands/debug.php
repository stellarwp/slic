<?php

namespace TEC\Tric;

if ( $is_help ) {
	echo "Activates or deactivates {$cli_name} debug output or returns the current debug status.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} debug (on|off)</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} debug on</light_cyan>" );
	echo colorize( "example: <light_cyan>{$cli_name} debug status</light_cyan>" );
	return;
}

$toggle = args( [ 'toggle' ], $args( '...' ), 0 )( 'toggle', 'status' );
if ( 'status' === $toggle ) {
	$value = getenv( 'XDE' );
	echo 'Debug status is: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );
	return;
}
$value = 'on' === $toggle ? '1' : '0';
write_env_file( $run_settings_file, [ 'CLI_VERBOSITY' => $value ], true );
echo 'Debug status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );
