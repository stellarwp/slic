<?php

namespace StellarWP\Slic\Test\Cli;

class UsingTest extends BaseTestCase {

	public function test_using_shows_current_target(): void {
		$this->setUpPluginsDir( 'test-plugin' );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'using', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'test-plugin',
			$output,
			'The using command should display the current target.'
		);
	}

	public function test_using_with_no_target_shows_none(): void {
		$this->setUpPluginsDir( 'test-plugin' );

		$output = $this->slicExec( 'using', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'not using any target',
			$output,
			'The using command should indicate no target is set.'
		);
	}

	public function test_using_after_target_change(): void {
		$pluginsDir = $this->setUpPluginsDir( 'plugin-alpha' );
		// Add a second plugin to the plugins directory.
		mkdir( $pluginsDir . '/plugin-beta', 0777, true );
		file_put_contents(
			$pluginsDir . '/plugin-beta/plugin.php',
			"/**\n* Plugin Name: Plugin Beta\n*/"
		);

		$this->slicExec( 'use plugin-alpha', $this->dockerMockEnv() );
		$this->slicExec( 'use plugin-beta', $this->dockerMockEnv() );

		$output = $this->slicExec( 'using', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'plugin-beta',
			$output,
			'The using command should display the most recently set target.'
		);
	}
}
