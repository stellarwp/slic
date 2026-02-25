<?php

namespace StellarWP\Slic\Test\Cli;

class MysqlTest extends BaseTestCase {

	public function test_mysql_help_shows_usage(): void {
		$output = $this->slicExec( 'mysql help' );

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The mysql help should show the summary section.'
		);
		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The mysql help should show the usage section.'
		);
	}

	public function test_mysql_runs_without_error(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'mysql', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Error',
			$output,
			'The mysql command should not produce error output.'
		);
		$this->assertStringNotContainsString(
			'not a valid command',
			$output,
			'The mysql command should be recognized as a valid command.'
		);
	}
}
