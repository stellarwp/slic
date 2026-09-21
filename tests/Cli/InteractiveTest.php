<?php

namespace StellarWP\Slic\Test\Cli;

class InteractiveTest extends BaseTestCase {

	public function test_interactive_on(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'interactive off' );

		$output = $this->slicExec( 'interactive on' );

		$this->assertStringContainsString(
			'Interactive status: on',
			$output,
			'Turning interactive on should confirm activation.'
		);
	}

	public function test_interactive_off(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'interactive on' );

		$output = $this->slicExec( 'interactive off' );

		$this->assertStringContainsString(
			'Interactive status: off',
			$output,
			'Turning interactive off should confirm deactivation.'
		);
	}

	public function test_interactive_status(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'interactive on' );

		$output = $this->slicExec( 'interactive status' );

		$this->assertStringContainsString(
			'Interactive status is: on',
			$output,
			'The status subcommand should report the current interactive state.'
		);

		$this->slicExec( 'interactive off' );

		$output = $this->slicExec( 'interactive status' );

		$this->assertStringContainsString(
			'Interactive status is: off',
			$output,
			'The status subcommand should reflect the updated state after turning off.'
		);
	}

	public function test_interactive_default_state(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'interactive off' );

		// Calling interactive with no argument should default to "on".
		$output = $this->slicExec( 'interactive' );

		$this->assertStringContainsString(
			'Interactive status: on',
			$output,
			'Calling interactive with no argument should default to turning it on.'
		);
	}
}
