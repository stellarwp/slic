<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class PHPVersionTest extends BaseTestCase {
	public function test_default_php_version_is_7_4(): void {
		$pluginsDir = Directory::createTemp()
		                       ->createPlugin( 'test-plugin' )
		                       ->getAbsolutePath();
		chdir( $pluginsDir );

		// Set the directory as slic root.
		$this->slicExec( 'here' );

		// Check the general PHP version.
		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The general PHP version should be the default one.'
		);

		// Set the target to the plugin.
		$this->slicExec( 'use test-plugin', true );

		// Check the plugin PHP version.
		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The plugin PHP version should be the default one.'
		);
	}
}
