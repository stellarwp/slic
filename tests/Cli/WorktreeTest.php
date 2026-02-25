<?php

namespace StellarWP\Slic\Test\Cli;

class WorktreeTest extends BaseTestCase {

	public function test_worktree_help_shows_usage(): void {
		$output = $this->slicExec( 'worktree help' );

		$this->assertStringContainsString(
			'USAGE',
			$output,
			'The worktree help output should contain USAGE section.'
		);

		$this->assertStringContainsString(
			'SUMMARY',
			$output,
			'The worktree help output should contain SUMMARY section.'
		);
	}

	public function test_worktree_no_subcommand_shows_help(): void {
		$output = $this->slicExec( 'worktree' );

		$this->assertStringContainsString(
			'Available commands',
			$output,
			'Running worktree without a subcommand should show available commands.'
		);

		$this->assertStringContainsString(
			"Run 'slic worktree help' for more information.",
			$output,
			'Running worktree without a subcommand should suggest running help.'
		);
	}
}
