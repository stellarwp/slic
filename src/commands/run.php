<?php
/**
 * Handles the `run` command to `codecept run` a command in the target.
 *
 * Codeception allows specifying an additional configuration file to load with the `-c`, or `--config` option.
 * Since the `codeception.dist.yml` and `codeception.yml` files will always be loaded if they exist, here we set up
 * the command to load the slic configuration file (`coeception.slic.yml`) if it exists.
 * Note: we cannot use `-o "params: .env.testing.slic"` to load slic env file as the `params` key is not overrideable
 * if not using a file; so we scaffold a slic-specific Codeception configuration file to get around it in the `init`
 * command.
 * See the `init` command for more details.
 *
 * @var string   $cli_name The current name of the CLI tool, usually `slic`.
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a Codeception test in the stack, the equivalent of <light_cyan>'codecept run ...'</light_cyan>, or all the tests.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} run [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} run</light_cyan>
		Runs all suites in sequential order.

		<light_cyan>{$cli_name} run wpunit</light_cyan>
		Runs the wpunit suite.
	HELP;

	echo colorize( $help );

	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_service_running( 'slic', codeception_dependencies() );

setup_id();

maybe_generate_htaccess();

// Run the command in the Codeception container.
$root = slic_plugins_dir( slic_target( true ) );

// Object-cache is disruptive in the context of tests; remove the object cache drop-in before running the tests.
$object_cache_dropin = slic_wp_dir( 'wp-content/object-cache.php' );
if ( file_exists( $object_cache_dropin ) ) {
	echo "Removing the object cache drop-in file before tests..." . PHP_EOL;
	if ( ! unlink( $object_cache_dropin ) ) {
		echo magenta( "Failed to remove the {$object_cache_dropin} file." . PHP_EOL );
		exit( 1 );
	}
	echo "Object cache drop-in file removed." . PHP_EOL;
}

/*
 * Check what configuration files we've got available.
 * Depending on the what we have apply them in this order: dist, local, slic.
 * Codeception will apply them in cascading style.
 */
$available_configs_mask = array_sum( [
	file_exists( $root . '/codeception.dist.yml' ),
	2 * file_exists( $root . '/codeception.yml' ),
	4 * file_exists( $root . '/codeception.slic.yml' ),
	8 * file_exists( $root . '/codeception.tric.yml' ),
] );

$config_files = [];

switch ( $available_configs_mask ) {
	case 0:
		// There is no configuration file; needs configuration.
		echo magenta( "No Codeception configuration file found: run the 'init' subcommand to initialize the project." . PHP_EOL );
		exit( 1 );
	case 1:
		// Dist config file only; this will work if `slic` is the dist tool used locally and in CI, CC will pick it up.
	case 2:
		// Local config file only; this will work, CC will pick it up.
	case 3:
		// Dist and local config file only; this will work, CC will pick it up.
		break;
	case 4:
		// Only `slic` configuration file, CC needs to know it should use it.
	case 5:
		// Dist and `slic` configuration file, CC needs to know it should add the slic one at the end.
	case 6:
		// Local and `slic` configuration file, CC needs to know it should add the slic one at the end.
	case 7:
		// Dist, local and `slic` configuration file, CC needs to know it should add the slic one at the end.
		$config_files = [ '-c codeception.slic.yml' ];
		break;
	case 8:
		// Backwards compatibility: Just `tric` config file, CC needs to know it should use it.
	case 9:
		// Backwards compatibility: Dist and `tric` config file, CC needs to know it should add the tric one at the end.
	case 11:
		// Backwards compatibility: Dist, local, and `tric` config file, CC needs to know it should add the tric one at the end.
		$config_files = [ '-c codeception.tric.yml' ];
		break;
	case 12:
		// Backwards compatibility: `slic` and `tric` config file, CC needs to know it should add the slic one at the end.
	case 14:
		// Backwards compatibility: local, `slic`, and `tric` config file, CC needs to know it should add the slic one at the end.
	case 15:
		// Backwards compatibility: Dist, local, `slic`, and `tric` config file, CC needs to know it should add the slic one at the end.
		$config_files = [ '-c codeception.slic.yml' ];
		break;
}
// Add slic configuration file, if existing.
$run_configuration = [
	'exec',
	'--user',
	sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
	'--workdir',
	escapeshellarg( get_project_container_path() ),
	'slic',
];

$base_command = array_merge( [ 'vendor/bin/codecept', ], $config_files, [ 'run' ] );
$run_args     = $args( '...' );
$run_suites   = [];

if ( empty( $run_args ) ) {
	$run_suites = collect_target_suites();
}

// Finally run the command.
if ( empty( $run_suites ) ) {
	// Run the command as per user input.
	$command = array_merge( $base_command, $run_args );
	$run_configuration[] = 'bash -c "' . implode( ' ', $command ) . '"';
	$status = slic_realtime()( $run_configuration );
} else {
	// Run all the suites sequentially, stop at first error.
	foreach ( $run_suites as $suite ) {
		$command = array_merge( $base_command, $suite );
		$run_configuration[] = 'bash -c "' . implode( ' ', $command ) . '"';
		$status = slic_realtime()( $run_configuration );
		if ( $status !== 0 ) {
			exit( $status );
		}
	}
}

exit( $status );
