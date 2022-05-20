<?php
/**
 * Handles a request to run a wp-cli command in the stack.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `tric` CLI application.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Runs a wp-cli command in the stack or opens a session into the wp-cli container.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} cli [ssh] [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cli plugin list --status=active</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cli ssh</light_cyan>" );

	return;
}

setup_id();
ensure_service_running( 'tric' );
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
} else {
	$status = tric_realtime()( cli_command( $command ) );
}
exit( $status );
