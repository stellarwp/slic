<?php

namespace StellarWP\Slic\Test\Cli;

class ConfigTest extends BaseTestCase {

	public function test_config_outputs_configuration(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'config' );

		$this->assertStringContainsString(
			'services:',
			$output,
			'The config output should contain a services section.'
		);
		$this->assertStringContainsString(
			'image:',
			$output,
			'The config output should contain image definitions.'
		);
		$this->assertStringContainsString(
			'networks:',
			$output,
			'The config output should contain a networks section.'
		);
	}

	public function test_config_reflects_php_version(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'php-version set 8.1 --skip-rebuild' );

		$output = $this->slicExec( 'config' );

		$this->assertStringContainsString(
			'slic-php8.1',
			$output,
			'The config output should reference PHP 8.1 in the image name.'
		);
		$this->assertStringContainsString(
			'slic-wordpress-php8.1',
			$output,
			'The config output should reference PHP 8.1 in the WordPress image name.'
		);
	}

	public function test_config_reflects_plugins_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'config' );

		$this->assertStringContainsString(
			realpath( $pluginsDir ),
			$output,
			'The config output should contain the plugins directory path.'
		);
	}

	public function test_config_reflects_xdebug_settings(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'xdebug on' );

		$output = $this->slicExec( 'config' );

		$this->assertStringContainsString(
			'XDEBUG_DISABLE: "0"',
			$output,
			'The config should show XDEBUG_DISABLE as 0 when xdebug is on.'
		);

		$this->slicExec( 'xdebug off' );

		$output = $this->slicExec( 'config' );

		$this->assertStringContainsString(
			'XDEBUG_DISABLE: "1"',
			$output,
			'The config should show XDEBUG_DISABLE as 1 when xdebug is off.'
		);
	}
}
