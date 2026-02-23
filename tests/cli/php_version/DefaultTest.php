<?php

namespace StellarWP\Slic\Test\Cli\php_version;

use StellarWP\Slic\Test\Cli\BaseTestCase;

class DefaultTest extends BaseTestCase {
	public function test_default_php_version_is_7_4(): void {
		// Create a temporary directory.
		$dir = sys_get_temp_dir() . '/slic-test-' . uniqid( '', true );

		// In the temp directory create a plugin.
		mkdir( $dir . '/00-default', 0777, true );
		file_put_contents(
			$dir . '/00-default/plugin.php',
			'/**
     * Plugin Name: Test Plugin
     */'
		);

		// Change to the directory.
		chdir( $dir );

		// Set the directory as slic root.
		$this->slicExec( 'here' );

		// Check the general PHP version.
		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The general PHP version should be the default one.'
		);

		// Set the target to the plugin.
		$this->slicExec( 'use 00-default', true );

		// Check the plugin PHP version.
		$this->assertStringContainsString(
			'PHP version currently set to 7.4',
			$this->slicExec( 'php-version' ),
			'The plugin PHP version should be the default one.'
		);
	}
}
