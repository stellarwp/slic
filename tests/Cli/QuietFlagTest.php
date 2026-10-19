<?php

namespace StellarWP\Slic\Test\Cli;

class QuietFlagTest extends BaseTestCase {

	public function test_quiet_flag_leaves_the_other_arguments_in_place(): void {
		$this->assertStringContainsString(
			'slic.php airplane-mode (on|off)',
			$this->slicExec( '-q airplane-mode help' ),
			'The -q flag should not shift the CLI name out of the arguments.'
		);
	}
}
