<?php

namespace StellarWP\Slic\Test\Cli;

class DebugTest extends BaseTestCase {

	public function test_debug_status_reports_the_debug_setting(): void {
		$this->setUpPluginsDir();

		// The debug setting is global, so restore it.
		$this->slicExec( 'debug on' );
		try {
			$output = $this->slicExec( 'debug status' );
		} finally {
			$this->slicExec( 'debug off' );
		}

		$this->assertStringContainsString(
			'Debug status is: on',
			$output,
			'The debug status should report the debug setting, not the XDebug one.'
		);
	}

	public function test_debug_on(): void {
		$this->setUpPluginsDir();

		// The debug setting is global, so restore it.
		try {
			$this->assertStringContainsString(
				'Debug status: on',
				$this->slicExec( 'debug on' ),
				'Turning debug on should confirm activation.'
			);
		} finally {
			$this->slicExec( 'debug off' );
		}
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

		// The debug setting is global, so restore it.
		$this->slicExec( 'debug on' );
		try {
			// The debug command writes CLI_VERBOSITY to the run settings file; verify it persists via info.
			$this->assertMatchesRegularExpression(
				'/CLI_VERBOSITY\s*[:=]\s*1/',
				$this->slicExec( 'info' ),
				'CLI_VERBOSITY should remain 1 after setting debug on in a previous command.'
			);
		} finally {
			$this->slicExec( 'debug off' );
		}
	}
}
