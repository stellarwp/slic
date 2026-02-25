<?php

namespace StellarWP\Slic\Test\Cli;

class GroupTest extends BaseTestCase {

	public function test_group_is_not_a_recognized_command(): void {
		$output = $this->slicExec( 'group help' );

		$this->assertStringContainsString(
			'Unknown command',
			$output,
			'The group command should not be recognized.'
		);
	}
}
