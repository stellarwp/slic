<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Waits for WordPress to be correctly set up to run a wp-cli command in the stack.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} site-cli [ssh] [...<commands>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} site-cli plugin list --status=active</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} site-cli theme install twentytwenty</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} site-cli _install</light_cyan>" );

	return;
}

setup_id();
$command = $args( '...' );

if ( 'wp' === reset( $command ) ) {
	// If there's an initial `wp` remove it; the user might have called the command with `tric site-cli wp ...`.
	array_shift( $command );
}

/*
 * wp-cli already comes with a `shell` command that will open a PHP shell, same as `php -a`, in it.
 * As much as it would be ideal to use the `shell` sub-command to open a shell... we cannot use the `shell` word.
 */
$open_bash_shell = reset( $command ) === 'bash';

/*
 * This is an "internal" shortcut to quickly install WordPress for the purpose of performing some operations on its
 * structure.
 */
$_install = reset( $command ) === '_install';

if ( ! $open_bash_shell ) {
	if ( $_install ) {
		if ( ! ask( "The _install sub-command is meant for CI use, " .
		            "if you want to install WordPress use the '{$cli_name} site-cli core install' command. " .
		            "\nDo you really want to run it?" ) ) {
			exit( 0 );
		}
		// Drop the `_install` subcommand.
		array_shift( $command );
		// Set up for the quick installation.
		array_push( $command, 'core', 'install', '--path=/var/www/html', '--url=http://wordpress.test',
			'--title=Tric', '--admin_user=admin', '--admin_password=admin', '--admin_email=admin@wordpress.test',
			'--skip-email' );
	}

	// Make sure to prepend with `wp --allow-root`.
	array_unshift( $command, 'wp', '--allow-root' );

	/*
	 * Due to how docker-compose works, the default `CMD` for the `wordpress:cli` image will be overridden as a
	 * consequence of overriding the `entrypoint` configuration parameter of the service.
	 * We cannot, thus, pass the user command directly, we use an env var, `TRIC_SITE_CLI_COMMAND`, to embed the
	 * command we're running into the entrypoint call arguments.
	 *
	 * @link https://docs.docker.com/compose/compose-file/#entrypoint
	 */
	putenv( 'TRIC_SITE_CLI_COMMAND=' . implode( ' ', $command ) );

	$status = tric_realtime()( [ 'run', '--rm', 'site-cli' ] );
} else {
	// What user ID are we running this as?
	$user = getenv( 'DOCKER_RUN_UID' );
	// Do not run the wp-cli container as `root` to avoid a number of file mode issues, run as `www-data` instead.
	$user   = empty( $user ) ? 'www-data' : $user;
	$status = tric_realtime()( [ 'run', '--rm', "--user={$user}", '--entrypoint', 'bash', 'site-cli' ] );
}

exit( $status );
