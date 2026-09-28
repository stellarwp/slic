<?php
/**
 * Remote browser orchestration. Test code and PHP hooks always run in slic.
 */
namespace StellarWP\Slic;

/**
 * Build an exec command without changing the persistent slic container's environment.
 *
 * @param string[] $arguments Unescaped Playwright CLI arguments.
 * @param string|null $endpoint The remote browser server WebSocket URL.
 *
 * @return string[] Raw command arguments.
 */
function playwright_exec_command( array $arguments, ?string $endpoint = null ): array {
	$command = [ 'exec', '--workdir', get_project_container_path() ];

	if ( $endpoint !== null ) {
		// Playwright Test reads this variable for its built-in browser fixtures.
		// Pass it only to this exec process so concurrent runs keep their own endpoints.
		$command = array_merge( $command, [ '--env', 'PW_TEST_CONNECT_WS_ENDPOINT=' . $endpoint ] );
	}

	return array_merge( $command, [ 'slic', 'node_modules/.bin/playwright' ], $arguments );
}

/**
 * Wait for the per-invocation server's health check, with a bounded startup time.
 *
 * @param string $name The unique browser container name.
 * @param int $timeout Maximum time to wait, in seconds.
 *
 * @return bool Whether the server became healthy.
 */
function wait_for_playwright_server( string $name, int $timeout = 30 ): bool {
	$deadline = microtime( true ) + $timeout;

	do {
		// The Docker template returns the health status while running, or "exited"
		// after the server stops. A running container alone does not mean it is ready.
		$state  = process_argv( array_merge( docker_binary_argv(), [ 'inspect', '--format', '{{if .State.Running}}{{.State.Health.Status}}{{else}}exited{{end}}', $name ] ) );
		$health = trim( $state['stdout'] );

		if ( $state['status'] !== 0 || in_array( $health, [ 'unhealthy', 'exited' ], true ) ) {
			return false;
		}

		if ( $health === 'healthy' ) {
			return true;
		}

		usleep( 200000 );
	} while ( microtime( true ) < $deadline );

	return false;
}

/**
 * Run the project CLI, using a separate browser server for test execution.
 *
 * @param string[] $arguments Unescaped Playwright CLI arguments.
 *
 * @return int The test or startup exit status.
 */
function run_playwright( array $arguments ): int {
	$subcommand = $arguments[0] ?? '';

	if ( $subcommand === 'install' ) {
		// Do not silently promise installation of Chrome/Edge channels absent from the image.
		$supported = [ 'install', 'chromium', 'firefox', 'webkit', '--with-deps', '--only-shell', '--no-shell' ];

		if ( array_diff( $arguments, $supported ) ) {
			echo magenta( "Remote installation is only a no-op for bundled Chromium, Firefox and WebKit. Custom browser channels and other install options are not supported.\n" );

			return 1;
		}

		echo "The remote Playwright image already contains its browsers and system dependencies; no installation is needed.\n";

		return 0;
	}

	$status = ensure_service_running( 'slic' );

	if ( $status !== 0 ) {
		return $status;
	}

	// Help, reports and test discovery do not need a browser server.
	if ( $subcommand !== 'test' || array_intersect( [ '--list', '--help', '-h' ], $arguments ) ) {
		return docker_compose_argv_realtime( playwright_exec_command( $arguments ), slic_stack_argv() );
	}

	// Query the actual executable in the same container and directory used by the tests.
	// This works with ranges, stale manifests and package-manager symlinks without parsing lockfiles.
	$version_command = playwright_exec_command( [ '--version' ] );
	$installed       = docker_compose_argv( $version_command, slic_stack_argv() );

	// Expect CLI output such as "Version 1.60.0" and capture the numeric version
	// for the browser image tag. Reject failed commands, unexpected output and
	// prereleases rather than guessing an image that may contain incompatible browsers.
	if ( $installed['status'] !== 0 || ! preg_match( '/^Version (\d+\.\d+\.\d+)$/', trim( $installed['stdout'] ), $matches ) ) {
		echo magenta( "Could not resolve the installed Playwright CLI. Run your project's dependency install (for example, slic npm ci), then retry. A stable Playwright release is required.\n" );

		return 1;
	}

	$version = $matches[1];

	if ( ! getenv( 'SLIC_PLAYWRIGHT_IMAGE' ) ) {
		putenv( 'SLIC_PLAYWRIGHT_IMAGE=mcr.microsoft.com/playwright:v' . $version );
	}

	echo colorize( 'Playwright browser image: <light_cyan>' . getenv( 'SLIC_PLAYWRIGHT_IMAGE' ) . '</light_cyan> (tests run in slic)' . PHP_EOL );

	// Each run owns only its own server, including concurrent runs using different versions.
	$name    = 'slic-playwright-' . bin2hex( random_bytes( 8 ) );
	$cleaned = false;
	// Both finally and shutdown can reach this callback. Remove the container once,
	// including when a signal exits PHP before the finally block can run.
	$cleanup = static function () use ( $name, &$cleaned ) {
		if ( ! $cleaned ) {
			$cleaned = true;
			process_argv( array_merge( docker_binary_argv(), [ 'rm', '--force', $name ] ) );
		}
	};
	register_shutdown_function( $cleanup );
	$signals       = [];
	$async_signals = null;

	// When PCNTL is available, handle Ctrl+C and termination signals while waiting on
	// child processes. Exiting runs the registered browser cleanup and returns the
	// conventional 128 + signal status. Save the existing handlers and async mode so
	// the finally block can restore them when this command returns normally.
	if ( function_exists( 'pcntl_async_signals' ) && function_exists( 'pcntl_signal_get_handler' ) ) {
		$async_signals = pcntl_async_signals( true );

		foreach ( [ SIGINT, SIGTERM ] as $signal ) {
			$signals[ $signal ] = pcntl_signal_get_handler( $signal );
			pcntl_signal( $signal, static function ( int $received ) {
				exit( 128 + $received );
			} );
		}
	}

	try {
		// Use the project's mounted CLI for the server as well as the client.
		// Keep the container until cleanup so startup failures still have readable logs.
		$status = docker_compose_argv_realtime( [
			'run', '--detach', '--no-deps', '--name', $name,
			'--workdir', get_project_container_path(),
			'playwright', 'node_modules/.bin/playwright', 'run-server', '--host', '0.0.0.0', '--port', '3000',
		], slic_stack_argv() );

		if ( $status !== 0 ) {
			return $status;
		}

		if ( ! wait_for_playwright_server( $name ) ) {
			echo magenta( "Playwright browser server exited or did not become ready within 30 seconds. Server logs follow:\n" );
			process_argv_realtime( array_merge( docker_binary_argv(), [ 'logs', $name ] ) );

			return 1;
		}

		return docker_compose_argv_realtime( playwright_exec_command( $arguments, 'ws://' . $name . ':3000/' ), slic_stack_argv() );
	} finally {
		$cleanup();

		foreach ( $signals as $signal => $handler ) {
			pcntl_signal( $signal, $handler );
		}

		if ( $async_signals !== null ) {
			pcntl_async_signals( $async_signals );
		}
	}
}
