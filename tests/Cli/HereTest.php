<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class HereTest extends BaseTestCase {

	public function test_here_sets_plugins_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'stack list' );

		$this->assertStringContainsString(
			realpath( $pluginsDir ),
			$output,
			'The stack list should contain the plugins directory.'
		);
	}

	public function test_here_output_confirms_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		chdir( $pluginsDir );
		$output = $this->slicExec( 'here' );

		$this->assertStringContainsString(
			realpath( $pluginsDir ),
			$output,
			'The here output should contain the set directory path.'
		);
	}

	public function test_here_reset_unsets_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$resetOutput = $this->slicExec( 'here reset' );

		// The reset output should reference the default _plugins directory as the new stack ID.
		$this->assertStringContainsString(
			'_plugins',
			$resetOutput,
			'The here reset output should reference the default _plugins directory.'
		);

		$this->assertStringContainsString(
			'Stack',
			$resetOutput,
			'The here reset output should confirm a stack operation.'
		);
	}

	public function test_here_from_subdirectory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		chdir( $pluginsDir . '/test-plugin' );
		$output = $this->slicExec( 'here' );

		$this->assertStringContainsString(
			realpath( $pluginsDir . '/test-plugin' ),
			$output,
			'Running here from a plugin subdirectory should set that subdirectory as the stack ID.'
		);
	}

	public function test_here_in_non_plugin_directory_still_creates_stack(): void {
		// First set up a valid plugins dir so we have a registered stack context.
		$this->setUpPluginsDir();

		$emptyDir = Directory::createTemp()->getAbsolutePath();
		chdir( $emptyDir );

		$output = $this->slicExec( 'here' );

		$this->assertStringContainsString(
			'Stack created successfully',
			$output,
			'Running here in a directory without plugins should still create a stack.'
		);

		$this->assertStringContainsString(
			realpath( $emptyDir ),
			$output,
			'The stack ID should reference the empty directory.'
		);
	}
}
