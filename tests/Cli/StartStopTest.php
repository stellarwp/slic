<?php

namespace StellarWP\Slic\Test\Cli;

class StartStopTest extends BaseTestCase {

	public function test_start_runs_without_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'start', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'All containers are running',
			$output,
			'The start command should report all containers running.'
		);
		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The start command should not produce error output.'
		);
	}

	public function test_up_is_alias_for_start(): void {
		$this->setUpPluginsDir();

		$startOutput = $this->slicExec( 'start', $this->dockerMockEnv() );
		$upOutput    = $this->slicExec( 'up', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'All containers are running',
			$upOutput,
			'The up command should report all containers running.'
		);
		$this->assertStringContainsString(
			'All containers are running',
			$startOutput,
			'The start command should report all containers running.'
		);
	}

	public function test_stop_runs_without_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'stop', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'All services have been stopped',
			$output,
			'The stop command should report all services stopped.'
		);
		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The stop command should not produce error output.'
		);
	}

	public function test_down_is_alias_for_stop(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'down', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'All services have been stopped',
			$output,
			'The down command should report all services stopped.'
		);
	}

	public function test_restart_runs_without_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'restart', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The restart command should not produce error output.'
		);
	}

	public function test_start_help_shows_usage(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'start help' );

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The start help should show the summary section.'
		);
		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The start help should show the usage section.'
		);
		$this->assertStringContainsString(
			'Starts containers in the stack',
			$output,
			'The start help should describe its purpose.'
		);
	}

	public function test_stop_help_shows_usage(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'stop help' );

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The stop help should show the summary section.'
		);
		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The stop help should show the usage section.'
		);
		$this->assertStringContainsString(
			'Stops containers in the stack',
			$output,
			'The stop help should describe its purpose.'
		);
	}
}
