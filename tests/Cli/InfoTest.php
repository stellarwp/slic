<?php

namespace StellarWP\Slic\Test\Cli;

class InfoTest extends BaseTestCase {

	public function test_info_shows_a_stack_setting_turned_off_and_the_override_of_it(): void {
		$pluginsDir = $this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'build-prompt off' );
		file_put_contents( $pluginsDir . '/test-plugin/.env.slic.local', "SLIC_BUILD_PROMPT=1\n" );

		$this->assertMatchesRegularExpression(
			'/- SLIC_BUILD_PROMPT: 0 \[runtime\] ⚠ 1 \[configured\]$/m',
			$this->slicExec( 'info' ),
			'The info command should show the stack\'s setting of 0 and the target override of it.'
		);
	}

	public function test_info_shows_the_stack_settings_over_the_global_ones(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'php-version set 8.3 --skip-rebuild' );

		// A stale value in the global run file, as a 2.x install leaves there.
		$globalRunFile = self::slicRoot() . '/.env.slic.run';
		$previous      = is_file( $globalRunFile ) ? file_get_contents( $globalRunFile ) : null;
		file_put_contents( $globalRunFile, "SLIC_PHP_VERSION=8.1\n" );
		try {
			$output = $this->slicExec( 'info' );
		} finally {
			null === $previous ? unlink( $globalRunFile ) : file_put_contents( $globalRunFile, $previous );
		}

		$this->assertMatchesRegularExpression(
			'/- SLIC_PHP_VERSION: 8\.3$/m',
			$output,
			'The info command should show the stack\'s own PHP version.'
		);
		$this->assertMatchesRegularExpression(
			'#- \S+/\.env\.slic\.run\.[0-9a-f]+$#m',
			$output,
			'The info command should list the stack\'s settings file among the files it read.'
		);
	}

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
