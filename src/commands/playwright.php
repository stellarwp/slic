<?php
/**
 * Handles Playwright commands.
 *
 * @var bool    $is_help  Whether we're handling an `help` request on this command or not.
 * @var Closure $args     The argument map closure, as produced by the `args` function.
 * @var string  $cli_name The current name of the `slic` CLI application.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} playwright [...<commands>]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} playwright install</light_cyan>
		Install Playwright dependencies in the current <light_cyan>use</light_cyan> target.

		<light_cyan>{$cli_name} playwright test</light_cyan>
		Run all Playwright tests following the Playwright configuration in the current <light_cyan>use</light_cyan> target.

		<light_cyan>{$cli_name} playwright test tests/e2e/my-test.spec.ts</light_cyan>
		Run a specific Playwright test file from the root directory of the <light_cyan>use</light_cyan> target.

	HELP;

	echo colorize( $help );

	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_service_running( 'slic' );

setup_id();
$playwright_args = $args( '...' );
$is_install_command = $playwright_args[0] === 'install';

if ( $is_install_command ) {
	// Install commands will need to run as root.
	$user = '0:0';
} else {
	// Other commands will run as the current user.
	$user = sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) );
}

if ( $playwright_args === ['install'] ) {
	// It's exactly the `playwright install` command and nothing more.
	$command = [
		'exec',
		'--user',
		'0:0',
		'--workdir',
		escapeshellarg( get_project_container_path() ),
		'slic',
		'node_modules/.bin/playwright install chromium --with-deps',
	];
} else {
	$command = array_merge( [
		'exec',
		'--user',
		$user,
		'--workdir',
		escapeshellarg( get_project_container_path() ),
		'slic',
		'node_modules/.bin/playwright',
	], $playwright_args );
}

$status = slic_realtime()( $command );

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );
