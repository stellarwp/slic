<?php

namespace StellarWP\Slic;

/**
 * Updates a SQL dump file by importing it, running database updates, and exporting it back.
 * Optionally tests the dump with a specific WordPress version before restoring the original version.
 *
 * @var bool     $is_help    Whether we're handling an `help` request on this command or not.
 * @var string   $subcommand This command.
 * @var callable $args       The argument map closure, as produced by the `args` function.
 * @var string   $cli_name   The current name of the `slic` CLI application.
 */
if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Updates a SQL dump file by importing it, running database updates, and exporting it back.
		Optionally upgrades the dump to a specific WordPress version (then restores original version).
		
		The process: imports the dump.sql → optionally installs a WP version → runs core update-db → 
		exports an updated dump.sql → restores original WP version (if version was specified).

	USAGE:

		<yellow>$cli_name $subcommand <file> [<wp_version>]</yellow>

	EXAMPLES:

		<light_cyan>$cli_name $subcommand tests/_data/dump.sql</light_cyan>
		Update the dump file's database structure using the current WordPress version.
		
		<light_cyan>$cli_name $subcommand tests/_data/dump.sql latest</light_cyan>
		Update the dump file to be compatible with the latest WordPress version.

		<light_cyan>$cli_name $subcommand tests/_data/dump.sql 6.4.3</light_cyan>
		Update the dump file to be compatible with WordPress 6.4.3.
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

// Build the paths.
$container_path = remove_double_separators( trailingslashit( get_project_container_path() ) . $file );
$host_path      = remove_double_separators( trailingslashit( get_project_local_path() ) . $file );

// Proper line endings.
$lb = PHP_EOL;

// Check if the existing dump file exists.
if ( ! file_exists( $host_path ) ) {
	echo magenta( sprintf(
		"Error: Dump file '%s' does not exist.\nPlease create it first with: %s wp db export --add-drop-table %s",
		$host_path,
		$cli_name,
		$file
	) );

	exit( 1 );
}

ensure_wordpress_installed();

$original_version = null;

// If $version is passed, capture the existing WP version to restore later.
if ( $version ) {
	$version_command = cli_command( [
		'core',
		'version',
	], true );

	ob_start();
	$result = slic_realtime()( $version_command );
	$output = trim( ob_get_clean() );

	if ( $result === 0 && ! empty( $output ) ) {
		$original_version = $output;
		echo colorize( sprintf( "<light_cyan>Captured current WordPress version: %s</light_cyan>{$lb}", $original_version ) );
	} else {
		echo magenta( 'Warning: Could not capture current WordPress version.' );
	}
}

// Clear the cache.
echo colorize( "<light_cyan>Clearing cache...</light_cyan>{$lb}" );
$result = slic_realtime()( cli_command( [
	'cli',
	'cache',
	'clear',
] ) );

if ( $result !== 0 ) {
	echo magenta( 'Error: Failed to clear cache.' );
	exit( 1 );
}

// Reset database.
echo colorize( "<light_cyan>Resetting database...</light_cyan>{$lb}" );
$result = slic_realtime()( cli_command( [
	'db',
	'reset',
	'--yes',
] ) );

if ( $result !== 0 ) {
	echo magenta( 'Error: Failed to reset database.' );
	exit( 1 );
}

// Import existing dump file.
echo colorize( "<light_cyan>Importing dump file...</light_cyan>{$lb}" );
$result = slic_realtime()( cli_command( [
	'db',
	'import',
	$container_path,
] ) );

if ( $result !== 0 ) {
	echo magenta( sprintf( 'Error: Failed to import dump file: %s', $container_path ) );
	exit( 1 );
}

// If $version is passed, install that WP version.
if ( $version ) {
	echo colorize( sprintf( "<light_cyan>Installing WordPress version %s...</light_cyan>{$lb}", $version ) );
	$result = slic_realtime()( cli_command( [
		'core',
		'update',
		'--force',
		sprintf( '--version=%s', $version ),
	], true ) );

	if ( $result !== 0 ) {
		echo magenta( sprintf( 'Error: Failed to install WordPress version: %s', $version ) );
		exit( 1 );
	}
}

// Run core update-db.
echo colorize( "<light_cyan>Updating database...</light_cyan>{$lb}" );
$result = slic_realtime()( cli_command( [
	'core',
	'update-db',
] ) );

if ( $result !== 0 ) {
	echo magenta( 'Error: Failed to update database.' );
	exit( 1 );
}

// Export the dump.sql to the same file.
echo colorize( "<light_cyan>Exporting updated dump...</light_cyan>{$lb}" );
$result = slic_realtime()( cli_command( [
	'db',
	'export',
	'--add-drop-table',
	$container_path,
] ) );

if ( $result !== 0 ) {
	echo magenta( 'Error: Failed to export database.' );
	exit( 1 );
}

// If $version was passed, restore the original WP version and run core update-db again.
if ( $version && $original_version ) {
	echo colorize( sprintf( "<light_cyan>Restoring original WordPress version %s...</light_cyan>{$lb}", $original_version ) );
	$result = slic_realtime()( cli_command( [
		'core',
		'update',
		'--force',
		sprintf( '--version=%s', $original_version ),
	], true ) );

	if ( $result !== 0 ) {
		echo magenta( sprintf( 'Warning: Failed to restore WordPress version: %s', $original_version ) );
	} else {
		echo colorize( "<light_cyan>Updating database for restored version...</light_cyan>{$lb}" );
		$result = slic_realtime()( cli_command( [
			'core',
			'update-db',
		] ) );

		if ( $result !== 0 ) {
			echo magenta( 'Warning: Failed to update database after restoring version.' );
		}
	}
}

echo green( sprintf(
	"Success: Exported to host path '%s'.",
	$host_path
) );

exit( 0 );
