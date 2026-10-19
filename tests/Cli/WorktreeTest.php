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

	public function test_worktree_add_accepts_the_yes_flag_before_the_branch(): void {
		$this->setUpGitPluginStack();

		try {
			$output = $this->slicExec( 'worktree add -y feature-x', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'Branch: feature-x',
			$output,
			'The -y flag should not be read as the branch name.'
		);
	}

	public function test_worktree_remove_accepts_the_yes_flag_before_the_branch(): void {
		$this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		try {
			$output = $this->slicExec( 'worktree remove -y feature-x', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'Worktree removed successfully.',
			$output,
			'The -y flag should not be read as the branch name.'
		);
	}

	public function test_worktree_add_rejects_a_detached_worktree_in_the_branch_directory(): void {
		$pluginDir = $this->setUpGitPluginStack();
		shell_exec( 'git -C ' . escapeshellarg( $pluginDir ) . ' worktree add -q --detach ../test-plugin-feature-x' );

		try {
			$output = $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'Error: Directory exists as a git worktree but for a different branch.',
			$output,
			'A detached worktree should not be registered as the requested branch.'
		);
	}

	public function test_stopping_a_base_stack_with_worktrees_says_how_to_remove_them(): void {
		$baseStack = realpath( dirname( $this->setUpGitPluginStack() ) );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		try {
			$output = $this->slicExec( 'stack stop ' . escapeshellarg( $baseStack ), $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'slic worktree remove feature-x',
			$output,
			'The warning should name the command that removes each worktree.'
		);
	}

	/**
	 * Sets up a stack whose `test-plugin` target is a git repository with one commit, and selects it.
	 *
	 * @return string The absolute path to the plugin repository.
	 */
	private function setUpGitPluginStack(): string {
		$pluginDir = $this->setUpPluginsDir() . '/test-plugin';
		// Fixture commits must not depend on the host git identity or commit signing settings.
		$git = 'git -C ' . escapeshellarg( $pluginDir ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false';
		shell_exec( "{$git} init -q && {$git} add -A && {$git} commit -q -m init" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		return $pluginDir;
	}
}
