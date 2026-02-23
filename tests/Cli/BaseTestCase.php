<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase {
	protected ?string $initialDir = null;

	public function setUp(): void {
		parent::setUp();
		$this->initialDir = getcwd();
	}

	public function tearDown(): void {
		parent::tearDown();
		chdir( $this->initialDir );
	}

	/**
	 * Execute a slic command and return the output.
	 *
	 * @param string $command The command to execute, escaped if required.
	 *
	 * @return string The command output.
	 */
	protected function slicExec( string $command ): string {
		// Execute the command with NO_COLOR set to avoid color codes in the output.
		$commandString = 'NO_COLOR=1 php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/slic.php' ) . ' ' . $command;

		// Redirect stderr to stdout to capture all output.
		return (string) shell_exec( $commandString . ' 2>&1' );
	}
}
