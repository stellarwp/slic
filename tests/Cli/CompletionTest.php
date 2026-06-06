<?php

namespace StellarWP\Slic\Test\Cli;

class CompletionTest extends BaseTestCase {

	public function test_completion_show_bash(): void {
		$output = $this->slicExec( 'completion show bash' );

		$this->assertStringContainsString(
			'Completion script for bash:',
			$output,
			'The output should indicate the bash completion script.'
		);

		$this->assertStringContainsString(
			'#!/usr/bin/env bash',
			$output,
			'The output should contain a bash shebang.'
		);

		$this->assertStringContainsString(
			'_slic_completions',
			$output,
			'The output should contain the bash completion function.'
		);
	}

	public function test_completion_show_zsh(): void {
		$output = $this->slicExec( 'completion show zsh' );

		$this->assertStringContainsString(
			'Completion script for zsh:',
			$output,
			'The output should indicate the zsh completion script.'
		);

		$this->assertStringContainsString(
			'#compdef slic',
			$output,
			'The output should contain the zsh compdef directive.'
		);

		$this->assertStringContainsString(
			'_slic',
			$output,
			'The output should contain the zsh completion function.'
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

		$output = $this->slicExec( 'completion', [ 'HOME' => $tempHome ] );

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
}
