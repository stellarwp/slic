<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class CompletionTest extends BaseTestCase {

	private string $cacheDir;

	public function setUp(): void {
		parent::setUp();
		$this->cacheDir = sys_get_temp_dir() . '/slic-completion-cache-' . uniqid( '', true );
	}

	public function test_branch_completions_are_not_shared_between_repositories(): void {
		$firstRepo  = $this->createGitRepositoryWithBranch( 'only-in-first' );
		$secondRepo = $this->createGitRepositoryWithBranch( 'only-in-second' );

		chdir( $firstRepo );
		$this->complete( [ 'slic', 'worktree', 'add', '' ], 3 );
		chdir( $secondRepo );

		$this->assertStringContainsString(
			'only-in-second',
			$this->complete( [ 'slic', 'worktree', 'add', '' ], 3 ),
			'Branch completions should come from the current repository.'
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

	public function test_zsh_completes_the_word_under_the_cursor(): void {
		if ( '' === trim( (string) shell_exec( 'command -v zsh' ) ) ) {
			$this->markTestSkipped( 'zsh is not installed.' );
		}

		$this->assertStringContainsString(
			'stack',
			$this->runInShell( 'zsh', '_describe() { print -l -- "${(@P)2}"; }; words=(slic st); CURRENT=2; BUFFER="slic st"; CURSOR=7; source ' . escapeshellarg( dirname( __DIR__, 2 ) . '/completions/zsh/_slic' ) ),
			'Zsh should complete the word under the cursor.'
		);
	}

	/**
	 * Runs the bash completion function for a command line and returns its completions.
	 *
	 * @param string[] $words The command line words.
	 * @param int      $cword The index of the word being completed.
	 *
	 * @return string[] The completions, one per element.
	 */
	private function completeInBash( array $words, int $cword ): array {
		$script = 'source ' . escapeshellarg( dirname( __DIR__, 2 ) . '/completions/bash/slic.bash' )
		          . '; COMP_WORDS=(' . implode( ' ', array_map( 'escapeshellarg', $words ) ) . ')'
		          . '; COMP_CWORD=' . $cword
		          . '; COMP_LINE=' . escapeshellarg( implode( ' ', $words ) )
		          . '; COMP_POINT=${#COMP_LINE}'
		          . '; _slic_completions; printf "%s\n" "${COMPREPLY[@]}"';

		return array_values( array_filter( explode( "\n", $this->runInShell( 'bash', $script ) ) ) );
	}

	/**
	 * Runs a script in a shell where `slic` resolves to this checkout, as it does once slic is installed.
	 *
	 * @param string $shell  The shell binary, e.g. `bash`.
	 * @param string $script The script to run.
	 *
	 * @return string The script output.
	 */
	private function runInShell( string $shell, string $script ): string {
		$binDir = sys_get_temp_dir() . '/slic-completion-bin-' . uniqid( '', true );
		mkdir( $binDir );
		symlink( dirname( __DIR__, 2 ) . '/slic', $binDir . '/slic' );

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
		           . ' php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/src/completions/complete.php' )
		           . ' --words=' . escapeshellarg( implode( "\n", $words ) )
		           . ' --cword=' . $cword;

		return (string) shell_exec( $command . ' 2>&1' );
	}

	/**
	 * Creates a git repository with one commit and an extra branch.
	 *
	 * @param string $branch The name of the extra branch.
	 *
	 * @return string The absolute path to the repository.
	 */
	private function createGitRepositoryWithBranch( string $branch ): string {
		$repo = Directory::createTemp( '/slic-test-repo-' . uniqid( '', true ) )
		                 ->createPlugin( 'repo' )
		                 ->getAbsolutePath() . '/repo';
		// Fixture commits must not depend on the host git identity or commit signing settings.
		$git = 'git -C ' . escapeshellarg( $repo ) . ' -c user.name=slic -c user.email=slic@example.com -c commit.gpgsign=false';
		shell_exec( "{$git} init -q && {$git} add -A && {$git} commit -q -m init && {$git} branch " . escapeshellarg( $branch ) );

		return $repo;
	}
}
