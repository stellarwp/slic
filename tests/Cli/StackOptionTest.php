<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class StackOptionTest extends BaseTestCase {

	public function test_stack_option_accepts_an_existing_path_that_contains_an_at_sign(): void {
		$path = $this->setUpPluginsDir( 'test-plugin', '/slic-test@stack-' . uniqid( '', true ) );

		$this->assertStringContainsString(
			'Available commands:',
			$this->slicExec( '--stack=' . escapeshellarg( $path ) . ' help' ),
			'An existing directory should not be read as a worktree stack ID.'
		);
	}

	public function test_stack_variable_expands_the_home_directory_in_a_path_that_contains_an_at_sign(): void {
		$pluginsDir = $this->setUpPluginsDir( 'test-plugin', '/slic-test-home-' . uniqid( '', true ) . '/project@v2/plugins' );
		$env        = [ 'HOME' => dirname( $pluginsDir, 2 ), 'SLIC_STACK' => '~/project@v2/plugins' ] + $this->dockerMockEnv();

		$this->assertStringContainsString(
			'Stack ID: ' . realpath( $pluginsDir ),
			$this->slicExec( 'stack info', $env ),
			'A home-relative stack path should resolve even when it contains @.'
		);
	}

	public function test_a_stack_in_a_path_that_contains_an_at_sign_is_not_a_worktree(): void {
		$this->setUpPluginsDir( 'test-plugin', '/slic-test@' . uniqid( '', true ) . '/plugins' );

		$this->assertStringContainsString(
			'Using test-plugin',
			$this->slicExec( 'use test-plugin', $this->dockerMockEnv() ),
			'A base stack whose path contains @ should switch targets like any other base stack.'
		);
	}

	public function test_stack_option_with_a_directory_that_is_not_a_stack_fails(): void {
		$this->setUpPluginsDir();
		$dir = realpath( $this->createTempDir() );

		$this->assertStringContainsString(
			"{$dir} is not a registered stack",
			$this->slicExec( '--stack=' . escapeshellarg( $dir ) . ' using', $this->dockerMockEnv() ),
			'An explicit stack that is not registered should fail, not run on the unnamed project.'
		);
	}

	public function test_stack_variable_that_is_not_a_stack_fails(): void {
		$this->setUpPluginsDir();

		$this->assertStringContainsString(
			'/no/such/stack is not a registered stack',
			$this->slicExec( 'using', [ 'SLIC_STACK' => '/no/such/stack' ] + $this->dockerMockEnv() ),
			'A SLIC_STACK that is not registered should fail, not run on the unnamed project.'
		);
	}
}
