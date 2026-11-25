<?php

namespace StellarWP\Slic\Test\Cli;

class UpdateTest extends BaseTestCase {

	public function test_update_help_shows_usage(): void {
		$output = $this->slicExec( 'update help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The update help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The update help output should contain a SUMMARY section.'
		);
	}

	public function test_upgrade_help_shows_usage(): void {
		$output = $this->slicExec( 'upgrade help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The upgrade help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The upgrade help output should contain a SUMMARY section.'
		);
	}

	public function test_update_keeps_the_stack_and_its_settings(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'xdebug port 9100' );

		// Not interactive, so update takes the default answer and stops the stack first.
		$this->slicExec( 'update', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$this->slicExec( 'using', $this->dockerMockEnv() ),
			'Updating should keep the stack registered with its target.'
		);
		$this->assertStringContainsString(
			'Remote port: 9100',
			$this->slicExec( 'xdebug status', $this->dockerMockEnv() ),
			'Updating should keep the stack settings.'
		);
	}
}
