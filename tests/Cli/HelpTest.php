<?php

namespace StellarWP\Slic\Test\Cli;

class HelpTest extends BaseTestCase {

	public function test_help_output_contains_version(): void {
		$output = $this->slicExec( 'help' );

		$this->assertStringContainsString(
			'slic version',
			$output,
			'The help output should contain the version string.'
		);
	}

	public function test_help_output_lists_popular_commands(): void {
		$output = $this->slicExec( 'help' );

		$this->assertStringContainsString(
			'Popular:',
			$output,
			'The help output should contain a Popular section.'
		);

		$popularCommands = [ 'composer', 'run', 'use', 'help' ];
		foreach ( $popularCommands as $command ) {
			$this->assertMatchesRegularExpression(
				'/Popular:.*^\s+' . preg_quote( $command, '/' ) . '\s/ms',
				$output,
				"The Popular section should list the '{$command}' command."
			);
		}
	}

	public function test_help_output_lists_advanced_commands(): void {
		$output = $this->slicExec( 'help' );

		$this->assertStringContainsString(
			'Advanced:',
			$output,
			'The help output should contain an Advanced section.'
		);

		$advancedCommands = [ 'cache', 'debug', 'dc' ];
		foreach ( $advancedCommands as $command ) {
			$this->assertMatchesRegularExpression(
				'/Advanced:.*^\s+' . preg_quote( $command, '/' ) . '\s/ms',
				$output,
				"The Advanced section should list the '{$command}' command."
			);
		}
	}

	public function test_help_output_includes_usage_hint(): void {
		$output = $this->slicExec( 'help' );

		$this->assertStringContainsString(
			'Type slic <command> help',
			$output,
			'The help output should contain guidance on getting per-command help.'
		);
	}

	public function test_unknown_command_shows_error(): void {
		$output = $this->slicExec( 'nonexistent-cmd-xyz' );

		$this->assertStringContainsString(
			'Unknown command: nonexistent-cmd-xyz',
			$output,
			'Running an unrecognized command should display an error message.'
		);
	}
}
