<?php

namespace StellarWP\Slic\Test\Cli;

class PlaywrightTest extends BaseTestCase {

	public function test_playwright_requires_use_target(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'playwright test' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running playwright without a use target should show an error.'
		);
	}

	public function test_playwright_passes_commands_to_container(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'playwright test', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'Running playwright test with docker mock should show the current use target.'
		);
		$this->assertStringNotContainsString(
			'Error',
			$output,
			'Running playwright test with docker mock should not produce errors.'
		);
	}
}
