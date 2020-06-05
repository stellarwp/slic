<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs PHP_CodeSniffer against the current use target.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} phpcs [...<commands>]</light_cyan>\n" );
	return;
}

$using = tric_target();
echo light_cyan( "Using {$using}\n" );

setup_id();
$phpcs_args = $args( '...' );
$status = tric_realtime()( array_merge( [ 'run', '--rm', 'php', 'vendor/bin/phpcs' ], $phpcs_args ) );

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );
