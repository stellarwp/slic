<?php

namespace StellarWP\Slic\Test\Cli;

class BuildStackTest extends BaseTestCase {

	public function test_build_stack_builds_all(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-stack', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The build-stack command should not produce error output.'
		);
		$this->assertDockerCalled( '/ build$/', 'The build-stack command should build every service.' );
	}

	public function test_build_stack_specific_service(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-stack wordpress', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The build-stack command for a specific service should not produce error output.'
		);
		$this->assertDockerCalled( '/ build wordpress$/', 'The build-stack command should build the requested service.' );
		$this->assertDockerCalled( '/ build$/', 'The build-stack command should build only the requested service.', false );
	}
}
