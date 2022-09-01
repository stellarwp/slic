<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:
		Starts containers in the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [service]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand}</light_cyan>
		Start all containers in the stack.

		<light_cyan>{$cli_name} {$subcommand} wordpress</light_cyan>
		Start the wordpress container in the stack.
	HELP;

	echo colorize( $help );
	return;
}

$service = args( [ 'service' ], $args( '...' ), 0 )( 'service' );

if ( ! $service ) {
	start_all_services();
	echo colorize( PHP_EOL . "✅ <green>All containers are running.</green>" . PHP_EOL );
	exit;
}

$exit_status = ensure_service_running( $service );

if ( $exit_status !== 0 ) {
	echo colorize( PHP_EOL . "❌ <red>{$service} failed to start.</red>" . PHP_EOL );
} else {
	echo colorize( PHP_EOL . "✅ <green>{$service} is running.</green>" . PHP_EOL );
}

exit( $exit_status );
