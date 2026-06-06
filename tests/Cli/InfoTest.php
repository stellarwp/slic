<?php

namespace StellarWP\Slic\Test\Cli;

class InfoTest extends BaseTestCase {

	public function test_info_shows_version(): void {
		$output = $this->slicExec( 'info' );

		$this->assertMatchesRegularExpression(
			'/slic version \d+\.\d+\.\d+/',
			$output,
			'The info output should include the slic version in semver format.'
		);
	}

	public function test_info_shows_plugins_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'info' );

		$this->assertStringContainsString(
			'SLIC_PLUGINS_DIR',
			$output,
			'The info output should include the SLIC_PLUGINS_DIR key.'
		);
		$this->assertStringContainsString(
			$pluginsDir,
			$output,
			'The info output should include the plugins directory path.'
		);
	}

	public function test_info_shows_current_target(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'info' );

		$this->assertMatchesRegularExpression(
			'/SLIC_CURRENT_PROJECT:\s*test-plugin/',
			$output,
			'The info output should show test-plugin as the SLIC_CURRENT_PROJECT value.'
		);
	}

	public function test_info_shows_php_version(): void {
		$output = $this->slicExec( 'info' );

		$this->assertMatchesRegularExpression(
			'/SLIC_PHP_VERSION:\s*7\.4/',
			$output,
			'The info output should show 7.4 as the default SLIC_PHP_VERSION value.'
		);
	}

	public function test_info_without_stack_shows_defaults(): void {
		$output = $this->slicExec( 'info' );

		$this->assertStringContainsString(
			'Current configuration:',
			$output,
			'The info output should include the current configuration header.'
		);
		$this->assertMatchesRegularExpression(
			'/SLIC_CURRENT_PROJECT:\s*$\n/m',
			$output,
			'The default SLIC_CURRENT_PROJECT should be empty when no target is set.'
		);
	}
}
