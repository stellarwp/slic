<?php
/**
 * Handles the `reset` command to reset tric to its initial state.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of tric CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Resets the tool to its initial state configured by the env files.\n" .
	     "Additionally remove the default WP directory.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} reset [wp]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} reset </light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} reset wp</light_cyan>\n" );

	return;
}

$targets = $args( '...' );

if ( in_array( 'wp', $targets, true ) && is_dir( root( '_wordpress' ) ) ) {
	echo "Removing the _wordpress directory ...";

	if ( false === rrmdir( root( '_wordpress' ) ) ) {
		echo magenta( "\nCould not remove the _wordpress directory; remove it manually.\n" );
		exit( 1 );
	}

	if ( ! mkdir( $wp_dir = root( '_wordpress' ) ) && ! is_dir( $wp_dir ) ) {
		echo magenta( "\nCould not create the _wordpress directory; create it manually.\n" );
		exit( 1 );
	}

	echo light_cyan( " done\n" );

	return;
}

$run_settings_file = root( '/.env.tric.run' );
echo "Removing {$run_settings_file} ...";

if ( ! file_exists( $run_settings_file ) ) {
	echo light_cyan( 'Done' );

	return;
}

$removed = unlink( $run_settings_file );

if ( false === $removed ) {
	echo magenta( "Could not remove the {$run_settings_file} file; remove it manually.\n" );
	exit( 1 );
}

echo light_cyan( ' done' );
