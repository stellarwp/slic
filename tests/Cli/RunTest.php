<?php

namespace StellarWP\Slic\Test\Cli;

class RunTest extends BaseTestCase {

	public function test_run_requires_use_target(): void {
		$output = $this->slicExec( 'run' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running run without a use target should show an error.'
		);
	}

	public function test_run_passes_commands_to_container(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a codeception.dist.yml so the run command does not bail for missing config.
		file_put_contents(
			$pluginsDir . '/test-plugin/codeception.dist.yml',
			"paths:\n  tests: tests\n"
		);

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'run wpunit', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'The run command should report the current use target.'
		);
	}
}
