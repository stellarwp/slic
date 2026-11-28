<?php

namespace StellarWP\Slic\Test\Cli;

class NpmTest extends BaseTestCase {

	public function test_npm_requires_use_target(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'npm install' );

		$this->assertStringContainsString(
			'This command requires a target set using the',
			$output,
			'Running npm without a use target should show an error.'
		);
	}

	public function test_npm_passes_commands_to_container(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'npm install', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'Running npm install with docker mock should show the current use target.'
		);

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'Running npm install with docker mock should not produce an error.'
		);
	}
}
