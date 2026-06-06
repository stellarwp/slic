<?php

namespace StellarWP\Slic\Test\Cli;

class CcTest extends BaseTestCase {

	public function test_cc_requires_use_target(): void {
		$output = $this->slicExec( 'cc' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running cc without a use target should show an error.'
		);
	}

	public function test_cc_passes_commands_to_container(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'cc generate:wpunit wpunit Foo', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'The cc command should report the use target.'
		);
	}
}
