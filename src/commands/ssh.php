<?php
/**
 * Opens a bash shell in a running stack service. Differently from the `shell` command, this command will fail if the
 * service is not already running.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Opens a bash shell in a running stack service, defaults to the '{$cli_name}' one.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [<service>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand} wordpress</light_cyan>
		Open a bash shell into the wordpress service.

		<light_cyan>{$cli_name} {$subcommand} chrome</light_cyan>
		Open a bash shell into the chrome service.

		<light_cyan>{$cli_name} {$subcommand} db</light_cyan>
		Open a bash shell into the db service.
	HELP;

	echo colorize( $help );

	return;
}

$service_args = args( [ 'service', '...' ], $args( '...' ), 0 );
$service = $service_args( 'service', 'slic' );

ensure_service_running( $service, [] );

$command = sprintf( 'docker exec -it --user "%d:%d" --workdir %s %s bash',
	getenv( 'SLIC_UID' ),
	getenv( 'SLIC_GID' ),
	escapeshellarg( get_project_container_path() ),
	get_service_id( $service )
);
process_realtime( $command );
