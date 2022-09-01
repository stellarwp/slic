<?php
namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Returns the IP Address of the host machine from the container perspective.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

// Buffer the output to avoid printing empty blank lines that might mangle the output in quite mode.
ob_start();
$command = sprintf( 'docker exec --user "%d:%d" --workdir %s %s bash -c ". /slic-scripts/host-ip.sh"',
	getenv( 'SLIC_UID' ),
	getenv( 'SLIC_GID' ),
	escapeshellarg( get_project_container_path() ),
	get_service_id( 'slic' )
);
process_realtime( $command );
echo trim( ob_get_clean() );
