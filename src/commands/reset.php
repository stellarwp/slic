<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Resets the tool to its initial state configured by the env files.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} reset</light_cyan>" );
	return;
}

if ( ! file_exists( $run_settings_file ) ) {
	echo light_cyan( 'Done' );
	return;
}

$removed = unlink( $run_settings_file );

if ( false === $removed ) {
	echo magenta( "Could not remove the {$run_settings_file} file; remove it manually.\n" );
	exit( 1 );
}

echo light_cyan( 'Done' );
