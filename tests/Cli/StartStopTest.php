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

	public function test_stop_keeps_the_stack_and_its_settings(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'xdebug port 9100' );

		$this->slicExec( 'stop', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$this->slicExec( 'using', $this->dockerMockEnv() ),
			'Stopping should keep the stack registered with its target; `stack stop` unregisters it.'
		);
		$this->assertStringContainsString(
			'Remote port: 9100',
			$this->slicExec( 'xdebug status', $this->dockerMockEnv() ),
			'Stopping should keep the stack settings.'
		);
	}

	public function test_stop_from_a_sibling_with_a_backslash_in_its_name_does_not_stop_the_stack(): void {
		$root = '/slic-test-backslash-' . uniqid( '', true );
		$base = realpath( $this->setUpPluginsDir( 'test-plugin', $root . '/base' ) );
		// A second stack: with one registered stack slic would fall back to it.
		$this->setUpPluginsDir( 'other-plugin' );
		// On POSIX a backslash is a filename character: `base\child` is a sibling of `base`, not inside it.
		$sibling = dirname( $base ) . '/base\\child';
		mkdir( $sibling );
		chdir( $sibling );

		$output = $this->slicExec( 'stop', $this->dockerMockLogEnv() );

		$this->assertStringContainsString( 'No active stack', $output, 'A sibling of a stack should not select it.' );
		$this->assertDockerCalled(
			'/-p slic_' . substr( md5( $base ), 0, 8 ) . ' .*down/',
			'The sibling should not stop the base stack.',
			false
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
