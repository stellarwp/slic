<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs a Composer command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} composer [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer install</light_cyan>" );
	return;
}

$using = tric_target();
echo light_cyan( "Using {$using}\n" );

setup_id();
$composer_command = $args( '...' );
$targets          = [ 'target' ];

if (
	file_exists( tric_plugins_dir( "{$using}/common" ) )
	&& ask( "\nWould you also like to run that composer command against common?", 'yes' )
) {
	$targets[] = 'common';
}

$command_process = static function( $target ) use ( $using, $composer_command ) {
	$prefix = light_cyan( $target );

	// Execute composer as the parent.
	if ( 'common' === $target ) {
		tric_switch_target( "{$using}/common" );
		$prefix = yellow( $target );
	}

	$status = tric_realtime()( array_merge( [ 'run', '--rm', 'composer' ], $composer_command ), $prefix );

	if ( 'common' === $target ) {
		tric_switch_target( $using );
	}

	exit( $status );
};

if ( count( $targets ) > 1 ) {
	$status = parallel_process( $targets, $command_process );
	tric_switch_target( $using );
	exit( $status );
}

exit( $command_process( reset( $targets ) ) );

