<?php

namespace StellarWP\Slic\Test\Cli;

class CacheTest extends BaseTestCase {

	public function test_cache_on(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache on', $this->dockerMockEnv() );

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
	}

	public function test_cache_off(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache off', $this->dockerMockEnv() );

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
	}

	public function test_cache_status(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache status', $this->dockerMockEnv() );

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
	}

	public function test_cache_default_is_status(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cache', $this->dockerMockEnv() );

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
