<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	Stops containers in the stack.

	Usage: <light_cyan>{$cli_name} {$subcommand}</light_cyan>
	HELP;

	echo colorize( $help );
	return;
}

$status = slic_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );

exit( $status );
