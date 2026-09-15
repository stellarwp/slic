<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Prints the stack configuration as interpolated from the environment.

	USAGE:

		<yellow>{$cli_name} config</yellow>
	HELP;

	echo colorize( $help );
	return;
}

setup_id();
$status = slic_realtime()( [ 'config' ] );

exit( $status );
