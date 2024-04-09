<?php

namespace StellarWP\Slic;

/**
 * Clears the database and exports a new dump.sql for use with acceptance tests. Optionally, installs a specific
 * WordPress version.
 *
 * @var bool     $is_help    Whether we're handling an `help` request on this command or not.
 * @var string   $subcommand This command.
 * @var callable $args       The argument map closure, as produced by the `args` function.
 * @var string   $cli_name   The current name of the `slic` CLI application.
 */
if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Clears the database and exports a new dump.sql for use with acceptance tests. Optionally, installs a specific WordPress version.

	USAGE:

		<yellow>$cli_name $subcommand <file> [<wp_version>]</yellow>

	EXAMPLES:

		<light_cyan>$cli_name $subcommand tests/_data/dump.sql</light_cyan>
		Export a dump.sql using slic's currently installed version of WordPress.
		
		<light_cyan>$cli_name $subcommand tests/_data/dump.sql latest</light_cyan>
		Export a dump.sql using the latest WordPress version.		

		<light_cyan>$cli_name $subcommand tests/_data/dump.sql 6.4.3</light_cyan>
		Export a dump.sql using WordPress version 6.4.3.
	HELP;

	echo colorize( $help );

	return;
}

// Confirm a target has been set or show an error.
slic_target( false );

// Extract the arguments.
$command = $args( '...' );
$file    = trim( $command[0] );
$version = trim( $command[1] ?? '' );

// Build the path inside the slic container.
$container_path = remove_double_separators( trailingslashit( get_project_container_path() ) . $file );

// Run core update if a version was provided, otherwise run core update-db.
if ( $version ) {
	$update_command = cli_command( [
		'core',
		'update',
		'--force',
		sprintf( '--version=%s', $version ),
	], true );
} else {
	$update_command = cli_command( [
		'core',
		'update-db',
	], true );
}

$commands = [
	cli_command( [
		'cli',
		'cache',
		'clear',
	] ),
	$update_command,
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

echo green( sprintf(
	"Success: Exported to host path '%s'.",
	remove_double_separators( trailingslashit( get_project_local_path() ) . $file )
) );

exit( 0 );
