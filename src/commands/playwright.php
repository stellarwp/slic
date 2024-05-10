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

		Runs Playwright commands in the stack.
		This command will not set up Playwright and its dependencies.
		If Playwright is not already configured, run the <light_cyan>{$cli_name} playwright install</light_cyan> first.

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
$status          = slic_realtime()(
	array_merge(
		[
			'exec',
			'--user',
			sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
			'--workdir',
			escapeshellarg( get_project_container_path() ),
			'slic',
			'node_modules/.bin/playwright',
		],
		$playwright_args
	)
);

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );
