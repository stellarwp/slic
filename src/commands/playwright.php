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

		Tests and PHP hooks run in <light_cyan>slic</light_cyan>; browser fixtures connect to a temporary Playwright server.
		The browser image matches the installed Playwright CLI version. Dependency ranges in package.json are supported.
		Set <light_cyan>SLIC_PLAYWRIGHT_IMAGE</light_cyan> to override the browser image; its browsers must match the installed CLI.
		Browser installation is unnecessary. Explicit browser.launch() calls still launch locally; see docs/playwright.md.

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

exit( run_playwright( $args( '...' ) ) );
