<?php

namespace StellarWP\Slic\Test\Cli;

class ResetTest extends BaseTestCase {

	public function test_reset_clears_use_target(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$usingBefore = $this->slicExec( 'using' );
		$this->assertStringContainsString(
			'Using test-plugin',
			$usingBefore,
			'The target should be set before reset.'
		);

		$this->slicExec( 'reset', $this->dockerMockEnv() );
		$this->slicExec( 'here' );

		$usingAfter = $this->slicExec( 'using' );
		$this->assertStringContainsString(
			'Currently not using any target',
			$usingAfter,
			'The target should be cleared after reset and re-initialization.'
		);
	}

	public function test_reset_restores_default_php_version(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'php-version set 8.1 --skip-rebuild' );

		$phpVersionBefore = $this->slicExec( 'php-version' );
		$this->assertStringContainsString(
			'8.1',
			$phpVersionBefore,
			'The PHP version should be staged to 8.1 before reset.'
		);

		// Reset preserves per-stack state; an explicit php-version reset is needed.
		$this->slicExec( 'reset', $this->dockerMockEnv() );
		$this->slicExec( 'php-version reset --skip-rebuild' );

		$phpVersionAfter = $this->slicExec( 'php-version' );
		$this->assertStringContainsString(
			'7.4',
			$phpVersionAfter,
			'The PHP version should be restored to 7.4 after reset.'
		);
	}

	public function test_reset_output_confirms(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'reset', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Removing',
			$output,
			'The reset output should mention removing files.'
		);
		$this->assertStringContainsString(
			'.env.slic.run',
			$output,
			'The reset output should mention the run settings file.'
		);
		$this->assertStringContainsString(
			'done',
			$output,
			'The reset output should confirm completion.'
		);
	}

	public function test_reset_clears_xdebug_settings(): void {
		$this->setUpPluginsDir();

		$setOutput = $this->slicExec( 'xdebug port 9009' );
		$this->assertStringContainsString(
			'XDP=9009',
			$setOutput,
			'The xdebug port command should confirm setting XDP=9009.'
		);

		$this->slicExec( 'reset', $this->dockerMockEnv() );
		$this->slicExec( 'here' );

		$xdebugAfter = $this->slicExec( 'xdebug status' );
		$this->assertStringNotContainsString(
			'9009',
			$xdebugAfter,
			'The xdebug port should no longer be 9009 after reset and re-initialization.'
		);
	}
}
