<?php

namespace StellarWP\Slic\Test\Cli;

class DcExecPsTest extends BaseTestCase {

	public function test_dc_passes_commands(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'dc ps', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'slic',
			$output,
			'The dc ps command should run without error.'
		);
	}

	public function test_dc_help_shows_usage(): void {
		$output = $this->slicExec( 'dc help' );

		$this->assertStringContainsString(
			'Runs a docker compose command in the stack',
			$output,
			'The dc help command should show the summary.'
		);

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The dc help command should show usage information.'
		);
	}

	public function test_exec_passes_command(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'exec "whoami"', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The exec command should run without error.'
		);

		$this->assertStringNotContainsString(
			'Please specify a bash command',
			$output,
			'The exec command should not complain about missing arguments.'
		);
	}

	public function test_exec_help_shows_usage(): void {
		$output = $this->slicExec( 'exec help' );

		$this->assertStringContainsString(
			'Runs a bash command in the stack',
			$output,
			'The exec help command should show the summary.'
		);

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The exec help command should show usage information.'
		);
	}

	public function test_ps_lists_containers(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'ps', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'slic',
			$output,
			'The ps command should run without error.'
		);
	}

	public function test_ps_help_shows_usage(): void {
		$output = $this->slicExec( 'ps help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The ps help command should show usage information.'
		);
	}
}
