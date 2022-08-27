<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Stops containers in the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

$status = slic_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );

exit( $status );
