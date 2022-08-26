<?php
namespace TEC\Tric;

if ( $is_help ) {
    echo "Returns the IP Address of the host machine from the container perspective.\n";
    echo PHP_EOL;
    echo colorize( "example: <light_cyan>{$cli_name} host-ip</light_cyan>\n" );
    return;
}

// Buffer the output to avoid printing empty blank lines that might mangle the output in quite mode.
ob_start();
//tric_passive()( [ 'run', '--rm', 'host-ip' ] );
$command = sprintf( 'docker exec --user "%d:%d" --workdir %s %s bash -c ". /tric-scripts/host-ip.sh"',
	getenv( 'TRIC_UID' ),
	getenv( 'TRIC_GID' ),
	escapeshellarg( get_project_container_path() ),
	get_service_id( 'tric' )
);
process_realtime( $command );
echo trim( ob_get_clean() );
