<?php

namespace StellarWP\Slic\Test\Cli;

class BuildSubdirTest extends BaseTestCase {

	public function test_build_subdir_on(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'build-subdir off' );

		$output = $this->slicExec( 'build-subdir on' );

		$this->assertStringContainsString(
			'Build Sub-directories status: on',
			$output,
			'Turning build-subdir on should confirm the on status.'
		);
	}

	public function test_build_subdir_off(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'build-subdir on' );

		$output = $this->slicExec( 'build-subdir off' );

		$this->assertStringContainsString(
			'Build Sub-directories status: off',
			$output,
			'Turning build-subdir off should confirm the off status.'
		);
	}

	public function test_build_subdir_status(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'build-subdir on' );

		$output = $this->slicExec( 'build-subdir status' );

		$this->assertStringContainsString(
			'Sub-directories build status is: on',
			$output,
			'The status subcommand should report the current on state.'
		);

		$this->slicExec( 'build-subdir off' );

		$output = $this->slicExec( 'build-subdir status' );

		$this->assertStringContainsString(
			'Sub-directories build status is: off',
			$output,
			'The status subcommand should report the current off state.'
		);
	}

	public function test_build_subdir_default_state(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-subdir status' );

		$this->assertMatchesRegularExpression(
			'/Sub-directories build status is: (on|off)/',
			$output,
			'The default state should be reported as on or off.'
		);
	}
}
