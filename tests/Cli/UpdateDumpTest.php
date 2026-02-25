<?php

namespace StellarWP\Slic\Test\Cli;

class UpdateDumpTest extends BaseTestCase {

	public function test_update_dump_help_shows_usage(): void {
		$output = $this->slicExec( 'update-dump help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The help output should contain the USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The help output should contain the SUMMARY section.'
		);
	}

	public function test_update_dump_requires_file_argument(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'update-dump', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Undefined array key 0',
			$output,
			'Running update-dump without a file argument should produce a warning about the missing argument.'
		);
	}

	public function test_update_dump_nonexistent_file_shows_error(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'update-dump nonexistent.sql', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'does not exist',
			$output,
			'Running update-dump with a nonexistent file should show an error about the missing file.'
		);
	}
}
