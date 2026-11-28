<?php

namespace StellarWP\Slic\Test\Cli;

class RestartTest extends BaseTestCase {

	public function test_restart_keeps_the_stack_registered(): void {
		$pluginsDir = realpath( $this->setUpPluginsDir() );

		$this->slicExec( 'restart', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			$pluginsDir,
			$this->slicExec( 'stack list', $this->dockerMockEnv() ),
			'Restarting a stack should not unregister it.'
		);
	}
}
