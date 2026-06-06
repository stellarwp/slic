<?php

namespace StellarWP\Slic\Test\Cli;

class AirplaneModeTest extends BaseTestCase {

	public function test_airplane_mode_on(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'airplane-mode on', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Airplane mode plugin installed',
			$output,
			'Turning airplane mode on should confirm the plugin was installed.'
		);
		$this->assertStringNotContainsString(
			'Error',
			$output,
			'Turning airplane mode on should not produce errors.'
		);
	}

	public function test_airplane_mode_off(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'airplane-mode off', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Airplane mode plugin removed',
			$output,
			'Turning airplane mode off should confirm the plugin was removed.'
		);
		$this->assertStringNotContainsString(
			'Error',
			$output,
			'Turning airplane mode off should not produce errors.'
		);
	}

	public function test_airplane_mode_help_shows_usage(): void {
		$output = $this->slicExec( 'airplane-mode help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'Running airplane-mode help should display usage information.'
		);
		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'Running airplane-mode help should display a summary.'
		);
	}
}
