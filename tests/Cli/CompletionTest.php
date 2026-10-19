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
		$this->complete( 'slic worktree add ', 3 );
		chdir( $secondRepo );

		$this->assertStringContainsString(
			'only-in-second',
			$this->complete( 'slic worktree add ', 3 ),
			'Branch completions should come from the current repository.'
		);
	}

	/**
	 * Runs the completion script the way the bash and zsh completion functions call it.
	 *
	 * @param string $words The command line words, separated by spaces.
	 * @param int    $cword The index of the word being completed.
	 *
	 * @return string The completion script output.
	 */
	private function complete( string $words, int $cword ): string {
		$command = 'SLIC_CACHE_DIR=' . escapeshellarg( $this->cacheDir )
		           . ' php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/src/completions/complete.php' )
		           . ' --words=' . escapeshellarg( $words )
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
