<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

/**
 * Upgrading from slic 2.x, which kept all its state in one global `.env.slic.run`.
 */
class MigrationTest extends BaseTestCase {
	/**
	 * The slic root files the migration reads or writes, set aside so each test starts as a 2.x install.
	 */
	private const ROOT_FILES = [ '.env.slic.stacks', '.env.slic.run', '.env.slic.run.backup', '.build-version' ];

	/**
	 * @var array<string,string|null>
	 */
	private array $savedRootFiles = [];

	private string $pluginsDir = '';

	public function setUp(): void {
		parent::setUp();

		foreach ( self::ROOT_FILES as $file ) {
			$path                          = self::slicRoot() . '/' . $file;
			$this->savedRootFiles[ $file ] = is_file( $path ) ? file_get_contents( $path ) : null;
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}

		$this->pluginsDir = realpath( Directory::createTemp()->createPlugin( 'test-plugin' )->getAbsolutePath() );
		chdir( $this->pluginsDir );

		// A 2.x install: debug on, Composer 1, a target in use and images built by 2.x.
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_PLUGINS_DIR={$this->pluginsDir}\nSLIC_CURRENT_PROJECT=test-plugin\nCLI_VERBOSITY=1\nSLIC_COMPOSER_VERSION=1\n"
		);
		file_put_contents( self::slicRoot() . '/.build-version', '2.5.1' );
	}

	public function tearDown(): void {
		// Stops the stacks the test created while its registry is still in place.
		parent::tearDown();

		$stateFile = self::slicRoot() . '/.env.slic.run.' . substr( md5( $this->pluginsDir ), 0, 8 );
		if ( is_file( $stateFile ) ) {
			unlink( $stateFile );
		}

		foreach ( $this->savedRootFiles as $file => $contents ) {
			$path = self::slicRoot() . '/' . $file;
			if ( null !== $contents ) {
				file_put_contents( $path, $contents );
			} elseif ( is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

	public function test_migration_registers_the_2x_target(): void {
		$output = $this->slicExec( 'worktree list', $this->dockerMockEnv() );

		$this->assertStringContainsString( 'Migrated existing slic configuration', $output );
		$this->assertStringContainsString(
			'Worktree Status for Target: test-plugin',
			$output,
			'The migrated stack should keep the target 2.x was using.'
		);
	}

	public function test_migration_keys_a_2x_site_on_its_here_directory(): void {
		$site = realpath( Directory::createTemp()->createPlugin( 'wp-content/plugins/test-plugin' )->getAbsolutePath() );
		mkdir( $site . '/wp-content/themes' );
		touch( $site . '/wp-config.php' );
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_HERE_DIR={$site}\nSLIC_PLUGINS_DIR={$site}/wp-content/plugins\nSLIC_THEMES_DIR={$site}/wp-content/themes\n"
		);
		$this->createdStackIds[] = $site;
		chdir( $site );

		$this->assertStringContainsString(
			"Stack ID: {$site}\n",
			$this->slicExec( 'stack info', $this->dockerMockEnv() ),
			'A 2.x site should be migrated to a stack keyed on the directory slic here ran in.'
		);
	}

	public function test_debug_off_sticks_after_migration(): void {
		$this->slicExec( 'debug off', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Debug status is: off',
			$this->slicExec( 'debug status', $this->dockerMockEnv() ),
			'The debug setting is global, so the migrated stack should not override it.'
		);
	}

	public function test_composer_version_set_sticks_after_migration(): void {
		$this->slicExec( 'composer set-version 2', $this->dockerMockEnv() );

		$this->slicExec( 'composer get-version', $this->dockerMockLogEnv() );

		$this->assertDockerCalled(
			'/ exec slic composer --version$/',
			'The Composer version is global, so the migrated stack should not override it.'
		);
	}

	public function test_update_prompt_shows_after_migration(): void {
		$this->slicExec( 'using', $this->dockerMockEnv() );

		$this->assertStringContainsString(
			'Your slic containers are not up to date',
			$this->slicExec( 'using', $this->dockerMockEnv() ),
			'Images built by 2.x should still prompt for an update after the migration.'
		);
	}

	public function test_migration_stops_the_2x_compose_project(): void {
		$this->slicExec( 'using', $this->dockerMockLogEnv() );

		// 2.x ran Compose without -p, so its project is named after the slic directory, as Compose normalizes it.
		$this->assertDockerCalled(
			'/^-p slic-test-root-[0-9a-f]+ down --remove-orphans$/',
			'The migration should bring down the 2.x Compose project, keeping its volumes.'
		);
	}

	public function test_a_new_stack_does_not_mark_the_images_as_built(): void {
		$this->slicExec( 'using', $this->dockerMockEnv() );
		$this->setUpPluginsDir();

		$this->assertSame(
			'2.5.1',
			file_get_contents( self::slicRoot() . '/.build-version' ),
			'Creating a stack state file should not record a build that did not happen.'
		);
	}
}
