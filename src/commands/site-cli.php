<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Waits for WordPress to be correctly set up to run a wp-cli command in the stack.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [ssh] [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} {$subcommand} plugin list --status=active</light_cyan>
		Get the plugin list using wp-cli.

		<light_cyan>{$cli_name} {$subcommand} theme install twentytwenty</light_cyan>
		Install the twentytwenty theme using wp-cli.

		<light_cyan>{$cli_name} {$subcommand} _install</light_cyan>
		Install WP using wp-cli.
	HELP;

	echo colorize( $help );

	return;
}

setup_id();

ensure_services_running( [ 'wordpress', 'slic' ], true );
ensure_wordpress_ready();

$command = $args( '...' );

if ( 'wp' === reset( $command ) ) {
	// If there's an initial `wp` remove it; the user might have called the command with `slic site-cli wp ...`.
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
		$confirm = ask( "The _install sub-command is meant for CI use, " .
		                "if you want to install WordPress use the '{$cli_name} site-cli core install' command. " .
		                PHP_EOL . "Do you really want to run it?", 'yes' );

		if ( ! $confirm ) {
			exit( 0 );
		}

		// Drop the `_install` subcommand, build the rest of the command.
		array_shift( $command );
		// Set up for the quick installation.
		array_push( $command, 'core', 'install', '--path=/var/www/html', '--url=http://wordpress.test',
			'--title=Slic', '--admin_user=admin', '--admin_password=admin', '--admin_email=admin@wordpress.test',
			'--skip-email' );
	}

	// Make sure to prepend with `wp --allow-root`.
	array_unshift( $command, 'wp', '--allow-root' );

	/*
	 * Due to how docker-compose works, the default `CMD` for the `wordpress:cli` image will be overridden as a
	 * consequence of overriding the `entrypoint` configuration parameter of the service.
	 * We cannot, thus, pass the user command directly, we use an env var, `SLIC_SITE_CLI_COMMAND`, to embed the
	 * command we're running into the entrypoint call arguments.
	 *
	 * @link https://docs.docker.com/compose/compose-file/#entrypoint
	 */
	putenv( 'SLIC_SITE_CLI_COMMAND=' . implode( ' ', $command ) );

	$run_configuration = [
		'exec',
		'--user',
		sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
		'--workdir',
		escapeshellarg( get_project_container_path() ),
		'slic',
	];

	$base_command = implode( ' ', $command );

	$run_configuration[] = 'bash -c "' . $base_command . '"';

	$status = slic_realtime()( $run_configuration );
} else {
	// What user ID are we running this as?
	$user = getenv( 'SLIC_UID' );
	// Do not run the wp-cli container as `root` to avoid a number of file mode issues, run as `www-data` instead.
	$user   = empty( $user ) ? 'www-data' : $user;

	$command = sprintf( 'docker exec -it --user "%d:%d" --workdir %s %s bash -c "wp shell"',
		getenv( 'SLIC_UID' ),
		getenv( 'SLIC_GID' ),
		escapeshellarg( get_project_container_path() ),
		get_service_id( 'slic' )
	);
	$status = process_realtime( $command );
}

exit( $status );
