<?php

namespace StellarWP\Slic\Test\Cli;

class LogsTest extends BaseTestCase {

	public function test_logs_help_shows_usage(): void {
		$output = $this->slicExec( 'logs help' );

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The logs help should show the summary section.'
		);
		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The logs help should show the usage section.'
		);
	}

	public function test_logs_runs_without_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'logs', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The logs command should not produce error output.'
		);
	}
}
