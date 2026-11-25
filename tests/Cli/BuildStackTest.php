<?php

namespace StellarWP\Slic\Test\Cli;

class BuildStackTest extends BaseTestCase {

	public function test_build_stack_builds_all(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-stack', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The build-stack command should not produce error output.'
		);
	}

	public function test_build_stack_specific_service(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-stack wordpress', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The build-stack command for a specific service should not produce error output.'
		);
	}
}
