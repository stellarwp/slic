<?php

namespace StellarWP\Slic\Test\Cli;

class CacheTest extends BaseTestCase {

	public function test_cache_on(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache on', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The cache on command should be recognized.'
		);
		$this->assertStringNotContainsString(
			'Failed',
			$output,
			'The cache on command should not report failure.'
		);
		$this->assertDockerCalled( '/ wp --allow-root redis enable$/', 'The cache on command should enable the Redis object cache.' );
	}

	public function test_cache_off(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache off', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The cache off command should be recognized.'
		);
		$this->assertStringNotContainsString(
			'Failed',
			$output,
			'The cache off command should not report failure.'
		);
		$this->assertDockerCalled( '/ wp --allow-root redis disable$/', 'The cache off command should disable the Redis object cache.' );
	}

	public function test_cache_status(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache status', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The cache status command should be recognized.'
		);
		$this->assertStringNotContainsString(
			'Failed',
			$output,
			'The cache status command should not report failure.'
		);
		$this->assertDockerCalled( '/ wp --allow-root redis status$/', 'The cache status command should ask Redis for its status.' );
		$this->assertDockerCalled( '/ redis (enable|disable)$/', 'The cache status command should not change the cache.', false );
	}

	public function test_cache_default_is_status(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The cache command with no argument should be recognized.'
		);
		$this->assertStringNotContainsString(
			'Failed',
			$output,
			'The cache command with no argument should not report failure.'
		);
		$this->assertDockerCalled( '/ wp --allow-root redis status$/', 'The cache command with no argument should ask Redis for its status.' );
		$this->assertDockerCalled( '/ redis (enable|disable)$/', 'The cache command with no argument should not change the cache.', false );
	}

	public function test_cache_help_shows_usage(): void {
		$output = $this->slicExec( 'help cache' );

		$this->assertStringContainsString(
			'cache',
			$output,
			'The cache help should reference the cache command.'
		);
		$this->assertStringContainsString(
			'status',
			$output,
			'The cache help should list the status subcommand.'
		);
		$this->assertStringContainsString(
			'on',
			$output,
			'The cache help should list the on subcommand.'
		);
		$this->assertStringContainsString(
			'off',
			$output,
			'The cache help should list the off subcommand.'
		);
	}
}
