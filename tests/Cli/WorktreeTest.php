<?php

namespace StellarWP\Slic\Test\Cli;

class WorktreeTest extends BaseTestCase {

	public function test_worktree_remove_stops_the_worktree_stack_with_its_own_xdebug_port(): void {
		$this->setUpGitPluginStack();
		preg_match( '/XDebug Port: (\d+)/', $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() ), $matches );
		$this->assertNotEmpty( $matches[1] ?? null, 'Adding a worktree should report its XDebug port.' );
		// Debug output prints the docker compose commands with their environment; the setting is global, so restore it.
		$this->slicExec( 'debug on' );
		try {
			$output = $this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'debug off' );
		}

		$this->assertMatchesRegularExpression(
			'/Executing command: .*XDP=' . $matches[1] . ' .* stop/',
			$output,
			'Stopping the worktree stack should use the worktree XDebug port.'
		);
	}

	public function test_worktree_add_accepts_a_branch_name_with_dots(): void {
		$this->setUpGitPluginStack();

		try {
			$output = $this->slicExec( 'worktree add release/3.0.0 -y', $this->dockerMockEnv() );
		} finally {
			// A base stack with worktrees is not unregistered on stop, so remove the worktree stack first.
			$this->slicExec( 'worktree remove release/3.0.0 -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'Worktree stack registered successfully!',
			$output,
			'A valid git branch name with dots should get a worktree.'
		);
	}

	/**
	 * Sets up a stack whose `test-plugin` target is a git repository with one commit, and selects it.
	 */
	private function setUpGitPluginStack(): void {
		$pluginDir = $this->setUpPluginsDir() . '/test-plugin';
		// Fixture commits must not depend on the host git identity or commit signing settings.
		$git = 'git -C ' . escapeshellarg( $pluginDir ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false';
		shell_exec( "{$git} init -q && {$git} add -A && {$git} commit -q -m init" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
	}
}
