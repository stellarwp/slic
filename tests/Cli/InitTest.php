<?php

namespace StellarWP\Slic\Test\Cli;

class InitTest extends BaseTestCase {

	public function test_init_without_plugin_shows_error(): void {
		$output = $this->slicExec( 'init', $this->gitMockEnv() );

		$this->assertStringContainsString(
			'Using',
			$output,
			'Running init without a plugin name should show the current (empty) target.'
		);

		$this->assertStringContainsString(
			'Finished initializing',
			$output,
			'Running init without a plugin should still finish initializing.'
		);
	}

	public function test_init_nonexistent_plugin_shows_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'init nonexistent', $this->gitMockEnv() );

		$this->assertStringContainsString(
			'Cloning nonexistent',
			$output,
			'Running init with a nonexistent plugin should attempt to clone it.'
		);

		$this->assertStringContainsString(
			'Could not clone',
			$output,
			'Running init with a nonexistent plugin should show a clone error.'
		);
	}

	public function test_init_help_shows_usage(): void {
		$output = $this->slicExec( 'init help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The init help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The init help output should contain a SUMMARY section.'
		);
	}
}
