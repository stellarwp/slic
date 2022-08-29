<?php
/**
 * Handles a request to run a Codeception command on the current target.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs a Codeception command in the stack, the equivalent of <light_cyan>'codecept ...'</light_cyan>.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} cc [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} cc generate:wpunit wpunit Foo</light_cyan>
		Generate a Codeception wpunit test in the wpunit suite and call it Foo.

	HELP;

	echo colorize( $help );

	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

setup_id();
$codeception_args = $args( '...' );
ensure_service_running( 'slic', codeception_dependencies( $codeception_args ) );

$codeception_config = '';
if ( file_exists( get_project_local_path() . '/codeception.slic.yml' ) ) {
	$codeception_config = '-c codeception.slic.yml';
}

$status = slic_realtime()( array_merge( [
		'exec',
		'--user',
		sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
		'--workdir',
		escapeshellarg( get_project_container_path() ),
		'slic',
		'vendor/bin/codecept ' . $codeception_config,
	], $codeception_args )
);

exit( $status );
