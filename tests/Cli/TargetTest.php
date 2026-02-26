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

	public function test_target_interactive_collects_targets_and_commands(): void {
		$this->setUpPluginsDir();

		// Pipe: target "test-plugin", end targets, command "info", end commands, confirm.
		$stdin  = "test-plugin\n\ninfo\n\n\n";
		$output = $this->slicExec( 'target', $this->dockerMockEnv(), $stdin );

		$this->assertStringContainsString(
			'Target',
			$output,
			'The interactive target flow should prompt for targets.'
		);
		$this->assertStringContainsString(
			'test-plugin',
			$output,
			'The output should echo the entered target name.'
		);
		$this->assertStringContainsString(
			'Command',
			$output,
			'The interactive target flow should prompt for commands.'
		);
	}

	public function test_target_interactive_shows_collected_targets(): void {
		$this->setUpPluginsDir( 'plugin-a' );

		// Enter one target, end targets loop, enter a command, end commands, confirm.
		$stdin  = "plugin-a\n\ninfo\n\n\n";
		$output = $this->slicExec( 'target', $this->dockerMockEnv(), $stdin );

		$this->assertStringContainsString(
			'Targets:',
			$output,
			'The output should display the "Targets:" label.'
		);
		$this->assertStringContainsString(
			'plugin-a',
			$output,
			'The output should include the collected target name.'
		);
	}

	public function test_target_interactive_prompts_confirmation(): void {
		$this->setUpPluginsDir();

		// Enter target, end targets, enter command, end commands, confirm.
		$stdin  = "test-plugin\n\ninfo\n\n\n";
		$output = $this->slicExec( 'target', $this->dockerMockEnv(), $stdin );

		$this->assertStringContainsString(
			'Are you sure',
			$output,
			'The interactive target flow should prompt for confirmation before executing.'
		);
	}
}
