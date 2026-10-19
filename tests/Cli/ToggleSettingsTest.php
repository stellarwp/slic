<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\Attributes\DataProvider;

class ToggleSettingsTest extends BaseTestCase {

	public static function toggleProvider(): array {
		return [
			'interactive'  => [ 'interactive' ],
			'build-prompt' => [ 'build-prompt' ],
			'build-subdir' => [ 'build-subdir' ],
		];
	}

	#[DataProvider( 'toggleProvider' )]
	public function test_toggle_off_applies_to_the_current_stack_only( string $toggle ): void {
		$otherStack = $this->setUpPluginsDir( 'other-plugin' );
		$this->setUpPluginsDir( 'this-plugin' );

		$this->slicExec( "{$toggle} off" );

		$this->assertMatchesRegularExpression(
			'/status is: off/',
			$this->slicExec( "{$toggle} status" ),
			"The {$toggle} toggle should apply to the current stack."
		);

		chdir( $otherStack );

		$this->assertMatchesRegularExpression(
			'/status is: on/',
			$this->slicExec( "{$toggle} status" ),
			"The {$toggle} toggle should not change another stack."
		);
	}

	public function test_composer_cache_set_applies_to_the_current_stack_only(): void {
		$otherStack = $this->setUpPluginsDir( 'other-plugin' );
		$this->setUpPluginsDir( 'this-plugin' );
		$cacheDir = sys_get_temp_dir() . '/slic-composer-cache-' . uniqid();
		mkdir( $cacheDir );
		// Setting the cache offers to restart the services: take the default without prompting, against mocked Docker.
		$this->slicExec( 'interactive off' );

		$this->slicExec( 'composer-cache set ' . escapeshellarg( $cacheDir ), $this->dockerMockEnv() );

		$this->assertStringContainsString(
			"Composer cache directory: {$cacheDir}",
			$this->slicExec( 'composer-cache status' ),
			'The composer cache directory should apply to the current stack.'
		);

		chdir( $otherStack );

		$this->assertStringNotContainsString(
			$cacheDir,
			$this->slicExec( 'composer-cache status' ),
			'The composer cache directory should not change another stack.'
		);
	}
}
