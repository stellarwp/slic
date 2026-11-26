<?php

namespace StellarWP\Slic\Test\Cli;


class CompletionTest extends BaseTestCase {

	private string $cacheDir;

	public function setUp(): void {
		parent::setUp();
		$this->cacheDir = sys_get_temp_dir() . '/slic-completion-cache-' . uniqid( '', true );
	}

	public function test_worktree_branches_come_from_the_target_repository(): void {
		$this->setUpGitTargetStackWithBranch( 'only-in-target' );

		// The stack root is not a git repository; `worktree add` works on the target's repository.
		$this->assertContains(
			'only-in-target',
			explode( "\n", $this->complete( [ 'slic', 'worktree', 'add', '' ], 3 ) ),
			'Branch completions should come from the repository of the stack target.'
		);
	}

	public function test_a_branch_checked_out_in_another_worktree_completes_without_its_marker(): void {
		$repo = $this->setUpGitTargetStackWithBranch( 'only-in-target' );
		shell_exec( 'git -C ' . escapeshellarg( $repo ) . ' worktree add -q -b fix/x ' . escapeshellarg( $this->createTempDir() . '/wt' ) );

		$this->assertContains(
			'fix/x',
			explode( "\n", $this->complete( [ 'slic', 'worktree', 'remove', '' ], 3 ) ),
			'A branch checked out in another worktree should complete without git\'s "+ " marker.'
		);
	}

	public function test_a_newly_registered_stack_completes_at_once(): void {
		chdir( $this->createTempDir() );
		$this->complete( [ 'slic', 'stack', 'stop', '' ], 3 );
		$stackId = realpath( $this->setUpPluginsDir( 'late-plugin' ) );

		$this->assertContains(
			$stackId,
			explode( "\n", $this->complete( [ 'slic', 'stack', 'stop', '' ], 3 ) ),
			'A stack registered after a completion should complete right away.'
		);
	}

	public function test_commands_left_in_the_cache_by_an_older_slic_do_not_hide_the_current_ones(): void {
		mkdir( $this->cacheDir . '/completions', 0777, true );
		file_put_contents(
			$this->cacheDir . '/completions/commands.json',
			json_encode( [ 'created_at' => time(), 'ttl' => 86400, 'data' => [ 'old-command' ] ] )
		);

		$this->assertContains(
			'worktree',
			explode( "\n", $this->complete( [ 'slic', '' ], 1 ) ),
			'Command completions should list the commands of this slic version.'
		);
	}

	public function test_an_empty_cache_file_is_a_cache_miss(): void {
		$this->setUpPluginsDir( 'test-plugin' );
		mkdir( $this->cacheDir . '/completions', 0777, true );
		// An interrupted cache write leaves a zero-byte file behind.
		touch( $this->cacheDir . '/completions/targets-' . md5( (string) getcwd() ) . '.json' );

		$this->assertContains(
			'test-plugin',
			explode( "\n", $this->complete( [ 'slic', 'use', '' ], 2 ) ),
			'An empty cache file should be ignored, not break completion.'
		);
	}

	public function test_use_completes_when_the_cache_dir_cannot_be_created(): void {
		$this->setUpPluginsDir( 'test-plugin' );
		$file = $this->createTempDir() . '/a-file';
		touch( $file );
		// A directory cannot be created inside a regular file.
		$this->cacheDir = $file . '/cache';

		$this->assertContains(
			'test-plugin',
			explode( "\n", $this->complete( [ 'slic', 'use', '' ], 2 ) ),
			'Completion should work without a usable cache directory.'
		);
	}

	public function test_use_completes_the_sub_folders_of_a_plugin_with_theme_in_its_name(): void {
		$pluginsDir = $this->setUpPluginsDir( 'theme-my-login' );
		mkdir( $pluginsDir . '/theme-my-login/lib' );
		touch( $pluginsDir . '/theme-my-login/lib/composer.json' );

		$this->assertContains(
			'theme-my-login/lib',
			explode( "\n", $this->complete( [ 'slic', 'use', '' ], 2 ) ),
			'A plugin with "theme" in its name is still a plugin, so its sub-folders should complete.'
		);
	}

	public function test_a_leading_quiet_flag_does_not_hide_the_command(): void {
		$this->assertContains(
			'list',
			explode( "\n", $this->complete( [ 'slic', '-q', 'stack', '' ], 3 ) ),
			'The command after -q should get its completions.'
		);
	}

	public function test_a_leading_stack_option_selects_the_stack_to_complete_from(): void {
		$firstStack = realpath( $this->setUpPluginsDir( 'first-plugin' ) );
		$this->setUpPluginsDir( 'second-plugin' );
		chdir( $this->createTempDir() );

		$completions = explode( "\n", $this->complete( [ 'slic', '--stack', $firstStack, 'use', '' ], 4 ) );

		$this->assertContains( 'first-plugin', $completions, 'The command after --stack should complete from that stack.' );
		$this->assertNotContains( 'second-plugin', $completions, 'Another stack\'s targets should not complete.' );
	}

	public function test_bash_completes_a_stack_path_after_an_equals_sign(): void {
		$stackId = realpath( $this->setUpPluginsDir( 'test-plugin' ) );

		// Bash splits --stack=<path> at the =, so the path arrives as its own word after an = word.
		$this->assertContains(
			$stackId,
			$this->completeInBash( [ 'slic', '--stack', '=', substr( $stackId, 0, -3 ) ], 3 ),
			'Bash should complete the path in --stack=<path>.'
		);
	}

	public function test_bash_does_not_run_commands_found_in_candidates(): void {
		$pluginsDir = $this->setUpPluginsDir( 'test-plugin' );
		mkdir( $pluginsDir . '/test-plugin/$(touch slic-pwned)' );
		touch( $pluginsDir . '/test-plugin/$(touch slic-pwned)/composer.json' );

		$completions = $this->completeInBash( [ 'slic', 'use', '' ], 2 );

		$this->assertFileDoesNotExist( $pluginsDir . '/slic-pwned', 'A candidate should never be run as a command.' );
		$this->assertContains( 'test-plugin', $completions, 'Plain candidates should still complete.' );
		$this->assertContains( 'test-plugin/\$\(touch\ slic-pwned\)', $completions, 'An unusual candidate should complete escaped.' );
	}

	public function test_fish_completes_the_word_under_the_cursor(): void {
		if ( '' === trim( (string) shell_exec( 'command -v fish' ) ) ) {
			$this->markTestSkipped( 'fish is not installed.' );
		}

		$this->assertStringContainsString(
			'add',
			$this->runInShell( 'fish', 'source ' . escapeshellarg( self::slicRoot() . '/completions/fish/slic.fish' ) . '; complete -C "slic worktree a"' ),
			'Fish should complete the word under the cursor.'
		);
	}

	public function test_use_completes_the_plugins_of_the_current_stack(): void {
		$this->setUpPluginsDir( 'test-plugin' );

		$this->assertContains(
			'test-plugin',
			explode( "\n", $this->complete( [ 'slic', 'use', '' ], 2 ) ),
			'The use command should complete the plugins of the current stack.'
		);
	}

	public function test_bash_completes_without_bash_completion_loaded(): void {
		$this->assertContains(
			'stack',
			$this->completeInBash( [ 'slic', 'st' ], 1 ),
			'Bash should complete commands when the bash-completion package is not loaded.'
		);
	}

	public function test_bash_completes_a_stack_path_that_contains_a_space(): void {
		$stackId = realpath( $this->setUpPluginsDir( 'test-plugin', '/slic test plugins ' . uniqid( '', true ) ) );

		$this->assertContains(
			str_replace( ' ', '\ ', $stackId ),
			$this->completeInBash( [ 'slic', '--stack', '' ], 2 ),
			'A stack path with spaces should be one escaped completion.'
		);
	}

	public function test_bash_follows_a_relative_slic_symlink_with_only_a_basic_readlink(): void {
		$this->assertContains(
			'stack',
			$this->completeInBash( [ 'slic', 'st' ], 1, $this->createBinDirWithRelativeLinkAndBasicReadlink() ),
			'Bash should find slic through a relative symlink without realpath or readlink -f.'
		);
	}

	public function test_installed_bash_config_follows_a_relative_slic_symlink_with_only_a_basic_readlink(): void {
		$home = sys_get_temp_dir() . '/slic-test-home-' . uniqid( '', true );
		mkdir( $home );
		touch( $home . '/.bashrc' );
		// The installer asks for confirmation; answer it so the test never waits on the terminal.
		shell_exec( "printf 'y\\n' | HOME=" . escapeshellarg( $home ) . ' NO_COLOR=1 php '
		            . escapeshellarg( self::slicRoot() . '/slic.php' ) . ' completion install bash 2>&1' );

		$this->assertStringContainsString(
			'_slic_completions',
			$this->runInShell(
				'bash',
				'source ' . escapeshellarg( $home . '/.bashrc' ) . '; declare -F _slic_completions',
				$this->createBinDirWithRelativeLinkAndBasicReadlink()
			),
			'The installed bash config should load the completions through a relative slic symlink.'
		);
	}

	public function test_zsh_completes_the_word_under_the_cursor(): void {
		if ( '' === trim( (string) shell_exec( 'command -v zsh' ) ) ) {
			$this->markTestSkipped( 'zsh is not installed.' );
		}

		$this->assertStringContainsString(
			'stack',
			$this->runInShell( 'zsh', '_describe() { print -l -- "${(@P)2}"; }; words=(slic st); CURRENT=2; BUFFER="slic st"; CURSOR=7; source ' . escapeshellarg( self::slicRoot() . '/completions/zsh/_slic' ) ),
			'Zsh should complete the word under the cursor.'
		);
	}

	public function test_completion_show_bash(): void {
		$output = $this->slicExec( 'completion show bash' );

		// The output is redirected to a file, so it must be the script and nothing else.
		$this->assertStringStartsWith(
			'#!/usr/bin/env bash',
			$output,
			'The output should start with the bash shebang.'
		);

		$this->assertStringContainsString(
			'_slic_completions',
			$output,
			'The output should contain the bash completion function.'
		);
	}

	public function test_completion_show_zsh(): void {
		$output = $this->slicExec( 'completion show zsh' );

		// compinit only loads a file whose first line is the compdef directive.
		$this->assertStringStartsWith(
			'#compdef slic',
			$output,
			'The output should start with the zsh compdef directive.'
		);

		$this->assertStringContainsString(
			'_slic',
			$output,
			'The output should contain the zsh completion function.'
		);
	}

	public function test_completion_show_prints_no_update_notice(): void {
		$this->setUpPluginsDir();
		$buildVersionFile = self::slicRoot() . '/.build-version';
		$buildVersion     = is_file( $buildVersionFile ) ? file_get_contents( $buildVersionFile ) : null;
		// An older build version makes slic print the "containers are not up to date" notice.
		file_put_contents( $buildVersionFile, '0.0.1' );

		try {
			$output = $this->slicExec( 'completion show zsh' );
		} finally {
			if ( null === $buildVersion ) {
				unlink( $buildVersionFile );
			} else {
				file_put_contents( $buildVersionFile, $buildVersion );
			}
		}

		$this->assertStringStartsWith(
			'#compdef slic',
			$output,
			'An update notice should not precede the completion script.'
		);
	}

	public function test_completion_cache_clear(): void {
		$output = $this->slicExec( 'completion cache-clear' );

		$this->assertStringContainsString(
			'Clearing completion cache',
			$output,
			'The output should mention clearing the cache.'
		);

		$this->assertMatchesRegularExpression(
			'/Cleared \d+ cached completion file/',
			$output,
			'The output should confirm how many cached files were cleared.'
		);
	}

	public function test_completion_no_args_shows_instructions(): void {
		// Use a clean HOME so is_installed() returns false, ensuring install instructions are shown.
		$tempHome = $this->createTempDir();

		// The detected shell comes from SHELL, so set it instead of inheriting the one running the tests.
		$output = $this->slicExec( 'completion', [ 'HOME' => $tempHome, 'SHELL' => '/bin/bash' ] );

		$this->assertStringContainsString(
			'Detected shell:',
			$output,
			'The output should show the detected shell.'
		);

		$this->assertStringContainsString(
			'completion install',
			$output,
			'The output should mention the completion install subcommand.'
		);
	}

	public function test_completion_install_confirm_writes_config(): void {
		$tempHome = $this->createTempDir();
		file_put_contents( $tempHome . '/.bashrc', '' );

		$output = $this->slicExec(
			'completion install bash',
			[ 'HOME' => $tempHome ],
			"yes\n"
		);

		$bashrc = file_get_contents( $tempHome . '/.bashrc' );

		$this->assertStringContainsString(
			'Continue with installation?',
			$output,
			'The install flow should prompt for confirmation.'
		);
		$this->assertStringContainsString(
			'completions installed successfully',
			$output,
			'The output should confirm successful installation.'
		);
		$this->assertStringContainsString(
			'slic completions',
			$bashrc,
			'The .bashrc file should contain the slic completions block.'
		);
	}

	public function test_completion_install_decline_cancels(): void {
		$tempHome = $this->createTempDir();
		file_put_contents( $tempHome . '/.bashrc', '' );

		$output = $this->slicExec(
			'completion install bash',
			[ 'HOME' => $tempHome ],
			"no\n"
		);

		$bashrc = file_get_contents( $tempHome . '/.bashrc' );

		$this->assertStringContainsString(
			'Installation cancelled.',
			$output,
			'Declining the prompt should cancel installation.'
		);
		$this->assertEmpty(
			$bashrc,
			'The .bashrc file should remain untouched after cancellation.'
		);
	}

	public function test_completion_install_fish_confirm_creates_symlink(): void {
		$tempHome = $this->createTempDir();
		mkdir( $tempHome . '/.config/fish/completions', 0777, true );

		$output = $this->slicExec(
			'completion install fish',
			[ 'HOME' => $tempHome ],
			"yes\n"
		);

		$symlinkPath = $tempHome . '/.config/fish/completions/slic.fish';

		$this->assertStringContainsString(
			'completions installed successfully',
			$output,
			'The output should confirm successful fish installation.'
		);
		$this->assertTrue(
			is_link( $symlinkPath ),
			'A symlink should be created for the fish completion script.'
		);
	}

	/**
	 * Runs the bash completion function for a command line and returns its completions.
	 *
	 * @param string[]    $words  The command line words.
	 * @param int         $cword  The index of the word being completed.
	 * @param string|null $binDir The directory that provides `slic` on the PATH, or `null` for the default one.
	 *
	 * @return string[] The completions, one per element.
	 */
	private function completeInBash( array $words, int $cword, ?string $binDir = null ): array {
		$script = 'source ' . escapeshellarg( self::slicRoot() . '/completions/bash/slic.bash' )
		          . '; COMP_WORDS=(' . implode( ' ', array_map( 'escapeshellarg', $words ) ) . ')'
		          . '; COMP_CWORD=' . $cword
		          . '; COMP_LINE=' . escapeshellarg( implode( ' ', $words ) )
		          . '; COMP_POINT=${#COMP_LINE}'
		          . '; _slic_completions; printf "%s\n" "${COMPREPLY[@]}"';

		return array_values( array_filter( explode( "\n", $this->runInShell( 'bash', $script, $binDir ) ) ) );
	}

	/**
	 * Creates a PATH directory where `slic` is a relative symlink, as on a system without `realpath` or `readlink -f`.
	 *
	 * @return string The absolute path to the directory.
	 */
	private function createBinDirWithRelativeLinkAndBasicReadlink(): string {
		$binDir = sys_get_temp_dir() . '/slic-completion-bin-' . uniqid( '', true );
		mkdir( $binDir . '/install', 0777, true );
		symlink( self::slicRoot() . '/slic', $binDir . '/install/slic' );
		symlink( 'install/slic', $binDir . '/slic' );

		// Stand-ins for a system that has neither `realpath` nor `readlink -f`, only the basic `readlink`.
		$readlink = trim( (string) shell_exec( 'command -v readlink' ) );
		file_put_contents( $binDir . '/realpath', "#!/bin/sh\nexit 1\n" );
		file_put_contents( $binDir . '/readlink', "#!/bin/sh\n[ \"\$1\" = -f ] && exit 1\nexec {$readlink} \"\$@\"\n" );
		chmod( $binDir . '/realpath', 0755 );
		chmod( $binDir . '/readlink', 0755 );

		return $binDir;
	}

	/**
	 * Runs a script in a shell where `slic` resolves to this checkout, as it does once slic is installed.
	 *
	 * @param string      $shell  The shell binary, e.g. `bash`.
	 * @param string      $script The script to run.
	 * @param string|null $binDir The directory that provides `slic` on the PATH, or `null` to link it directly.
	 *
	 * @return string The script output.
	 */
	private function runInShell( string $shell, string $script, ?string $binDir = null ): string {
		if ( null === $binDir ) {
			$binDir = sys_get_temp_dir() . '/slic-completion-bin-' . uniqid( '', true );
			mkdir( $binDir );
			symlink( self::slicRoot() . '/slic', $binDir . '/slic' );
		}

		$command = 'PATH=' . escapeshellarg( $binDir . ':' . getenv( 'PATH' ) )
		           . ' SLIC_CACHE_DIR=' . escapeshellarg( $this->cacheDir )
		           . ' ' . $shell . ' -c ' . escapeshellarg( $script );

		return (string) shell_exec( $command . ' 2>&1' );
	}

	/**
	 * Runs the completion script the way the bash and zsh completion functions call it.
	 *
	 * @param string[] $words The command line words.
	 * @param int      $cword The index of the word being completed.
	 *
	 * @return string The completion script output.
	 */
	private function complete( array $words, int $cword ): string {
		$command = 'SLIC_CACHE_DIR=' . escapeshellarg( $this->cacheDir )
		           . ' php ' . escapeshellarg( self::slicRoot() . '/src/completions/complete.php' )
		           . ' --words=' . escapeshellarg( implode( "\n", $words ) )
		           . ' --cword=' . $cword;

		return (string) shell_exec( $command . ' 2>&1' );
	}

	/**
	 * Sets up a stack whose target is a git repository with one commit and an extra branch, from the stack root.
	 *
	 * @param string $branch The name of the extra branch.
	 *
	 * @return string The absolute path to the target repository.
	 */
	private function setUpGitTargetStackWithBranch( string $branch ): string {
		$repo = $this->setUpPluginsDir( 'test-plugin' ) . '/test-plugin';
		// Fixture commits must not depend on the host git identity or commit signing settings.
		$git = 'git -C ' . escapeshellarg( $repo ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false';
		shell_exec( "{$git} init -q && {$git} add -A && {$git} commit -q -m init && {$git} branch " . escapeshellarg( $branch ) );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		return $repo;
	}
}
