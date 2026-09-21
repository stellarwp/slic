<?php

namespace StellarWP\Slic\Test\Cli;

class ShellTest extends BaseTestCase {

	public function test_shell_requires_use_target(): void {
		$output = $this->slicExec( 'shell' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running shell without a use target should show an error.'
		);
	}

	public function test_shell_with_target_runs(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'shell', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'The shell command should show the current target.'
		);
	}

	public function test_ssh_is_alias_for_shell(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'ssh', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'not a valid command',
			$output,
			'The ssh command should be a recognized command.'
		);
	}

	public function test_shell_help_shows_usage(): void {
		$output = $this->slicExec( 'shell help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The shell help should display usage information.'
		);

		$this->assertStringContainsString(
			'shell',
			$output,
			'The shell help should reference the shell command.'
		);
	}
}
