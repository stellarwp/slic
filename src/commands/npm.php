<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs an npm command in the stack. If an .nvmrc is present, that version of node will be used.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} npm install</light_cyan>
		Runs npm install in the stack.
	HELP;

	echo colorize( $help );
	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_service_running( 'slic' );

$command = $args( '...' );
if ( '--pretty' === end( $command ) ) {
	array_pop( $command );

	$command = 'npm ' . implode( ' ', $command );
	$command = get_script_command( build_npm_script( $command ) );

	$docker_command = sprintf( 'docker exec --user "%d:%d" --workdir %s %s ' . $command,
		getenv( 'SLIC_UID' ),
		getenv( 'SLIC_GID' ),
		escapeshellarg( get_project_container_path() ),
		get_service_id( 'slic' )
	);
	$status = process_realtime( $docker_command );

	// If there is a status other than 0, we have an error. Bail.
	if ( $status ) {
		exit( $status );
	}

	if ( ! file_exists( slic_plugins_dir( "{$using}/common" ) ) ) {
		return;
	}

	if ( ask( PHP_EOL . "Would you like to run that npm command against common?", 'no' ) ) {
		slic_switch_target( "{$using}/common" );

		echo light_cyan( "Temporarily using " . slic_target() . PHP_EOL );

		$docker_command = sprintf( 'docker exec --user "%d:%d" --workdir %s %s ' . $command,
			getenv( 'SLIC_UID' ),
			getenv( 'SLIC_GID' ),
			escapeshellarg( get_project_container_path() ),
			get_service_id( 'slic' )
		);
		$status = process_realtime( $docker_command );

		slic_switch_target( $using );

		echo light_cyan( "Using " . slic_target() ." once again" . PHP_EOL );
	}

	exit( $status );
} else {
	$command = 'npm ' . implode( ' ', $command );
	$command = get_script_command( build_npm_script( $command ) );

	$pool = build_command_pool( $command, [], [ 'common' ] );
	execute_command_pool( $pool );
}
