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

// Keep the stack and its settings registered; `slic stack stop` is the command that unregisters a stack.
$status = command_stop( null, false );

exit( $status );
