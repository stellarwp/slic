<?php

namespace StellarWP\Slic\Test\Cli;

class ComposerTest extends BaseTestCase {

	public function test_composer_requires_use_target(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'composer install' );

		$this->assertStringContainsString(
			'This command requires a target set using the',
			$output,
			'Running composer without a use target should show an error.'
		);
	}

	public function test_composer_get_version(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'composer get-version', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'The get-version output should show the current use target.'
		);
	}

	public function test_composer_set_version_1(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'composer set-version 1', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Composer version set to 1',
			$output,
			'Setting version to 1 should confirm the change.'
		);
	}

	public function test_composer_set_version_2(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'composer set-version 2', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Composer version set to 2',
			$output,
			'Setting version to 2 should confirm the change.'
		);
	}

	public function test_composer_reset_version(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		// Set to 1 first so reset has something to change.
		$this->slicExec( 'composer set-version 1', $this->dockerMockEnv() );

		$output = $this->slicExec( 'composer reset-version', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Composer version reset to default',
			$output,
			'Resetting the version should confirm the reset to default.'
		);
	}

	public function test_composer_passes_commands_to_container(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'composer install', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'Running composer install with docker mock should pass through without error.'
		);
	}
}
