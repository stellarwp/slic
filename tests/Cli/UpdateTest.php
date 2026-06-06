<?php

namespace StellarWP\Slic\Test\Cli;

class UpdateTest extends BaseTestCase {

	public function test_update_help_shows_usage(): void {
		$output = $this->slicExec( 'update help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The update help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The update help output should contain a SUMMARY section.'
		);
	}

	public function test_upgrade_help_shows_usage(): void {
		$output = $this->slicExec( 'upgrade help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The upgrade help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The upgrade help output should contain a SUMMARY section.'
		);
	}
}
