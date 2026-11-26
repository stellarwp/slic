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
			"/Executing command: .*XDP='{$matches[1]}' .* down/",
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
		$this->createdStackIds[] = realpath( $site );
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

	public function test_a_worktree_stack_mounts_the_worktree_into_the_playwright_server(): void {
		exec( 'docker compose version 2>/dev/null', $output, $status );
		if ( 0 !== $status ) {
			$this->markTestSkipped( 'docker compose is not installed.' );
		}
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( dirname( $pluginDir ) . '/test-plugin-feature-x' );
		try {
			// `config` only renders the stack configuration; it starts nothing, so the real docker compose is safe here.
			$config = $this->slicExec( 'dc --profile playwright config playwright', [ 'SSH_AUTH_SOCK' => sys_get_temp_dir() . '/slic-test-ssh-agent.sock' ] );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression(
			'#source: \S+/test-plugin-feature-x\s+target: /var/www/html/wp-content/plugins/test-plugin\s#',
			$config,
			'The Playwright server should run the worktree\'s own Playwright, as the slic container does.'
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

	public function test_reset_in_a_worktree_clears_its_settings_and_keeps_its_target(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( dirname( $pluginDir ) . '/test-plugin-feature-x' );
		try {
			$this->slicExec( 'xdebug port 9009' );
			$this->slicExec( 'reset', $this->dockerMockEnv() );
			$xdebug = $this->slicExec( 'xdebug status', $this->dockerMockEnv() );
			$using  = $this->slicExec( 'using', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'Remote port:',
			$xdebug,
			'The xdebug status command should report the worktree stack settings.'
		);
		$this->assertStringNotContainsString(
			'Remote port: 9009',
			$xdebug,
			'Reset should clear the settings of the worktree stack it runs in.'
		);
		$this->assertStringContainsString(
			'Using test-plugin',
			$using,
			'Reset should leave the worktree stack pointed at its target.'
		);
	}

	public function test_stopping_a_worktree_stack_from_another_stack_mounts_that_worktree(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$pluginsDir    = realpath( dirname( $pluginDir ) );
		$worktreeStack = $pluginsDir . '@test-plugin-feature-x';

		// Run from another stack, so slic starts with that stack's settings.
		$this->setUpPluginsDir( 'other-plugin' );
		try {
			$this->slicExec(
				'stack stop ' . escapeshellarg( $worktreeStack ) . ' -y',
				$this->dockerMockLogEnv( [ 'SLIC_WORKTREE_FULL_PATH' ] )
			);
		} finally {
			chdir( $pluginsDir );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertDockerCalled(
			'# down .*\[SLIC_WORKTREE_FULL_PATH=' . preg_quote( $pluginsDir . '/test-plugin-feature-x', '#' ) . '\]$#',
			'Stopping a worktree stack should give Docker Compose that worktree to mount.'
		);
	}

	public function test_a_worktree_stack_uses_the_composer_cache_set_on_its_base_stack(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$cacheDir  = $this->createTempDir();
		$this->slicExec( 'composer-cache set ' . escapeshellarg( $cacheDir ), $this->dockerMockEnv() );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( dirname( $pluginDir ) . '/test-plugin-feature-x' );
		try {
			$output = $this->slicExec( 'composer-cache', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			basename( $cacheDir ),
			$output,
			'A worktree stack should use the Composer cache directory set on its base stack.'
		);
	}

	public function test_update_dump_in_a_worktree_finds_a_dump_that_exists_only_on_the_branch(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$worktreeDir = dirname( $pluginDir ) . '/test-plugin-feature-x';
		file_put_contents( $worktreeDir . '/branch-only.sql', '' );

		chdir( $worktreeDir );
		try {
			$output = $this->slicExec( 'update-dump branch-only.sql', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringNotContainsString(
			'does not exist',
			$output,
			'A worktree stack should look for files in the worktree, not in the base checkout.'
		);
	}

	public function test_use_in_a_worktree_says_how_to_switch_target_from_the_base_stack(): void {
		// A space in the base stack path: the suggested command must still be one to paste.
		$pluginDir = $this->setUpPluginsDir( 'test-plugin', '/slic test plugins ' . uniqid( '', true ) ) . '/test-plugin';
		$this->initGitRepository( $pluginDir );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		mkdir( dirname( $pluginDir ) . '/other-plugin' );
		file_put_contents( dirname( $pluginDir ) . '/other-plugin/plugin.php', "/**\n* Plugin Name: Other Plugin\n*/" );

		chdir( dirname( $pluginDir ) . '/test-plugin-feature-x' );
		try {
			$output = $this->slicExec( 'use other-plugin', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'cd ' . escapeshellarg( realpath( dirname( $pluginDir ) ) ) . ' && slic use other-plugin',
			$output,
			'Refusing to switch target in a worktree should say how to switch it in the base stack.'
		);
		$this->assertStringNotContainsString(
			'stack switch',
			$output,
			'Refusing to switch target in a worktree should not suggest a command that does not exist.'
		);
	}

	public function test_stop_all_keeps_a_worktree_that_failed_to_stop(): void {
		$baseStack = realpath( dirname( $this->setUpGitPluginStack() ) );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$worktreeProject = 'slic_' . substr( md5( $baseStack . '@test-plugin-feature-x' ), 0, 8 );

		try {
			$this->slicExec( 'stack stop all -y', [ 'SLIC_DOCKER_MOCK_FAIL' => "^-p {$worktreeProject} " ] + $this->dockerMockEnv() );
			$output = $this->slicExec( 'stack list', $this->dockerMockEnv() );
		} finally {
			chdir( $baseStack );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString(
			'test-plugin-feature-x (feature-x)',
			$output,
			'A worktree that failed to stop should stay registered so it can be stopped again.'
		);
	}

	public function test_here_in_an_unregistered_worktree_registers_only_the_worktree(): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = dirname( $pluginDir ) . '/test-plugin-feature-x';
		shell_exec( 'git -C ' . escapeshellarg( $pluginDir ) . ' worktree add -q ' . escapeshellarg( $worktreeDir ) . ' -b feature-x 2>&1' );
		$worktreeDir = realpath( $worktreeDir );
		chdir( $worktreeDir );
		$this->createdStackIds[] = $worktreeDir;

		try {
			$this->slicExecInTerminal( 'here', "y\n" );
			// Running here again in the now registered worktree should not register it either.
			$this->slicExec( 'here', $this->dockerMockEnv() );
			$output = $this->slicExec( 'stack list', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'stack stop ' . escapeshellarg( dirname( $worktreeDir ) . '@test-plugin-feature-x' ), $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'test-plugin-feature-x (feature-x)', $output, 'The worktree should be registered as a worktree.' );
		$this->assertStringNotContainsString( $worktreeDir, $output, 'The worktree should not also be registered as a stack of its own.' );
	}

	public function test_a_worktree_of_a_stack_in_a_path_that_contains_an_at_sign_mounts_the_worktree(): void {
		exec( 'docker compose version 2>/dev/null', $output, $status );
		if ( 0 !== $status ) {
			$this->markTestSkipped( 'docker compose is not installed.' );
		}
		$pluginDir = $this->setUpPluginsDir( 'test-plugin', '/slic-test@' . uniqid( '', true ) . '/plugins' ) . '/test-plugin';
		$this->initGitRepository( $pluginDir );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );

		chdir( dirname( $pluginDir ) . '/test-plugin-feature-x' );
		try {
			// `config` only renders the stack configuration; it starts nothing, so the real docker compose is safe here.
			$config = $this->slicExec( 'dc config slic', [ 'SSH_AUTH_SOCK' => sys_get_temp_dir() . '/slic-test-ssh-agent.sock' ] );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression(
			'#source: \S+/test-plugin-feature-x\s+target: /var/www/html/wp-content/plugins/test-plugin\s#',
			$config,
			'A worktree of a stack whose path contains @ should be mounted over its target.'
		);
	}

	public function test_a_sibling_repository_with_the_target_prefix_is_not_offered_as_a_worktree(): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir  = $this->setUpGitPluginStack();
		$otherRepo  = $this->createTempDir();
		$sibling    = dirname( $pluginDir ) . '/test-plugin-plus';
		$this->initGitRepository( $otherRepo );
		// A worktree of another repository, named like a worktree of the target.
		shell_exec( 'git -C ' . escapeshellarg( $otherRepo ) . ' worktree add -q ' . escapeshellarg( $sibling ) . ' -b plus 2>&1' );
		chdir( $sibling );

		$this->assertStringNotContainsString(
			'Detected unregistered git worktree!',
			$this->slicExecInTerminal( 'stack info', "n\n" ),
			'A checkout that is not a worktree of the target should not be offered for registration.'
		);
	}

	public static function targetTypeProvider(): array {
		return [
			'plugin' => [ 'plugin' ],
			'theme'  => [ 'theme' ],
		];
	}

	#[DataProvider( 'targetTypeProvider' )]
	public function test_worktree_add_list_sync_and_remove_work_for_a_target_of_each_type( string $type ): void {
		$targetDir   = 'theme' === $type ? $this->setUpGitThemeStack() : $this->setUpGitPluginStack();
		$worktreeDir = $targetDir . '-feature-x';

		$add  = $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$list = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
		$sync = $this->slicExec( 'worktree sync', $this->dockerMockEnv() );
		$remove = $this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );

		$this->assertStringContainsString( 'Worktree stack registered successfully!', $add, "Adding a worktree of a {$type} should succeed." );
		$this->assertStringContainsString( 'Path: ' . realpath( $targetDir ) . '-feature-x', $list, 'The worktree should be created next to its target.' );
		$this->assertMatchesRegularExpression( '#^' . preg_quote( realpath( $targetDir ) . '-feature-x', '#' ) . '\s+\S+ \[feature-x\]$#m', $list, 'The list should show the git worktrees of the target.' );
		$this->assertStringContainsString( 'All worktrees are in sync.', $sync, 'Sync should find the worktree where add created it.' );
		$this->assertStringContainsString( 'Worktree removed successfully.', $remove, "Removing a worktree of a {$type} should succeed." );
		$this->assertDirectoryDoesNotExist( $worktreeDir, 'Remove should delete the worktree directory.' );
	}

	#[DataProvider( 'targetTypeProvider' )]
	public function test_worktree_merge_merges_the_branch_and_removes_the_worktree( string $type ): void {
		$targetDir   = 'theme' === $type ? $this->setUpGitThemeStack() : $this->setUpGitPluginStack();
		$worktreeDir = $targetDir . '-feature-x';
		$baseBranch  = $this->git( $targetDir, 'symbolic-ref --short HEAD' );
		preg_match( '/Project Name: (\S+)/', $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() ), $project );
		$this->commitFile( $worktreeDir, 'feature.txt', 'feature' );

		$output = $this->slicExec( 'worktree merge feature-x -y', $this->dockerMockLogEnv() );

		$this->assertStringContainsString( 'Worktree merged and removed successfully!', $output, 'A clean merge should succeed.' );
		$this->assertSame( 'feature', $this->git( $targetDir, 'show ' . escapeshellarg( $baseBranch . ':feature.txt' ) ), 'The base branch should have the worktree commit.' );
		$this->assertSame( '', $this->git( $targetDir, 'branch --list feature-x' ), 'The merged branch should be deleted.' );
		$this->assertDirectoryDoesNotExist( $worktreeDir, 'The worktree directory should be removed.' );
		$this->assertStringContainsString( 'No slic worktree stacks registered.', $this->slicExec( 'worktree list', $this->dockerMockEnv() ), 'The worktree stack should be unregistered.' );
		$this->assertDockerCalled( '#-p ' . preg_quote( $project[1], '#' ) . ' .*down#', 'Merge should bring the worktree stack down, removing its containers and network.' );
	}

	public function test_worktree_merge_with_a_conflict_keeps_the_worktree_and_says_how_to_abort(): void {
		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $worktreeDir, 'plugin.php', 'worktree' );
		$this->commitFile( $pluginDir, 'plugin.php', 'base' );

		try {
			$output = $this->slicExec( 'worktree merge feature-x -y || echo merge-failed', $this->dockerMockEnv() );
			$list   = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
			$kept   = is_dir( $worktreeDir );
		} finally {
			$this->git( $pluginDir, 'merge --abort' );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'merge-failed', $output, 'A conflicting merge should fail.' );
		$this->assertStringContainsString( 'git -C ' . escapeshellarg( realpath( $pluginDir ) ) . ' merge --abort', $output, 'The advice should say how to abort the merge.' );
		$this->assertStringContainsString( 'Branch: feature-x', $list, 'The worktree stack should stay registered.' );
		$this->assertTrue( $kept, 'The worktree should be kept.' );
	}

	public function test_worktree_merge_leaves_the_base_repository_mid_merge_after_a_conflict(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $pluginDir . '-feature-x', 'plugin.php', 'worktree' );
		$this->commitFile( $pluginDir, 'plugin.php', 'base' );

		try {
			$this->slicExec( 'worktree merge feature-x -y', $this->dockerMockEnv() );
			$mergeHead = $this->git( $pluginDir, 'rev-parse -q --verify MERGE_HEAD' );
		} finally {
			$this->git( $pluginDir, 'merge --abort' );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertNotSame( '', $mergeHead, 'The base repository should be left mid-merge, as git leaves it.' );
	}

	public function test_worktree_merge_refuses_a_detached_base_branch(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->git( $pluginDir, 'checkout -q --detach' );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $pluginDir . '-feature-x', 'feature.txt', 'feature' );

		try {
			$output = $this->slicExec( 'worktree merge feature-x -y || echo merge-failed', $this->dockerMockEnv() );
			$branch = $this->git( $pluginDir, 'branch --list feature-x' );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'merge-failed', $output, 'Merging into a detached HEAD should fail.' );
		$this->assertStringContainsString( 'feature-x', $branch, 'The branch should not be deleted.' );
	}

	public function test_worktree_merge_of_a_missing_worktree_cleans_up_its_stack(): void {
		$pluginDir = $this->setUpGitPluginStack();
		preg_match( '/Project Name: (\S+)/', $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() ), $project );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );

		$output = $this->slicExec( 'worktree merge feature-x -y', $this->dockerMockLogEnv() );

		$this->assertStringContainsString( 'Git worktree no longer exists', $output, 'Merge should notice the worktree is gone.' );
		$this->assertStringContainsString( 'No slic worktree stacks registered.', $this->slicExec( 'worktree list', $this->dockerMockEnv() ), 'The worktree stack should be unregistered.' );
		$this->assertDockerCalled( '#-p ' . preg_quote( $project[1], '#' ) . ' .*down#', 'Merge should bring the stack of the missing worktree down.' );
	}

	public function test_worktree_sync_clean_brings_down_and_unregisters_only_its_own_deleted_worktrees(): void {
		$otherDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		shell_exec( 'rm -rf ' . escapeshellarg( $otherDir . '-feature-x' ) );
		$pluginDir = $this->setUpGitPluginStack();
		preg_match( '/Project Name: (\S+)/', $this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() ), $project );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );

		try {
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockLogEnv() );
			$list      = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
			chdir( dirname( $otherDir ) );
			$otherList = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $otherDir ) );
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'No slic worktree stacks registered.', $list, 'Sync should unregister the deleted worktree.' );
		$this->assertDockerCalled( '#-p ' . preg_quote( $project[1], '#' ) . ' .*down#', 'Sync should bring the deleted worktree stack down before unregistering it.' );
		$this->assertStringContainsString( 'Branch: feature-x', $otherList, 'Sync should not clean the worktrees of another base stack.' );
	}

	public static function lockedWorktreeProvider(): array {
		return [
			'remove, directory present' => [ 'remove feature-x -y', true ],
			'remove, directory offline' => [ 'remove feature-x -y', false ],
			'merge, directory present'  => [ 'merge feature-x -y', true ],
			'merge, directory offline'  => [ 'merge feature-x -y', false ],
			'sync, directory offline'   => [ 'sync --clean -y', false ],
		];
	}

	#[DataProvider( 'lockedWorktreeProvider' )]
	public function test_worktree_subcommands_refuse_a_locked_worktree_before_touching_it( string $command, bool $present ): void {
		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $worktreeDir, 'feature.txt', 'feature' );
		$this->git( $pluginDir, 'worktree lock --reason ' . escapeshellarg( 'External disk' ) . ' ' . escapeshellarg( $worktreeDir ) );
		if ( ! $present ) {
			// An unmounted external disk: git keeps the record of a locked worktree whose directory is unavailable.
			rename( $worktreeDir, $worktreeDir . '.offline' );
		}

		try {
			$output = $this->slicExec( "worktree {$command}; echo \"exit=\$?\"", $this->dockerMockLogEnv() );
			$list   = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
			$branch = $this->git( $pluginDir, 'branch --list feature-x' );
		} finally {
			if ( ! $present ) {
				rename( $worktreeDir . '.offline', $worktreeDir );
			}
			$this->git( $pluginDir, 'worktree unlock ' . escapeshellarg( $worktreeDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'A locked worktree should make the command fail.' );
		$this->assertStringContainsString( 'External disk', $output, "The failure should show git's lock reason." );
		$this->assertStringContainsString( ' worktree unlock ', $output, 'The failure should say how to unlock the worktree.' );
		$this->assertDockerCalled( '# down #', 'The stack of a locked worktree should not be brought down.', false );
		$this->assertStringContainsString( 'Branch: feature-x', $list, 'The stack of a locked worktree should stay registered.' );
		$this->assertStringContainsString( 'feature-x', $branch, 'The branch of a locked worktree should be kept.' );
	}

	public static function unreadableRepositoryProvider(): array {
		return [
			'remove, repository offline'                => [ 'remove feature-x -y', false ],
			'remove, empty directory at the repository' => [ 'remove feature-x -y', true ],
			'merge, empty directory at the repository'  => [ 'merge feature-x -y', true ],
			'sync, repository offline'                  => [ 'sync --clean -y', false ],
			'sync, empty directory at the repository'   => [ 'sync --clean -y', true ],
		];
	}

	#[DataProvider( 'unreadableRepositoryProvider' )]
	public function test_worktree_subcommands_change_nothing_when_git_cannot_read_the_repository( string $command, bool $emptyMount ): void {
		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		// An unmounted disk with the repository and its worktree: git cannot read the worktree records, or their locks.
		rename( $pluginDir, $pluginDir . '.offline' );
		rename( $worktreeDir, $worktreeDir . '.offline' );
		if ( $emptyMount ) {
			mkdir( $pluginDir );
		}

		try {
			$output = $this->slicExec( "worktree {$command}; echo \"exit=\$?\"", $this->dockerMockLogEnv() );
			$list   = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
		} finally {
			if ( $emptyMount ) {
				rmdir( $pluginDir );
			}
			rename( $pluginDir . '.offline', $pluginDir );
			rename( $worktreeDir . '.offline', $worktreeDir );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'A repository git cannot read should make the command fail.' );
		$this->assertStringContainsString( 'Cannot read the git worktree records of', $output, 'The failure should say git cannot read the repository.' );
		$this->assertDockerCalled( '# down #', 'The stack should not be brought down when git cannot say whether its worktree is locked.', false );
		$this->assertStringContainsString( 'Branch: feature-x', $list, 'The stack should stay registered.' );
	}

	public static function lockedAtThePromptProvider(): array {
		return [
			'remove, directory present' => [ 'worktree remove feature-x', true ],
			'merge, directory present'  => [ 'worktree merge feature-x', true ],
			'merge, directory offline'  => [ 'worktree merge feature-x', false ],
			'sync, directory offline'   => [ 'worktree sync --clean', false ],
		];
	}

	#[DataProvider( 'lockedAtThePromptProvider' )]
	public function test_worktree_subcommands_refuse_a_worktree_locked_while_waiting_for_confirmation( string $command, bool $present ): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $worktreeDir, 'feature.txt', 'feature' );
		if ( ! $present ) {
			rename( $worktreeDir, $worktreeDir . '.offline' );
		}
		$lock = 'git -C ' . escapeshellarg( $pluginDir ) . ' worktree lock --reason ' . escapeshellarg( 'External disk' ) . ' ' . escapeshellarg( $worktreeDir ) . ' >/dev/null 2>&1';

		try {
			$output = $this->slicExecInTerminal( $command, "y\n", $this->dockerMockLogEnv(), $lock );
			$list   = $this->slicExec( 'worktree list', $this->dockerMockEnv() );
			$locked = $this->git( $pluginDir, 'worktree list --porcelain' );
		} finally {
			if ( ! $present ) {
				rename( $worktreeDir . '.offline', $worktreeDir );
			}
			$this->git( $pluginDir, 'worktree unlock ' . escapeshellarg( $worktreeDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'locked External disk', $locked, 'The worktree should have been locked at the prompt.' );
		$this->assertStringContainsString( 'External disk', $output, "The failure should show git's lock reason." );
		$this->assertDockerCalled( '# down #', 'A worktree locked at the prompt should not be brought down.', false );
		$this->assertStringContainsString( 'Branch: feature-x', $list, 'A worktree locked at the prompt should stay registered.' );
	}

	public function test_worktree_merge_that_fails_before_merging_does_not_say_how_to_abort(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$this->commitFile( $pluginDir . '-feature-x', 'feature.txt', 'feature' );
		$this->commitFile( $pluginDir, 'base.txt', 'base' );
		// No git identity: the merge commit cannot be made, so git refuses the merge before it starts.
		$gitConfig = $this->createTempDir() . '/gitconfig';
		file_put_contents( $gitConfig, "[user]\n\tuseConfigOnly = true\n" );
		$noIdentity = [
			'GIT_CONFIG_GLOBAL'   => $gitConfig,
			'GIT_CONFIG_NOSYSTEM' => '1',
			'GIT_AUTHOR_NAME'     => '',
			'GIT_AUTHOR_EMAIL'    => '',
			'GIT_COMMITTER_NAME'  => '',
			'GIT_COMMITTER_EMAIL' => '',
			'EMAIL'               => '',
		];

		try {
			$output    = $this->slicExec( 'worktree merge feature-x -y; echo "exit=$?"', $noIdentity + $this->dockerMockEnv() );
			$mergeHead = $this->git( $pluginDir, 'rev-parse -q --verify MERGE_HEAD' );
		} finally {
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'A merge git refuses should fail.' );
		$this->assertSame( '', $mergeHead, 'The fixture merge should fail before it starts.' );
		$this->assertStringNotContainsString( 'merge --abort', $output, 'There is no merge to abort.' );
	}

	public function test_worktree_add_fails_when_the_registered_worktree_directory_is_not_a_worktree(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );
		mkdir( $pluginDir . '-feature-x' );

		try {
			$output = $this->slicExec( 'worktree add feature-x -y; echo "exit=$?"', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockEnv() );
		}

		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'Adding a registered worktree whose directory is not a worktree should fail.' );
		$this->assertStringContainsString( 'slic worktree sync --clean', $output, 'The failure should say how to clean up the stale stack.' );
	}

	public function test_worktree_sync_clean_fails_when_a_stack_fails_to_stop(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );

		try {
			$output = $this->slicExec( 'worktree sync --clean -y; echo "exit=$?"', [ 'SLIC_DOCKER_MOCK_FAIL' => ' down ' ] + $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( '1 failed', $output, 'Sync should report the stack it could not clean.' );
		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'Sync should fail when it could not clean a stack.' );
	}

	public static function sameDirectorySubcommandProvider(): array {
		return [
			'add'    => [ 'add' ],
			'remove' => [ 'remove' ],
			'merge'  => [ 'merge' ],
		];
	}

	#[DataProvider( 'sameDirectorySubcommandProvider' )]
	public function test_worktree_subcommands_refuse_a_branch_whose_directory_belongs_to_another_branch( string $subcommand ): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feat/c -y', $this->dockerMockEnv() );
		$this->commitFile( $pluginDir . '-feat-c', 'feature.txt', 'feature' );

		try {
			$output = $this->slicExec( "worktree {$subcommand} feat-c -y || echo {$subcommand}-failed", $this->dockerMockEnv() );
			$branch = $this->git( $pluginDir, 'branch --list feat/c' );
			$kept   = is_dir( $pluginDir . '-feat-c' );
		} finally {
			$this->slicExec( 'worktree remove feat/c -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( "{$subcommand}-failed", $output, "{$subcommand} should refuse a branch whose directory is another branch's worktree." );
		$this->assertTrue( $kept, "{$subcommand} should not touch the other branch's worktree." );
		$this->assertStringContainsString( 'feat/c', $branch, "{$subcommand} should not delete the other branch." );
	}

	public function test_worktree_add_fails_when_the_registered_worktree_directory_is_gone(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );

		try {
			$output = $this->slicExec( 'worktree add feature-x -y || echo add-failed', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'add-failed', $output, 'Adding a registered worktree whose directory is gone should fail.' );
		$this->assertStringContainsString( 'slic worktree sync --clean', $output, 'The failure should say how to clean up the stale stack.' );
	}

	public function test_worktree_remove_of_an_unknown_branch_fails_when_another_worktree_directory_is_gone(): void {
		$pluginDir = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		shell_exec( 'rm -rf ' . escapeshellarg( $pluginDir . '-feature-x' ) );

		try {
			$output = $this->slicExec( 'worktree remove no-such-branch -y || echo remove-failed', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'worktree sync --clean -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'Error: Worktree not found.', $output, 'A branch with no worktree should not be found.' );
		$this->assertStringContainsString( 'remove-failed', $output, 'Removing a branch with no worktree should fail.' );
	}

	public function test_an_auto_registered_worktree_merges_into_the_branch_it_was_registered_from(): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$baseBranch  = $this->git( $pluginDir, 'symbolic-ref --short HEAD' );
		$this->git( $pluginDir, 'worktree add -q ' . escapeshellarg( $worktreeDir ) . ' -b feature-x' );
		chdir( $worktreeDir );
		$registered = $this->slicExecInTerminal( 'stack info', "y\n" );
		chdir( $pluginDir );
		$this->commitFile( $worktreeDir, 'feature.txt', 'feature' );
		$this->git( $pluginDir, 'checkout -q -b other' );

		chdir( dirname( $pluginDir ) );
		$output = $this->slicExec( 'worktree merge feature-x -y', $this->dockerMockEnv() );

		$this->assertStringContainsString( 'Registered successfully!', $registered, 'The worktree should be auto-registered.' );
		$this->assertStringContainsString( "into '{$baseBranch}'", $output, 'Merge should target the branch the worktree was registered from.' );
		$this->assertSame( 'feature', $this->git( $pluginDir, 'show ' . escapeshellarg( $baseBranch . ':feature.txt' ) ), 'The registered base branch should get the worktree commit.' );
	}

	public function test_a_worktree_of_a_target_subdirectory_uses_that_subdirectory(): void {
		$pluginDir = $this->setUpPluginsDir() . '/test-plugin';
		mkdir( $pluginDir . '/common' );
		file_put_contents( $pluginDir . '/common/README.md', 'common' );
		$this->initGitRepository( $pluginDir );
		$this->slicExec( 'use test-plugin/common', $this->dockerMockEnv() );
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$worktreeDir = $pluginDir . '-feature-x';
		file_put_contents( $worktreeDir . '/common/codeception.slic.yml', '' );

		chdir( $worktreeDir );
		try {
			$using = $this->slicExec( 'using', $this->dockerMockEnv() );
			$run   = $this->slicExec( 'run wpunit', $this->dockerMockEnv() );
		} finally {
			chdir( dirname( $pluginDir ) );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertStringContainsString( 'Using test-plugin/common', $using, 'A worktree stack should use the subdirectory its base stack uses.' );
		$this->assertStringNotContainsString( 'No Codeception configuration file found', $run, 'A worktree stack should run the configuration in the subdirectory of the worktree.' );
	}

	public function test_here_in_an_unregistered_worktree_without_a_terminal_does_not_create_a_stack(): void {
		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->git( $pluginDir, 'worktree add -q ' . escapeshellarg( $worktreeDir ) . ' -b feature-x' );
		$worktreeDir = realpath( $worktreeDir );
		chdir( $worktreeDir );
		$this->createdStackIds[] = $worktreeDir;

		$output = $this->slicExec( 'here; echo "exit=$?"', $this->dockerMockEnv() );
		$list   = $this->slicExec( 'stack list', $this->dockerMockEnv() );

		$this->assertMatchesRegularExpression( '/exit=[1-9]/', $output, 'Here in an unregistered worktree should fail without a terminal to ask in.' );
		$this->assertStringContainsString( 'slic worktree add', $output, 'The failure should say how to register the worktree.' );
		$this->assertStringNotContainsString( $worktreeDir, $list, 'The worktree should not be registered as a stack of its own.' );
	}

	public function test_here_in_an_unregistered_worktree_does_not_create_a_stack_when_registration_is_declined(): void {
		if ( '' === trim( (string) shell_exec( 'command -v script' ) ) ) {
			$this->markTestSkipped( 'script is not installed.' );
		}

		$pluginDir   = $this->setUpGitPluginStack();
		$worktreeDir = $pluginDir . '-feature-x';
		$this->git( $pluginDir, 'worktree add -q ' . escapeshellarg( $worktreeDir ) . ' -b feature-x' );
		$worktreeDir = realpath( $worktreeDir );
		chdir( $worktreeDir );
		$this->createdStackIds[] = $worktreeDir;

		$output = $this->slicExecInTerminal( 'here', "n\n" );
		$list   = $this->slicExec( 'stack list', $this->dockerMockEnv() );

		$this->assertStringContainsString( 'slic worktree add', $output, 'Declining should say how to register the worktree.' );
		$this->assertStringNotContainsString( $worktreeDir, $list, 'The worktree should not be registered as a stack of its own.' );
	}

	public function test_stopping_a_worktree_stack_from_its_base_stack_loads_the_worktree_settings(): void {
		$pluginDir   = $this->setUpGitPluginStack();
		$this->slicExec( 'worktree add feature-x -y', $this->dockerMockEnv() );
		$worktreeDir = $pluginDir . '-feature-x';
		file_put_contents( $pluginDir . '/.env.slic.local', "SLIC_PHP_VERSION=8.1\n" );
		file_put_contents( $worktreeDir . '/.env.slic.local', "SLIC_PHP_VERSION=8.3\n" );
		$pluginsDir = realpath( dirname( $pluginDir ) );

		try {
			$this->slicExec(
				'stack stop ' . escapeshellarg( $pluginsDir . '@test-plugin-feature-x' ) . ' -y',
				$this->dockerMockLogEnv( [ 'SLIC_PHP_VERSION' ] )
			);
		} finally {
			unlink( $worktreeDir . '/.env.slic.local' );
			$this->slicExec( 'worktree remove feature-x -y', $this->dockerMockEnv() );
		}

		$this->assertDockerCalled( '# down .*\[SLIC_PHP_VERSION=8\.3\]$#', 'Stopping a worktree stack should use the PHP version set in the worktree.' );
	}

	private function setUpGitPluginStack(): string {
		$pluginDir = $this->setUpPluginsDir() . '/test-plugin';
		$this->initGitRepository( $pluginDir );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		return $pluginDir;
	}

	/**
	 * Sets up a site stack whose `test-theme` target is a git repository, selects it, and changes to its plugins directory.
	 *
	 * @return string The absolute path to the theme repository.
	 */
	private function setUpGitThemeStack(): string {
		$site = $this->createTempDir();
		mkdir( $site . '/wp-content/plugins', 0777, true );
		mkdir( $site . '/wp-content/themes/test-theme', 0777, true );
		touch( $site . '/wp-config.php' );
		file_put_contents( $site . '/wp-content/themes/test-theme/style.css', "/*\nTheme Name: Test Theme\n*/" );
		$this->initGitRepository( $site . '/wp-content/themes/test-theme' );

		chdir( $site );
		$this->slicExec( 'here' );
		// The stack id is the site, or its plugins directory before slic 3.0; the plugins directory resolves to either.
		$this->createdStackIds[] = realpath( $site );
		$this->createdStackIds[] = realpath( $site . '/wp-content/plugins' );
		chdir( $site . '/wp-content/plugins' );
		$this->slicExec( 'use test-theme', $this->dockerMockEnv() );

		return $site . '/wp-content/themes/test-theme';
	}

	/**
	 * Runs a git command in a repository, with the fixture identity, and returns its trimmed output.
	 *
	 * @param string $dir  The absolute path to the repository.
	 * @param string $args The git arguments, escaped if required.
	 *
	 * @return string The trimmed output.
	 */
	private function git( string $dir, string $args ): string {
		return trim( (string) shell_exec( 'git -C ' . escapeshellarg( $dir ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false ' . $args . ' 2>/dev/null' ) );
	}

	/**
	 * Writes a file in a repository and commits it.
	 *
	 * @param string $dir     The absolute path to the repository or worktree.
	 * @param string $file    The file path relative to the repository.
	 * @param string $content The file content.
	 */
	private function commitFile( string $dir, string $file, string $content ): void {
		file_put_contents( $dir . '/' . $file, $content );
		$this->git( $dir, 'add ' . escapeshellarg( $file ) );
		$this->git( $dir, 'commit -q -m ' . escapeshellarg( $file ) );
	}

	/**
	 * Runs a slic command in a pseudo-terminal, where slic asks its interactive questions, and returns the output.
	 *
	 * @param string $command      The command to execute, escaped if required.
	 * @param string $input        The answers to type at the prompts.
	 * @param array  $env          Environment variables to set, on top of the Docker mock ones.
	 * @param string $beforeAnswer A shell command to run once slic asks, before the answers are typed.
	 *
	 * @return string The command output.
	 */
	private function slicExecInTerminal( string $command, string $input, array $env = [], string $beforeAnswer = ':' ): string {
		$slic = 'php ' . escapeshellarg( self::slicRoot() . '/slic.php' ) . ' ' . $command;
		// util-linux script (Linux) takes the command with -c; BSD script (macOS) takes it as trailing arguments.
		$inTerminal = str_contains( (string) shell_exec( 'script --version 2>&1' ), 'util-linux' )
			? 'script -qec ' . escapeshellarg( $slic ) . ' /dev/null'
			: 'script -q /dev/null ' . $slic;

		$envString = '';
		foreach ( [ 'NO_COLOR' => '1' ] + $env + $this->dockerMockEnv() as $key => $value ) {
			$envString .= $key . '=' . escapeshellarg( $value ) . ' ';
		}

		// Type the answers once slic asks, for at most 10 seconds: script drops what arrives before the terminal is set up.
		$outputFile = $this->createTempDir() . '/terminal-output';
		$waitForPrompt = 'i=0; while ! grep -q "\[y/N\]" ' . escapeshellarg( $outputFile ) . ' 2>/dev/null && [ $i -lt 100 ]; do sleep 0.1; i=$((i+1)); done';
		shell_exec( '{ ' . $waitForPrompt . '; ' . $beforeAnswer . '; printf %s ' . escapeshellarg( $input ) . '; } | ' . $envString . $inTerminal . ' > ' . escapeshellarg( $outputFile ) . ' 2>&1' );

		return (string) file_get_contents( $outputFile );
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
