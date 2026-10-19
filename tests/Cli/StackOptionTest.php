<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class StackOptionTest extends BaseTestCase {

	public function test_stack_option_accepts_an_existing_path_that_contains_an_at_sign(): void {
		$path = Directory::createTemp( '/slic-test@stack-' . uniqid( '', true ) )
		                 ->createPlugin( 'test-plugin' )
		                 ->getAbsolutePath();

		$this->assertStringContainsString(
			'Available commands:',
			$this->slicExec( '--stack=' . escapeshellarg( $path ) . ' help' ),
			'An existing directory should not be read as a worktree stack ID.'
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
}
