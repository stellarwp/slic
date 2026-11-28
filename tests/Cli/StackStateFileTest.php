<?php

namespace StellarWP\Slic\Test\Cli;

class StackStateFileTest extends BaseTestCase {

	public function test_commands_still_run_when_the_stack_state_file_is_missing(): void {
		$this->setUpPluginsDir();
		preg_match( '/Environment File: (\S+)/', $this->slicExec( 'stack info' ), $matches );
		$this->assertNotEmpty( $matches[1] ?? null, 'The stack info should report the stack state file.' );
		unlink( $matches[1] );

		$this->assertStringContainsString(
			'PHP version currently set to',
			$this->slicExec( 'php-version' ),
			'A missing stack state file should not stop the command.'
		);
	}
}
