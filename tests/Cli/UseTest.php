<?php

namespace StellarWP\Slic\Test\Cli;

class UseTest extends BaseTestCase {

	public function test_use_sets_target_plugin(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'using' );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'The using command should report the set target.'
		);
	}

	public function test_use_invalid_target_shows_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'use nonexistent-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'is not a valid target',
			$output,
			'Using a nonexistent plugin should show an invalid target error.'
		);
	}

	public function test_use_lists_available_targets(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'use nonexistent-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'test-plugin',
			$output,
			'The error output should list available targets.'
		);
	}

	public function test_use_with_subdir_target(): void {
		$pluginsDir = $this->setUpPluginsDir( 'my-plugin' );

		// Add a subdirectory target inside the plugin.
		mkdir( $pluginsDir . '/my-plugin/common', 0777, true );
		file_put_contents(
			$pluginsDir . '/my-plugin/common/plugin.php',
			"/**\n* Plugin Name: Common\n*/"
		);

		$this->slicExec( 'use my-plugin/common', $this->dockerMockEnv() );

		$output = $this->slicExec( 'using' );

		$this->assertStringContainsString(
			'Using my-plugin/common',
			$output,
			'The using command should report the subdirectory target.'
		);
	}

	public function test_use_changes_target(): void {
		$pluginsDir = $this->setUpPluginsDir( 'first-plugin' );

		// Add a second plugin to the plugins directory.
		mkdir( $pluginsDir . '/second-plugin', 0777, true );
		file_put_contents(
			$pluginsDir . '/second-plugin/plugin.php',
			"/**\n* Plugin Name: Second Plugin\n*/"
		);

		$this->slicExec( 'use first-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'use second-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'using' );

		$this->assertStringContainsString(
			'Using second-plugin',
			$output,
			'The using command should report the most recently set target.'
		);
	}

	public function test_use_without_here_shows_error(): void {
		// Set up a plugins directory without running 'here' to register it.
		$tempDir = sys_get_temp_dir() . '/slic-no-here-' . uniqid( '', true );
		mkdir( $tempDir, 0777, true );
		mkdir( $tempDir . '/test-plugin', 0777, true );
		file_put_contents(
			$tempDir . '/test-plugin/plugin.php',
			"/**\n* Plugin Name: Test Plugin\n*/"
		);
		chdir( $tempDir );

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'is not a valid target',
			$output,
			'Using a plugin without a configured here directory should show an error.'
		);

		chdir( $this->initialDir );
		exec( 'rm -rf ' . escapeshellarg( $tempDir ) );
	}
}
