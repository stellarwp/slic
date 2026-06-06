<?php

namespace StellarWP\Slic\Test\Cli;

class DebugTest extends BaseTestCase {

	public function test_debug_on(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'debug on' );

		$this->assertStringContainsString(
			'Debug status: on',
			$output,
			'Turning debug on should confirm activation.'
		);
	}

	public function test_debug_off(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'debug off' );

		$this->assertStringContainsString(
			'Debug status: off',
			$output,
			'Turning debug off should confirm deactivation.'
		);
	}

	public function test_debug_status(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'debug status' );

		$this->assertStringContainsString(
			'Debug status is:',
			$output,
			'The status subcommand should report the current debug state.'
		);
	}

	public function test_debug_no_args_defaults_to_status(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'debug' );

		$this->assertStringContainsString(
			'Debug status is:',
			$output,
			'Running debug with no argument should default to showing status.'
		);
	}

	public function test_debug_persists_across_commands(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'debug on' );

		// The debug command writes CLI_VERBOSITY to the run settings file; verify it persists via info.
		$output = $this->slicExec( 'info' );

		$this->assertMatchesRegularExpression(
			'/CLI_VERBOSITY\s*[:=]\s*1/',
			$output,
			'CLI_VERBOSITY should remain 1 after setting debug on in a previous command.'
		);
	}
}
