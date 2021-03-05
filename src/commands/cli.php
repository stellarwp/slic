<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Runs a wp-cli command in the stack or opens a session into the wp-cli container.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} cli [ssh] [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cli plugin list --status=active</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} cli ssh</light_cyan>" );
	return;
}

setup_id();
// Runs a wp-cli command in the stack, using the `cli` service.
$command = $args( '...' );
/*
 * wp-cli already comes with a `shell` command that will open a PHP shell, same as `php -a`, in it.
 * As much as it would be ideal to use the `shell` sub-command to open a shell... we cannot use the `shell` word.
 */
$cli_command = reset( $command );
// If the command is `bash` or is empty, then open a shell in the `cli` service.
$open_bash_shell = empty( $cli_command ) || $cli_command === 'bash';
if ( ! $open_bash_shell ) {
	$status = tric_realtime()( cli_command( $command ) );
} else {
	// What user ID are we running this as?
	$user = getenv( 'DOCKER_RUN_UID' );
	// Do not run the wp-cli container as `root` to avoid a number of file mode issues, run as `www-data` instead.
	$user   = empty( $user ) ? 'www-data' : $user;

	$status = tric_realtime()( [ 'run', '-e PS1="wp-cli » "', '--rm', "--user={$user}", '--entrypoint', 'bash', 'cli' ] );

}
exit( $status );
