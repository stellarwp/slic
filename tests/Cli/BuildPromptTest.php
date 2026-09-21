<?php

namespace StellarWP\Slic\Test\Cli;

class BuildPromptTest extends BaseTestCase {

	public function test_build_prompt_on(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-prompt on' );

		$this->assertStringContainsString(
			'Build Prompt status: on',
			$output,
			'Turning build-prompt on should confirm activation.'
		);
	}

	public function test_build_prompt_off(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-prompt off' );

		$this->assertStringContainsString(
			'Build Prompt status: off',
			$output,
			'Turning build-prompt off should confirm deactivation.'
		);
	}

	public function test_build_prompt_status(): void {
		$this->setUpPluginsDir();

		// Set to on, then check status.
		$this->slicExec( 'build-prompt on' );
		$output = $this->slicExec( 'build-prompt status' );

		$this->assertStringContainsString(
			'Interactive status is: on',
			$output,
			'Status should report on after enabling build-prompt.'
		);

		// Set to off, then check status.
		$this->slicExec( 'build-prompt off' );
		$output = $this->slicExec( 'build-prompt status' );

		$this->assertStringContainsString(
			'Interactive status is: off',
			$output,
			'Status should report off after disabling build-prompt.'
		);
	}

	public function test_build_prompt_default_state(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'build-prompt status' );

		$this->assertStringContainsString(
			'Interactive status is: off',
			$output,
			'The default build-prompt state should be off.'
		);
	}
}
