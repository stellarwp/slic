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

		Runs Playwright commands in the stack. This command requires a use target set using the <light_cyan>use</light_cyan> command.

		Playwright runs in the <light_cyan>mcr.microsoft.com/playwright</light_cyan> image, which already contains the browser.
		The image tag is the <light_cyan>@playwright/test</light_cyan> version in the target's <light_cyan>package.json</light_cyan>, which has to be an exact version such as <light_cyan>1.60.0</light_cyan>.
		Set <light_cyan>SLIC_PLAYWRIGHT_VERSION</light_cyan> to use a different version, or <light_cyan>SLIC_PLAYWRIGHT_IMAGE</light_cyan> to use a different image.

	USAGE:

		<yellow>{$cli_name} playwright [...<commands>]</yellow>

	EXAMPLES:

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

$playwright_args = $args( '...' );

if ( $playwright_args === [ 'install' ] ) {
	// The browser used to be downloaded into the slic container; the Playwright image already contains it.
	echo colorize( 'The <light_cyan>playwright</light_cyan> service image already contains the browser, there is nothing to install.' . PHP_EOL );

	exit( 0 );
}

if ( ! getenv( 'SLIC_PLAYWRIGHT_IMAGE' ) ) {
	$version = getenv( 'SLIC_PLAYWRIGHT_VERSION' );

	if ( empty( $version ) ) {
		$declared = get_target_playwright_dependency();

		if ( $declared === null ) {
			echo magenta( "@playwright/test is not a dependency in the package.json file of {$using}." . PHP_EOL );

			exit( 1 );
		}

		$version = playwright_exact_version( $declared );

		if ( $version === null ) {
			echo magenta( "@playwright/test must be pinned to an exact version in the package.json file of {$using}, e.g. \"1.60.0\"; found \"{$declared}\"." . PHP_EOL );
			echo magenta( 'The Playwright image only contains the browser build for its own version, and a range can install a different version.' . PHP_EOL );

			exit( 1 );
		}
	}

	putenv( 'SLIC_PLAYWRIGHT_IMAGE=mcr.microsoft.com/playwright:v' . ltrim( $version, 'v' ) );
}

echo colorize( 'Playwright image: <light_cyan>' . getenv( 'SLIC_PLAYWRIGHT_IMAGE' ) . '</light_cyan>' . PHP_EOL );

setup_id();

$status = slic_playwright_realtime()( array_merge( [
	'run',
	'--rm',
	'--workdir',
	escapeshellarg( get_project_container_path() ),
	'playwright',
	'node_modules/.bin/playwright',
], $playwright_args ) );

exit( $status );
