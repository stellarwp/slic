<?php

namespace StellarWP\Slic\Test\Cli;

class WorktreeTest extends BaseTestCase {

	public function test_worktree_remove_stops_the_worktree_stack_with_its_own_xdebug_port(): void {
		$this->setUpGitPluginStack();
		preg_match( '/XDebug Port: (\d+)/', $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() ), $matches );
		$this->assertNotEmpty( $matches[1] ?? null, 'Adding a worktree should report its XDebug port.' );
		$this->slicExec( 'debug on' );

		$this->assertMatchesRegularExpression(
			'/Executing command: .*XDP=' . $matches[1] . ' .* stop/',
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() ),
			'Stopping the worktree stack should use the worktree XDebug port.'
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
