<?php

namespace StellarWP\Slic;

/**
 * Installs a specific version of WordPress, wipes the database and exports a tests dump.sql.
 *
 * @var bool     $is_help    Whether we're handling an `help` request on this command or not.
 * @var string   $subcommand This command.
 * @var callable $args       The argument map closure, as produced by the `args` function.
 * @var string   $cli_name   The current name of the `slic` CLI application.
 */
if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Installs a specific version of WordPress, wipes the database and exports a tests dump.sql.

	USAGE:

		<yellow>$cli_name $subcommand [<wp_version>] [--yes skip confirmation] [--file /custom/path/to/dump.sql]</yellow>

	EXAMPLES:

		<light_cyan>$cli_name $subcommand latest</light_cyan>
		Export a dump.sql using the latest stable WordPress version.
		
		<light_cyan>$cli_name $subcommand nightly</light_cyan>
		Export a dump.sql using the nightly WordPress version.		

		<light_cyan>$cli_name $subcommand 6.4.3</light_cyan>
		Export a dump.sql using WordPress version 6.4.3.
		
		<light_cyan>$cli_name $subcommand nightly --yes</light_cyan>
		Export a dump.sql using the nightly WordPress version and skip any confirmations.		

		<light_cyan>$cli_name $subcommand 6.4.3 --file /home/my-project/dump.sql</light_cyan>
		Export a dump.sql to a custom path, using WordPress version 6.4.3.
	HELP;

	echo colorize( $help );

	return;
}

// Confirm a target has been set or show an error.
slic_target(false);

// Extract the arguments.
$command = $args( '...' );
$version = trim( $command[0] );

$path = get_project_local_path();
$file = $path . DIRECTORY_SEPARATOR . 'tests/_data/dump.sql';
$key  = array_search( '--file', $command, true );

// Export sql file to a custom location.
if ( $key !== false ) {
	$path_key = $key + 1;

	if ( empty( $command[ $path_key ] ) ) {
		echo magenta( 'You must provide a path to a sql file after using the --file option, e.g. /home/plugins/my-project/tests/_data/dump-custom.sql' );
		exit ( 1 );
	}

	$file = trim( $command[ $path_key ] );
}

if ( ! str_starts_with( $file, $path ) ) {
	echo magenta( sprintf(
		'Error: The file sql export path must be under the SLIC_CURRENT_PROJECT directory: %s',
		$path ) );

	echo yellow( sprintf(
		'%sHint: use the `%s here` command to export for a different project.',
		PHP_EOL,
		$cli_name
	) );

	exit( 1 );
}

if ( ! in_array( '--yes', $command, true ) ) {
	$confirm = ask( sprintf(
		'Are you sure you want to install WordPress "%s", wipe the tests database and overwrite %s?',
		$version,
		$file,
	), 'no' );

	if ( ! $confirm ) {
		echo yellow( 'Aborted.' );
		exit( 0 );
	}
}

// Replace host path with container path.
$container_path = str_replace( get_project_local_path(), get_project_container_path(), $file );

$commands = [
	cli_command( [
		'cli',
		'cache',
		'clear',
	] ),
	cli_command( [
		'core',
		'update',
		'--force',
		sprintf( '--version=%s', $version ),
	], true ),
	cli_command( [
		'db',
		'reset',
		'--yes',
	] ),
	cli_command( [
		'core',
		'version',
		'--extra',
	], true ),
	cli_command( [
		'db',
		'export',
		'--add-drop-table',
		$container_path,
	] ),
];

// Execute the command chain.
foreach ( $commands as $arguments ) {
	$result = slic_passive()( $arguments );

	// 0 is success on command line.
	if ( $result === 0 ) {
		continue;
	}

	echo magenta( sprintf( 'Error: Command Failed: %s', implode( ' ', $arguments ) ) );
	exit ( 1 );
}

ensure_wordpress_installed();

echo green( sprintf( "Success: Exported to host path '%s'.", $file ) );

exit( 0 );
