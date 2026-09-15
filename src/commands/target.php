<?php
/**
 * Handles the execution of the `target` command.
 *
 * @package StellarWP\Slic
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a set of commands on a set of targets.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );

	return;
}

$targets = [];
do {
	$last_target = ask( 'Target (return when done):', null );
	if ( $last_target && ensure_valid_target( $last_target, false ) ) {
		$targets[] = $last_target;
	}
} while ( ! empty( $last_target ) );

$targets = array_unique( $targets );
$target_types = array_unique( array_map( __NAMESPACE__ . '\\get_target_content_type', $targets ) );

if (
	slic_here_is_site()
	&& site_wordpress_is_in_subdirectory( realpath( getenv( 'SLIC_HERE_DIR' ) ) )
	&& count( $target_types ) > 1
) {
	echo magenta( 'Site, plugin, and theme targets use different mounts and cannot be mixed in one target command.' . PHP_EOL );
	exit( 1 );
}

if ( count( $targets ) > 1 ) {
	$mount_keys          = slic_target_mount_keys();
	$original_environment = [];

	foreach ( array_merge( $mount_keys, [ 'SLIC_MU_PLUGINS_DIR' ] ) as $key ) {
		$original_environment[ $key ] = getenv( $key );
	}

	$profiles = array_map( static function ( $target ) use ( $mount_keys ) {
		foreach ( slic_target_mount_base_keys() as $key => $base_key ) {
			putenv( "{$key}=" . getenv( $base_key ) );
		}

		slic_site_target_environment( explode( '/', $target )[0] );

		$profile = [];
		foreach ( $mount_keys as $key ) {
			$profile[ $key ] = getenv( $key );
		}

		$file    = get_project_local_path( $target ) . '/.env.slic.local';
		$profile = array_merge(
			$profile,
			is_file( $file ) ? array_intersect_key( read_env_file( $file ), array_flip( $mount_keys ) ) : []
		);
		ksort( $profile );

		return serialize( $profile );
	}, $targets );

	foreach ( $original_environment as $key => $value ) {
		false === $value ? putenv( $key ) : putenv( "{$key}={$value}" );
	}

	if ( count( array_unique( $profiles ) ) > 1 ) {
		echo magenta( 'Targets with different bind-mount settings cannot be mixed in one target command.' . PHP_EOL );
		exit( 1 );
	}
}

$command_lines = [];

echo yellow( PHP_EOL . "Targets: " ) . implode( ', ', $targets ) . PHP_EOL . PHP_EOL;

// Allow users to enter a command prefixing it with `slic` or not.
do {
	$last_command_line = trim(
		preg_replace( '/^\\s*slic/', '', ask( 'Command (return when done):', null )
		)
	);
	if ( ! empty( $last_command_line ) ) {
		$command_lines[] = $last_command_line;
	}
} while ( ! empty( $last_command_line ) );

echo yellow( PHP_EOL . "Targets: " ) . implode( ', ', $command_lines ) . PHP_EOL . PHP_EOL;

if ( preg_match( '/^n/i', ask(
	colorize(
		sprintf(
			"<bold>Are you sure you want to run these commands on</bold> <light_cyan>%s</light_cyan>?",
			implode( ', ', $targets )
		)
	),
	'yes'
) ) ) {
	echo PHP_EOL . "Done!";

	return;
}

// Store the previous target, if any.
$previous_target = slic_target();
// The command will fail if a target is not set at this time, so we set one.
slic_switch_target( reset( $targets ) );

$status = 0;
foreach ( $command_lines as $command_line ) {
	$command      = preg_split( '/\\s/', $command_line );
	$base_command = array_shift( $command );
	$status       = execute_command_pool( build_targets_command_pool( $targets, $base_command, $command, [ 'common' ] ) );
	if ( 0 !== (int) $status ) {
		// Restore the previous target, if any.
		slic_switch_target( $previous_target );
		// If any previous command fails, then exit.
		exit( $status );
	}
}

// Restore the previous target, if any.
slic_switch_target( $previous_target );

exit( $status );
