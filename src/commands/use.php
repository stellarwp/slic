<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Sets the plugin to use in the tests.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} use <target>[/<subdir>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} use the-events-calendar</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} use event-tickets/common</light_cyan>" );

	return;
}

$sub_args = args( [ 'target' ], $args( '...' ), 0 );
$target   = $sub_args( 'target', false );

$target = (string) ensure_valid_target( $target );

if ( ! empty( $target ) ) {
	slic_switch_target( $target );
}

echo light_cyan( "Using {$target}\n" );
