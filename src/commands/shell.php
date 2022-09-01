<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Opens a shell in a stack service, defaults to the 'slic' one.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} shell [<service>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} shell slic</light_cyan>
		Open a shell into the the main {$cli_name} service.

		<light_cyan>{$cli_name} shell chrome</light_cyan>
		Open a shell into the chrome service.
	HELP;

	echo colorize( $help );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service = $service_args( 'service', 'slic' );

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_services_running( [ 'wordpress', 'slic' ] );

setup_id();

$command = sprintf( 'docker exec -it --user "%d:%d" --workdir %s %s bash',
	getenv( 'SLIC_UID' ),
	getenv( 'SLIC_GID' ),
	escapeshellarg( get_project_container_path() ),
	get_service_id( $service )
);
process_realtime( $command );
