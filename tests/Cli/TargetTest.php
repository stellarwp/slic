<?php

namespace StellarWP\Slic\Test\Cli;

class TargetTest extends BaseTestCase {

	public function test_target_help_shows_usage(): void {
		$output = $this->slicExec( 'target help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The target help output should display usage information.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The target help output should display a summary.'
		);

		$this->assertStringContainsString(
			'slic.php target',
			$output,
			'The target help output should contain the command signature.'
		);
	}
}
