<?php

namespace StellarWP\Slic\Test\Cli;

class ComposerCacheTest extends BaseTestCase {

	public function test_composer_cache_shows_current(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'composer-cache' );

		$this->assertStringContainsString(
			'Composer cache directory:',
			$output,
			'The composer-cache command should show the current cache directory setting.'
		);
	}

	public function test_composer_cache_set(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'composer-cache set /tmp/test-cache', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Composer cache directory:',
			$output,
			'The set subcommand should display the cache directory label.'
		);

		$this->assertStringContainsString(
			'/tmp/test-cache',
			$output,
			'The set subcommand should confirm the new cache directory path.'
		);
	}

	public function test_composer_cache_unset(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'composer-cache unset', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Composer cache directory:',
			$output,
			'The unset subcommand should display the cache directory label.'
		);
	}

	public function test_composer_cache_set_nonexistent_path(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec(
			'composer-cache set /tmp/nonexistent-path-' . uniqid(),
			$this->dockerMockEnv()
		);

		$this->assertStringContainsString(
			'Composer cache directory:',
			$output,
			'Setting a non-existent path should still be accepted.'
		);

		$this->assertStringNotContainsString(
			'error',
			strtolower( $output ),
			'No error should be reported for a non-existent cache path.'
		);
	}
}
