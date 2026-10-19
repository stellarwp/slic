<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use StellarWP\Slic\Test\Support\Factories\Directory;

class WorktreeTest extends BaseTestCase {

	public static function invalidBranchProvider(): array {
		return [
			'leading dash'                => [ '-x' ],
			'component starting with dot' => [ 'foo/.bar' ],
			'trailing dot'                => [ 'foo.' ],
			'component ending in .lock'   => [ 'foo/bar.lock/baz' ],
			'reflog syntax'               => [ 'a@{b' ],
		];
	}

	#[DataProvider( 'invalidBranchProvider' )]
	public function test_worktree_add_rejects_a_branch_name_git_does_not_allow( string $branch ): void {
		$this->setUpGitPluginStack();

		$this->assertStringContainsString(
			"Error: Invalid branch name: {$branch}",
			$this->slicExec( 'worktree add ' . escapeshellarg( $branch ) . ' -y', $this->dockerMockEnv() ),
			'Branch names git rejects should fail validation before any git command runs.'
		);
	}

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

	public function test_an_added_worktree_stack_knows_where_its_target_is(): void {
		$pluginsDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( dirname( $pluginsDir ) . '/test-plugin-feature-x' );
		try {
			$output = $this->slicExec( 'stack info', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginsDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringNotContainsString(
			'Could not determine container path',
			$output,
			'A worktree stack should inherit the directories of its base stack.'
		);
	}

	public function test_a_worktree_of_a_site_stack_mounts_plugins_where_its_base_stack_does(): void {
		$site = Directory::createTemp( '/slic-test-site-' . uniqid( '', true ) )
		                 ->createPlugin( 'content/plugins/test-plugin' )
		                 ->getAbsolutePath();
		mkdir( $site . '/content/themes' );
		touch( $site . '/wp-config.php' );
		$pluginsDir = $site . '/content/plugins';
		$this->initGitRepository( $pluginsDir . '/test-plugin' );

		chdir( $site );
		$this->slicExec( 'here' );
		$this->createdStackIds[] = realpath( $pluginsDir );
		chdir( $pluginsDir );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( $pluginsDir . '/test-plugin-feature-x' );
		try {
			$output = $this->slicExec( 'xdebug status', $this->dockerMockEnv() );
		} finally {
			chdir( $pluginsDir );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'/content/plugins => /var/www/html/content/plugins',
			$output,
			'A worktree of a site stack should mount plugins where its base stack does.'
		);
		$this->assertStringContainsString(
			'/test-plugin-feature-x => /var/www/html/content/plugins/test-plugin',
			$output,
			'A worktree of a site stack should be mounted over its target in the same content directory.'
		);
	}

	public function test_a_subdirectory_of_an_unregistered_worktree_offers_to_register_it(): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = dirname( $pluginDir ) . '/test-plugin-feature-x';
		shell_exec( 'git -C ' . escapeshellarg( $pluginDir ) . ' worktree add -q ' . escapeshellarg( $worktreeDir ) . ' -b feature-x 2>&1' );
		mkdir( $worktreeDir . '/src' );
		chdir( $worktreeDir . '/src' );

		$this->assertStringContainsString(
			'Detected unregistered git worktree!',
			$this->slicExecInTerminal( 'stack info', "n\n" ),
			'Running slic below the root of an unregistered worktree should offer to register it.'
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
		$this->initGitRepository( $pluginDir );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		return $pluginDir;
	}

	/**
	 * Runs a slic command in a pseudo-terminal, where slic asks its interactive questions, and returns the output.
	 *
	 * @param string $command The command to execute, escaped if required.
	 * @param string $input   The answers to type at the prompts.
	 *
	 * @return string The command output.
	 */
	private function slicExecInTerminal( string $command, string $input ): string {
		$slic = 'php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/slic.php' ) . ' ' . $command;
		// util-linux script (Linux) takes the command with -c; BSD script (macOS) takes it as trailing arguments.
		$inTerminal = str_contains( (string) shell_exec( 'script --version 2>&1' ), 'util-linux' )
			? 'script -qec ' . escapeshellarg( $slic ) . ' /dev/null'
			: 'script -q /dev/null ' . $slic;

		$envString = '';
		foreach ( [ 'NO_COLOR' => '1' ] + $this->dockerMockEnv() as $key => $value ) {
			$envString .= $key . '=' . escapeshellarg( $value ) . ' ';
		}

		return (string) shell_exec( 'printf %s ' . escapeshellarg( $input ) . ' | ' . $envString . $inTerminal . ' 2>&1' );
	}

	/**
	 * Makes a directory a git repository with one commit of its contents.
	 *
	 * @param string $dir The absolute path to the directory.
	 */
	private function initGitRepository( string $dir ): void {
		// Fixture commits must not depend on the host git identity or commit signing settings.
		$git = 'git -C ' . escapeshellarg( $dir ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false';
		shell_exec( "{$git} init -q && {$git} add -A && {$git} commit -q -m init" );
	}
}
