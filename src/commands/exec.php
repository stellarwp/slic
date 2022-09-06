<?php
/**
 * Handles a request to run a bash command using the `slic` service.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 * @var string   $cli_name The current name of the main CLI command, e.g. `slic`.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a bash command in the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand} "<commands>"</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand} "whoami"</light_cyan>
		Runs the whoami command in the stack.

	HELP;

	echo colorize( $help );
	return;
}

$using = slic_target_or_fail();

ensure_service_running( 'slic', [] );

setup_id();
$exec_args = $args( '...' );

if ( empty( $exec_args ) ) {
	echo colorize( '<red>Please specify a bash command to execute.</red> Type <light_cyan>slic help exec</light_cyan> for more info.' . PHP_EOL );
	exit( 1 );
}

$exec_command = trim( $exec_args[0] );

ob_start();
$status = slic_realtime()(
	array_merge(
		[
			'exec',
			'--user',
			sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
			'--workdir',
			escapeshellarg( get_project_container_path() ),
			'slic',
			'bash -c',
		],
		[ $exec_command ]
	)
);
$output = ob_get_clean();

echo trim( $output ) . PHP_EOL;

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );

