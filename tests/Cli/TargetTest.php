<?php

namespace StellarWP\Slic\Test\Cli;

class TargetTest extends BaseTestCase {

	public function test_target_help_shows_usage(): void {
		$output = $this->slicExec( 'target help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The target help output should display usage information.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The target help output should display a summary.'
		);

		$this->assertStringContainsString(
			'slic.php target',
			$output,
			'The target help output should contain the command signature.'
		);
	}

	public function test_target_interactive_flow(): void {
		$this->setUpPluginsDir();

		// Pipe: target "test-plugin", end targets, command "info", end commands, confirm.
		$stdin  = "test-plugin\n\ninfo\n\n\n";
		$output = $this->slicExec( 'target', $this->dockerMockEnv(), $stdin );

		$this->assertStringContainsString(
			'Target (return when done):',
			$output,
			'The interactive target flow should prompt for targets.'
		);
		$this->assertStringContainsString(
			'Targets:',
			$output,
			'The output should display the "Targets:" label after collecting targets.'
		);
		$this->assertStringContainsString(
			'test-plugin',
			$output,
			'The output should include the collected target name.'
		);
		$this->assertStringContainsString(
			'Command (return when done):',
			$output,
			'The interactive target flow should prompt for commands.'
		);
		$this->assertStringContainsString(
			'Are you sure you want to run these commands on',
			$output,
			'The interactive target flow should prompt for confirmation before executing.'
		);
	}
}
