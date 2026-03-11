<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class StackTest extends BaseTestCase {

	public function test_stack_list_shows_registered_stacks(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'stack list' );

		$this->assertStringContainsString(
			$pluginsDir,
			$output,
			'The stack list should include the registered plugins directory.'
		);
	}

	public function test_stack_list_empty_when_no_stacks(): void {
		$this->slicExec( 'stack stop all -y', $this->dockerMockEnv() );

		$output = $this->slicExec( 'stack list' );

		$this->assertStringContainsString(
			'No stacks registered',
			$output,
			'The stack list should indicate no stacks are registered.'
		);
	}

	public function test_stack_info_shows_stack_details(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'stack info' );

		$this->assertStringContainsString(
			'Stack ID:',
			$output,
			'The stack info output should contain the Stack ID label.'
		);
		$this->assertStringContainsString(
			$pluginsDir,
			$output,
			'The stack info output should contain the plugins directory path.'
		);
		$this->assertStringContainsString(
			'Status:',
			$output,
			'The stack info output should contain the Status label.'
		);
	}

	public function test_stack_stop_removes_stack(): void {
		// Create the stack manually to avoid tearDown trying to stop it again.
		$pluginsDir = Directory::createTemp()
		                       ->createPlugin( 'test-plugin' )
		                       ->getAbsolutePath();
		chdir( $pluginsDir );
		$this->slicExec( 'here' );
		$stackId = realpath( $pluginsDir );

		$this->slicExec( 'stack stop ' . escapeshellarg( $stackId ), $this->dockerMockEnv() );

		$output = $this->slicExec( 'stack list' );
		$this->assertStringNotContainsString(
			$stackId,
			$output,
			'The stack list should not contain the stopped stack.'
		);
	}

	public function test_stack_stop_all_stops_all_stacks(): void {
		$dirA = $this->setUpPluginsDir( 'plugin-alpha' );
		$dirB = $this->setUpPluginsDir( 'plugin-beta' );

		$this->slicExec( 'stack stop all -y', $this->dockerMockEnv() );

		$output = $this->slicExec( 'stack list' );
		$this->assertStringNotContainsString(
			$dirA,
			$output,
			'The stack list should not contain the first stack after stopping all.'
		);
		$this->assertStringNotContainsString(
			$dirB,
			$output,
			'The stack list should not contain the second stack after stopping all.'
		);
	}

	public function test_stack_list_shows_multiple_stacks(): void {
		$dirA = $this->setUpPluginsDir( 'plugin-one' );
		$dirB = $this->setUpPluginsDir( 'plugin-two' );

		$output = $this->slicExec( 'stack list' );

		$this->assertStringContainsString(
			$dirA,
			$output,
			'The stack list should contain the first registered stack.'
		);
		$this->assertStringContainsString(
			$dirB,
			$output,
			'The stack list should contain the second registered stack.'
		);
	}
}
