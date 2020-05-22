<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Sets the current plugins directory to be the one used by tric.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} here</light_cyan>\n" );
	echo colorize( "signature: <light_cyan>{$cli_name} here reset</light_cyan>\n" );
	return;
}

$sub_args    = args( [ 'reset' ], $args( '...' ), 0 );
$reset       = $sub_args( 'reset', false );

if ( empty( $reset ) ) {
	$plugins_dir = getcwd();
	if ( false === $plugins_dir ) {
		echo magenta( "Cannot get the current working directory with 'getcwd'; please make sure it's accessible." );
		exit( 1 );
	}
} else {
	$plugins_dir = './_plugins';
}

write_env_file( $run_settings_file, [ 'TRIC_PLUGINS_DIR' => $plugins_dir ], true );

teardown_stack();

echo colorize( "\n<light_cyan>Tric plugin path set to</light_cyan> {$plugins_dir}.\n\n" );
echo colorize( "If this is the first time setting this plugin path, be sure to <light_cyan>tric init <plugin></light_cyan>." );
