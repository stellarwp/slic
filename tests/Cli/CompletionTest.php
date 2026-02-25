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
		$output = $this->slicExec( 'completion' );

		$this->assertStringContainsString(
			'Detected shell:',
			$output,
			'The output should show the detected shell.'
		);

		$this->assertStringContainsString(
			'completion cache-clear',
			$output,
			'The output should mention the cache-clear subcommand.'
		);
	}
}
