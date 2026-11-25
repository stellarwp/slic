<?php

namespace StellarWP\Slic\Test\Cli;

class PhpcsPhpcbfTest extends BaseTestCase {

	public function test_phpcs_requires_use_target(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'phpcs' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running phpcs without a use target should show an error.'
		);
	}

	public function test_phpcs_with_target_runs(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'phpcs', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'Running phpcs with a use target should show the target name.'
		);
	}

	public function test_phpcbf_requires_use_target(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'phpcbf' );

		$this->assertStringContainsString(
			'This command requires a target set using the use command.',
			$output,
			'Running phpcbf without a use target should show an error.'
		);
	}

	public function test_phpcbf_with_target_runs(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'phpcbf', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Using test-plugin',
			$output,
			'Running phpcbf with a use target should show the target name.'
		);
	}
}
