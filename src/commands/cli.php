<?php
/**
 * Handles a request to run a wp-cli command in the stack.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a wp-cli command or opens a `wp-cli shell` in the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [ssh] [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand} plugin list --status=active</light_cyan>
		Run the wp-cli "plugin list" command in the stack.

		<light_cyan>{$cli_name} {$subcommand} ssh</light_cyan>
		Open a shell session with wp-cli.
	HELP;

	echo colorize( $help );

	return;
}

setup_id();
ensure_wordpress_ready();

// Runs a wp-cli command in the stack, using the `cli` service.
$command = $args( '...' );
/*
 * wp-cli already comes with a `shell` command that will open a PHP shell, same as `php -a`, in it.
 * As much as it would be ideal to use the `shell` sub-command to open a shell... we cannot use the `shell` word.
 */
$cli_command = reset( $command );

// If the command is `bash` or `ssh` or is empty, then open a shell in the `cli` service.
if ( empty( $cli_command ) || in_array( $cli_command, [ 'bash', 'ssh' ], true ) ) {
	// @todo replace from ssh command.
	$command = sprintf( 'docker exec -it --user "%d:%d" --workdir %s %s bash -c "wp shell"',
		getenv( 'SLIC_UID' ),
		getenv( 'SLIC_GID' ),
		escapeshellarg( get_project_container_path() ),
		get_service_id( 'slic' )
	);
	$status = process_realtime( $command );
} else {
	$status = slic_realtime()( cli_command( $command ) );
}
exit( $status );
