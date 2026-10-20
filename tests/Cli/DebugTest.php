<?php

namespace StellarWP\Slic\Test\Cli;

class DebugTest extends BaseTestCase {

	public function test_debug_status_reports_the_debug_setting(): void {
		$this->setUpPluginsDir();

		// The debug setting is global, so restore it.
		$this->slicExec( 'debug on' );
		try {
			$output = $this->slicExec( 'debug status' );
		} finally {
			$this->slicExec( 'debug off' );
		}

		$this->assertStringContainsString(
			'Debug status is: on',
			$output,
			'The debug status should report the debug setting, not the XDebug one.'
		);
	}
}
