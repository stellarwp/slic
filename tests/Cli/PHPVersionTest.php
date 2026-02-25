<?php

namespace StellarWP\Slic\Test\Cli;

class PHPVersionTest extends BaseTestCase {

	public function test_default_php_version_is_7_4(): void {
		$this->setUpPluginsDir();

		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The general PHP version should be the default one.'
		);

		$this->slicExec( 'use test-plugin' );

		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The plugin PHP version should be the default one.'
		);
	}

	public function test_php_version_set_with_skip_rebuild_stages_version(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'php-version set 8.1 --skip-rebuild' );

		$this->assertStringContainsString(
			'PHP version staged for one time use: 8.1',
			$output,
			'The version should be staged.'
		);

		$output = $this->slicExec( 'php-version' );

		$this->assertStringContainsString(
			'PHP version is staged to switch to 8.1',
			$output,
			'The php-version command should report the staged version.'
		);
	}

	public function test_php_version_reset_with_skip_rebuild_resets_to_default(): void {
		$this->setUpPluginsDir();

		// Set to 8.1 first.
		$this->slicExec( 'php-version set 8.1 --skip-rebuild' );

		// Reset to default.
		$output = $this->slicExec( 'php-version reset --skip-rebuild' );

		$this->assertStringContainsString(
			'Resetting PHP version to: 7.4',
			$output,
			'The reset output should mention 7.4.'
		);
	}

	public function test_staged_version_is_applied_on_slic_use(): void {
		$this->setUpPluginsDir();

		// Stage version 8.2.
		$this->slicExec( 'php-version set 8.2 --skip-rebuild' );

		// Run slic use to apply the staged version; this triggers docker, so mock it.
		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'PHP 8.2 (using staged version)',
			$output,
			'The staged version should be applied during slic use.'
		);

		$this->assertStringContainsString(
			'PHP version set: 8.2',
			$output,
			'The version should be set during slic use.'
		);
	}

	public function test_auto_detection_from_composer_json(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a composer.json with config.platform.php in the plugin directory.
		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '8.2.10',
					],
				],
			] )
		);

		// Run slic use, which triggers auto-detection and docker; mock docker.
		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'PHP 8.2 (auto-detected from project)',
			$output,
			'The PHP version should be auto-detected from composer.json.'
		);

		$output = $this->slicExec( 'php-version' );

		$this->assertStringContainsString(
			'8.2',
			$output,
			'The php-version command should show the auto-detected version.'
		);
	}

	public function test_auto_detection_from_slic_json(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a slic.json with phpVersion in the plugin directory.
		file_put_contents(
			$pluginsDir . '/test-plugin/slic.json',
			json_encode( [ 'phpVersion' => '8.3' ] )
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'PHP 8.3 (auto-detected from project)',
			$output,
			'The PHP version should be auto-detected from slic.json.'
		);
	}

	public function test_slic_json_takes_priority_over_composer_json(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create both files with different versions.
		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '8.1.0',
					],
				],
			] )
		);

		file_put_contents(
			$pluginsDir . '/test-plugin/slic.json',
			json_encode( [ 'phpVersion' => '8.3' ] )
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'PHP 8.3 (auto-detected from project)',
			$output,
			'slic.json should take priority over composer.json.'
		);
	}

	public function test_cli_override_via_env_var(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a composer.json to verify CLI overrides project detection.
		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '8.1.0',
					],
				],
			] )
		);

		$env = array_merge( $this->dockerMockEnv(), [
			'SLIC_PHP_VERSION' => '8.4',
		] );

		$output = $this->slicExec( 'use test-plugin', $env );

		$this->assertStringContainsString(
			'PHP 8.4 (CLI override - temporary)',
			$output,
			'The CLI override should take priority.'
		);
	}

	public function test_project_env_slic_local_override(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a .env.slic.local in the plugin directory with a PHP version override.
		file_put_contents(
			$pluginsDir . '/test-plugin/.env.slic.local',
			"SLIC_PHP_VERSION=8.0\n"
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			"PHP 8.0 (from project's .env.slic.local)",
			$output,
			'The .env.slic.local override should be applied.'
		);
	}

	public function test_php_version_set_rejects_invalid_format(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'php-version set 8' );

		$this->assertStringContainsString(
			'Error: set-version requires a PHP version number with a single dot',
			$output,
			'A version without a dot should be rejected.'
		);

		$output = $this->slicExec( 'php-version set 8.1.10' );

		$this->assertStringContainsString(
			'Error: set-version requires a PHP version number with a single dot',
			$output,
			'A version with two dots should be rejected.'
		);

		$output = $this->slicExec( 'php-version set abc' );

		$this->assertStringContainsString(
			'Error: set-version requires a PHP version number with a single dot',
			$output,
			'A non-numeric version should be rejected.'
		);
	}

	public function test_cli_override_takes_priority_over_staged_version(): void {
		$this->setUpPluginsDir();

		// Stage version 8.1.
		$this->slicExec( 'php-version set 8.1 --skip-rebuild' );

		// Override with CLI env var.
		$env = array_merge( $this->dockerMockEnv(), [
			'SLIC_PHP_VERSION' => '8.4',
		] );

		$output = $this->slicExec( 'use test-plugin', $env );

		$this->assertStringContainsString(
			'PHP 8.4 (CLI override - temporary)',
			$output,
			'The CLI override should take priority over the staged version.'
		);
	}

	public function test_project_env_slic_local_takes_priority_over_auto_detection(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Set up both .env.slic.local and composer.json with different versions.
		file_put_contents(
			$pluginsDir . '/test-plugin/.env.slic.local',
			"SLIC_PHP_VERSION=8.0\n"
		);

		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '8.2.0',
					],
				],
			] )
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			"PHP 8.0 (from project's .env.slic.local)",
			$output,
			'.env.slic.local should take priority over composer.json auto-detection.'
		);
	}

	public function test_composer_json_version_below_7_4_is_ignored(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a composer.json with PHP version below 7.4.
		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '7.2.0',
					],
				],
			] )
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		// Should not detect any version, falling back to default.
		$this->assertStringNotContainsString(
			'auto-detected from project',
			$output,
			'A PHP version below 7.4 should not be auto-detected.'
		);

		$output = $this->slicExec( 'php-version' );

		$this->assertStringContainsString(
			'7.4',
			$output,
			'The default version should remain 7.4 when composer.json specifies a version below 7.4.'
		);
	}

	public function test_composer_json_long_version_is_normalized(): void {
		$pluginsDir = $this->setUpPluginsDir();

		// Create a composer.json with a three-part version.
		file_put_contents(
			$pluginsDir . '/test-plugin/composer.json',
			json_encode( [
				'config' => [
					'platform' => [
						'php' => '8.1.25',
					],
				],
			] )
		);

		$output = $this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'PHP 8.1 (auto-detected from project)',
			$output,
			'A three-part version should be normalized to major.minor.'
		);
	}
}
