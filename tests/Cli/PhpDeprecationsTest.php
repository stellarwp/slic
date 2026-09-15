<?php

namespace StellarWP\Slic\Test\Cli;

class PhpDeprecationsTest extends BaseTestCase {
	/**
	 * Every PHP file is compiled when the CLI boots, so a single command surfaces compile-time deprecations
	 * (deprecated syntax) as well as those raised while running the command itself.
	 */
	public function test_cli_boots_without_php_deprecations(): void {
		$output = $this->slicExec( 'help' );

		$this->assertStringContainsString( 'slic version', $output );
		$this->assertStringNotContainsString( 'Deprecated:', $output );
	}
}
