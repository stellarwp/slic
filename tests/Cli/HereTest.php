<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class HereTest extends BaseTestCase {

	public function test_here_sets_plugins_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$output = $this->slicExec( 'stack list' );

		$this->assertStringContainsString(
			realpath( $pluginsDir ),
			$output,
			'The stack list should contain the plugins directory.'
		);
	}

	public function test_here_output_confirms_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		chdir( $pluginsDir );
		$output = $this->slicExec( 'here' );

		$this->assertStringContainsString(
			realpath( $pluginsDir ),
			$output,
			'The here output should contain the set directory path.'
		);
	}

	public function test_here_reset_unsets_directory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		$resetOutput = $this->slicExec( 'here reset' );

		// The reset output should reference the default _plugins directory as the new stack ID.
		$this->assertStringContainsString(
			'_plugins',
			$resetOutput,
			'The here reset output should reference the default _plugins directory.'
		);

		$this->assertStringContainsString(
			'Stack',
			$resetOutput,
			'The here reset output should confirm a stack operation.'
		);
	}

	public function test_here_from_subdirectory(): void {
		$pluginsDir = $this->setUpPluginsDir();

		chdir( $pluginsDir . '/test-plugin' );
		$output                  = $this->slicExec( 'here' );
		$this->createdStackIds[] = realpath( $pluginsDir . '/test-plugin' );

		$this->assertStringContainsString(
			realpath( $pluginsDir . '/test-plugin' ),
			$output,
			'Running here from a plugin subdirectory should set that subdirectory as the stack ID.'
		);
	}

	public function test_here_in_non_plugin_directory_still_creates_stack(): void {
		// First set up a valid plugins dir so we have a registered stack context.
		$this->setUpPluginsDir();

		$emptyDir = $this->createTempDir();
		chdir( $emptyDir );

		$output                  = $this->slicExec( 'here' );
		$this->createdStackIds[] = realpath( $emptyDir );

		$this->assertStringContainsString(
			'Stack created successfully',
			$output,
			'Running here in a directory without plugins should still create a stack.'
		);

		$this->assertStringContainsString(
			realpath( $emptyDir ),
			$output,
			'The stack ID should reference the empty directory.'
		);
	}

	public function test_a_site_stack_is_found_from_the_site_root_and_its_themes(): void {
		$this->setUpPluginsDir( 'other-plugin' );
		$site = realpath( Directory::createTemp( '/slic-test-site-' . uniqid( '', true ) )
		                           ->createPlugin( 'wp-content/plugins/test-plugin' )
		                           ->getAbsolutePath() );
		mkdir( $site . '/wp-content/themes/test-theme', 0777, true );
		touch( $site . '/wp-config.php' );
		chdir( $site );
		$this->slicExec( 'here' );
		$this->createdStackIds[] = $site;

		foreach ( [ $site, $site . '/wp-content/themes/test-theme' ] as $dir ) {
			chdir( $dir );
			$this->assertStringContainsString(
				"Stack ID: {$site}",
				$this->slicExec( 'stack info', $this->dockerMockEnv() ),
				"A site stack should be found from {$dir} when another stack exists."
			);
		}
	}

	public function test_here_in_sibling_themes_and_plugins_directories_creates_two_stacks(): void {
		$content = realpath( Directory::createTemp()->createPlugin( 'plugins/test-plugin' )->getAbsolutePath() );
		mkdir( $content . '/themes' );
		foreach ( [ 'themes', 'plugins' ] as $dir ) {
			chdir( $content . '/' . $dir );
			$this->slicExec( 'here' );
			$this->createdStackIds[] = $content . '/' . $dir;
		}

		$output = $this->slicExec( 'stack list', $this->dockerMockEnv() );

		foreach ( [ 'themes', 'plugins' ] as $dir ) {
			$this->assertMatchesRegularExpression(
				'#  ' . preg_quote( $content . '/' . $dir, '#' ) . '( <-current)?$#m',
				$output,
				"The {$dir} directory should keep its own stack."
			);
		}
	}

	public function test_here_again_clears_the_target_of_the_stack(): void {
		$pluginsDir = realpath( $this->setUpPluginsDir() );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->slicExec( 'here' );

		$this->assertMatchesRegularExpression(
			'#  ' . preg_quote( $pluginsDir, '#' ) . '( <-current)?\n    Target: none\n#',
			$this->slicExec( 'stack list', $this->dockerMockEnv() ),
			'Running here again should clear the target the worktree commands read.'
		);
	}

	public function test_a_subdirectory_of_a_nested_stack_resolves_to_the_nearest_stack(): void {
		$pluginsDir = realpath( $this->setUpPluginsDir() );
		$inner      = $pluginsDir . '/test-plugin';
		chdir( $inner );
		$this->slicExec( 'here' );
		$this->createdStackIds[] = $inner;
		mkdir( $inner . '/src' );

		chdir( $inner . '/src' );
		$this->assertStringContainsString(
			"Stack ID: {$inner}\n",
			$this->slicExec( 'stack info', $this->dockerMockEnv() ),
			'The working directory should resolve to the nearest enclosing stack.'
		);

		chdir( $pluginsDir );
		$this->assertStringContainsString(
			"Stack ID: {$inner}\n",
			$this->slicExec( 'stack info ' . escapeshellarg( $inner . '/src' ), $this->dockerMockEnv() ),
			'A path should resolve to the nearest enclosing stack.'
		);
	}
}
