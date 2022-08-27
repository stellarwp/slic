<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Opens a shell in a stack service, defaults to the 'codeception' one.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} shell [<service>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} shell chrome</light_cyan>\n" );
	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service = $service_args( 'service', 'slic' );

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}\n" );

ensure_services_running( [ 'wordpress', 'slic' ] );

setup_id();

$command = sprintf( 'docker exec -it --user "%d:%d" --workdir %s %s bash',
	getenv( 'SLIC_UID' ),
	getenv( 'SLIC_GID' ),
	escapeshellarg( get_project_container_path() ),
	get_service_id( $service )
);
process_realtime( $command );
