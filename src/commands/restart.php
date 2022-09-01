<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Restarts a container part of the stack.

		The <light_cyan>hard</light_cyan> restart mode will restart the container tearing it down and up again.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [...<service> [(hard|soft)]]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} restart</light_cyan>
		Restart all service in the stack. If a service wasn't running, it starts it.

		<light_cyan>{$cli_name} restart wordpress</light_cyan>
		Restarts the wordpress service. If it wasn't running, it starts it.

		<light_cyan>{$cli_name} restart wordpress hard</light_cyan>
		Hard kills the service and starts it again.
	HELP;

	echo colorize( $help );
	return;
}

setup_id();
$sub_args = args( [ 'service', 'hard' ], $args( '...' ), 0 );
$service  = $sub_args( 'service' );

if ( empty( $service ) ) {
	restart_all_services();
} else {
	$hard     = 'hard' === $sub_args( 'hard', 'soft' );
	restart_service( $service, $service, $hard );
}
