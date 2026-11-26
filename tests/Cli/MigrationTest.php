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
	private const ROOT_FILES = [ '.env.slic.stacks', '.env.slic.run', '.env.slic.run.backup', '.build-version', '.env.slic.local', '.env' ];

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
			// In CI slic runs exec without a terminal, with -T.
			'/ exec (-T )?slic composer --version$/',
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

	public function test_migration_stops_the_2x_project_named_in_the_local_settings(): void {
		// 2.x loaded .env.slic.local over the shell environment, so its project name won.
		file_put_contents( self::slicRoot() . '/.env.slic.local', "COMPOSE_PROJECT_NAME=legacy_qa\n" );

		$this->slicExec( 'using', [ 'COMPOSE_PROJECT_NAME' => 'from_shell' ] + $this->dockerMockLogEnv() );

		$this->assertDockerCalled(
			'/^-p legacy_qa down --remove-orphans$/',
			'The migration should bring down the Compose project 2.x was configured to use.'
		);
	}

	public function test_migration_stops_the_2x_project_with_the_configured_compose_bin(): void {
		$composeBin = $this->createTempDir() . '/legacy-compose';
		file_put_contents( $composeBin, "#!/bin/bash\nexec " . escapeshellarg( $this->dockerMockEnv()['SLIC_DOCKER_BIN'] ) . ' legacy-compose "$@"' . "\n" );
		chmod( $composeBin, 0755 );
		file_put_contents( self::slicRoot() . '/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$composeBin}\n" );

		$this->slicExec( 'using', $this->dockerMockLogEnv() );

		$this->assertDockerCalled(
			'/^legacy-compose -p slic-test-root-[0-9a-f]+ down --remove-orphans$/',
			'The migration should use the Compose executable 2.x was configured to use.'
		);
	}

	public function test_a_failed_2x_teardown_prints_the_configured_project_and_compose_bin(): void {
		$composeBin = $this->createTempDir() . '/legacy-compose';
		file_put_contents( $composeBin, "#!/bin/bash\nexec " . escapeshellarg( $this->dockerMockEnv()['SLIC_DOCKER_BIN'] ) . ' "$@"' . "\n" );
		chmod( $composeBin, 0755 );
		file_put_contents( self::slicRoot() . '/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$composeBin}\nCOMPOSE_PROJECT_NAME=legacy_qa\n" );

		$output = $this->slicExec( 'using', [ 'SLIC_DOCKER_MOCK_FAIL' => ' down --remove-orphans$' ] + $this->dockerMockEnv() );

		$this->assertStringContainsString(
			"{$composeBin} -p 'legacy_qa' down --remove-orphans",
			$output,
			'The retry command should stop the project 2.x used, with the executable it used.'
		);
	}

	public function test_migration_stops_the_2x_project_the_target_settings_selected(): void {
		// 2.x loaded the selected target's .env.slic.local last, over the root's.
		file_put_contents( self::slicRoot() . '/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$this->composeWrapper( 'from-root' )}\nCOMPOSE_PROJECT_NAME=root_qa\n" );
		file_put_contents( $this->pluginsDir . '/test-plugin/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$this->composeWrapper( 'from-target' )}\nCOMPOSE_PROJECT_NAME=plugin_qa\n" );

		$this->slicExec( 'using', $this->dockerMockLogEnv() );

		$this->assertDockerCalled(
			'/^from-target -p plugin_qa down --remove-orphans$/',
			'The migration should stop the project 2.x ran for the target, with the executable it used.'
		);
	}

	public function test_migration_finds_the_2x_target_in_the_global_plugins_directory(): void {
		// 2.x restored the global plugins directory, overridden by the target, before it located the target.
		$otherDir = $this->createTempDir();
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_BASE_PLUGINS_DIR={$this->pluginsDir}\nSLIC_PLUGINS_DIR={$otherDir}\nSLIC_CURRENT_PROJECT=test-plugin\n"
		);
		file_put_contents( self::slicRoot() . '/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$this->composeWrapper( 'from-root' )}\nCOMPOSE_PROJECT_NAME=root_qa\nDOCKER_CONTEXT=root-endpoint\n" );
		file_put_contents( $this->pluginsDir . '/test-plugin/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$this->composeWrapper( 'from-target' )}\nCOMPOSE_PROJECT_NAME=plugin_qa\nDOCKER_CONTEXT=plugin-endpoint\n" );

		$this->slicExec( 'using', $this->dockerMockLogEnv( [ 'DOCKER_CONTEXT' ] ) );

		$this->assertDockerCalled(
			'/^from-target -p plugin_qa down --remove-orphans \[DOCKER_CONTEXT=plugin-endpoint\]$/',
			'The migration should stop the project 2.x ran for the target it found in the global plugins directory.'
		);
	}

	public function test_migration_stops_the_2x_project_named_in_the_compose_env_file(): void {
		// 2.x ran Compose from the slic root, so Compose read the project name from the root's .env.
		file_put_contents( self::slicRoot() . '/.env', "COMPOSE_PROJECT_NAME=qa_project\n" );

		$this->slicExec( 'using', $this->dockerMockLogEnv( [ 'PWD' ] ) );

		$this->assertDockerCalled(
			'/^-p qa_project down --remove-orphans \[PWD=' . preg_quote( realpath( self::slicRoot() ), '/' ) . '\]$/',
			'The migration should stop the project Compose named from the .env in the slic root, from that root.'
		);
	}

	public function test_a_missing_2x_target_skips_the_teardown_and_prints_how_to_stop_it(): void {
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_PLUGINS_DIR={$this->pluginsDir}\nSLIC_CURRENT_PROJECT=missing-plugin\n"
		);

		$output = $this->slicExec( 'using', $this->dockerMockLogEnv() );

		$this->assertDockerCalled( '/ down /', 'Without the target 2.x used, the migration cannot tell which project to stop.', false );
		$this->assertStringContainsString( 'docker compose ls', $output, 'The user should be told how to find the 2.x containers.' );
		$this->assertStringContainsString( ' -p <name> down --remove-orphans', $output, 'The user should be told how to stop the 2.x containers.' );
		$this->assertStringContainsString( 'Migrated existing slic configuration', $output, 'The migration itself should still succeed.' );
	}

	public function test_migration_stops_the_2x_project_on_its_docker_context(): void {
		file_put_contents( self::slicRoot() . '/.env.slic.local', "DOCKER_CONTEXT=legacy-remote\n" );

		$this->slicExec( 'using', $this->dockerMockLogEnv( [ 'DOCKER_CONTEXT' ] ) );

		$this->assertDockerCalled(
			'/ down --remove-orphans \[DOCKER_CONTEXT=legacy-remote\]$/',
			'The migration should stop the 2.x project on the Docker endpoint 2.x used.'
		);
	}

	public function test_a_failed_2x_teardown_prints_the_docker_context(): void {
		file_put_contents( self::slicRoot() . '/.env.slic.local', "DOCKER_CONTEXT=legacy-remote\n" );

		$output = $this->slicExec( 'using', [ 'SLIC_DOCKER_MOCK_FAIL' => ' down --remove-orphans$' ] + $this->dockerMockEnv() );

		$this->assertMatchesRegularExpression(
			"/^DOCKER_CONTEXT='legacy-remote' .* down --remove-orphans$/m",
			$output,
			'The retry command should target the Docker endpoint 2.x used.'
		);
	}

	public function test_the_2x_target_settings_do_not_outlive_the_teardown(): void {
		// A staged PHP version wins over the target's unless the target's leaks in looking like a command-line one.
		file_put_contents( self::slicRoot() . '/.env.slic.run', "SLIC_PHP_VERSION=8.1\nSLIC_PHP_VERSION_STAGED=1\n", FILE_APPEND );
		file_put_contents( $this->pluginsDir . '/test-plugin/.env.slic.local', "SLIC_PHP_VERSION=8.0\n" );

		$this->assertStringContainsString(
			'PHP version is staged to switch to 8.1',
			$this->slicExec( 'php-version', $this->dockerMockEnv() ),
			'The environment the 2.x teardown ran with should be restored afterwards.'
		);
	}

	public function test_migration_normalizes_the_2x_project_name_as_compose_does(): void {
		// The slic root is shared by the test class: run a copy whose directory name Compose has to normalize.
		$root = $this->createTempDir() . '/_Legacy.Slic';
		exec( 'cp -R ' . escapeshellarg( self::slicRoot() ) . ' ' . escapeshellarg( $root ), $cpOutput, $cpStatus );
		$this->assertSame( 0, $cpStatus, 'Could not copy the slic root.' );

		$command = '';
		foreach ( $this->dockerMockLogEnv() + [ 'SLIC_CACHE_DIR' => $this->createTempDir(), 'NO_COLOR' => '1', 'SLIC_INTERACTIVE' => '0' ] as $key => $value ) {
			$command .= $key . '=' . escapeshellarg( $value ) . ' ';
		}
		shell_exec( $command . 'php ' . escapeshellarg( $root . '/slic.php' ) . ' using 2>&1' );

		// Compose lowercases the name, drops other characters and strips its leading `_` and `-`.
		$this->assertDockerCalled(
			'/^-p legacyslic down --remove-orphans$/',
			'The migration should bring down the 2.x project under the name Compose gave it.'
		);
	}

	public function test_completing_a_command_does_not_migrate(): void {
		$env     = $this->dockerMockLogEnv() + [ 'SLIC_CACHE_DIR' => $this->createTempDir() ];
		$command = '';
		foreach ( $env as $key => $value ) {
			$command .= $key . '=' . escapeshellarg( $value ) . ' ';
		}
		$command .= 'php ' . escapeshellarg( self::slicRoot() . '/src/completions/complete.php' )
		            . ' --words=' . escapeshellarg( "slic\nuse\n" ) . ' --cword=2';

		// Pressing Tab must not take down the 2.x containers the user may be running.
		$this->assertContains( 'test-plugin', explode( "\n", (string) shell_exec( $command . ' 2>&1' ) ), 'The 2.x target should still complete.' );
		$this->assertDockerCalled( '/ down /', 'Completion should not bring down the 2.x Compose project.', false );
		$this->assertFileDoesNotExist( self::slicRoot() . '/.env.slic.stacks', 'Completion should leave the migration to the next command.' );
	}

	public function test_completion_show_prints_only_the_script_before_the_migration(): void {
		$this->assertStringStartsWith(
			'#compdef slic',
			$this->slicExec( 'completion show zsh', $this->dockerMockEnv() ),
			'The migration notice should not precede the completion script.'
		);
	}

	public function test_a_failed_2x_teardown_prints_the_command_to_stop_it(): void {
		$env = [ 'SLIC_DOCKER_MOCK_FAIL' => ' down --remove-orphans$' ] + $this->dockerMockEnv();

		$output = $this->slicExec( 'using', $env );

		$this->assertMatchesRegularExpression(
			'/' . preg_quote( $env['SLIC_DOCKER_COMPOSE_BIN'], '/' ) . " -p 'slic-test-root-[0-9a-f.]+' down --remove-orphans/",
			$output,
			'A failed teardown should tell the user how to stop the 2.x containers by hand.'
		);
		$this->assertStringContainsString( 'Migrated existing slic configuration', $output, 'The migration itself should still succeed.' );
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

	/**
	 * Creates a Compose executable that records its calls through the Docker mock, prefixed with a tag.
	 */
	private function composeWrapper( string $tag ): string {
		$composeBin = $this->createTempDir() . '/' . $tag;
		file_put_contents( $composeBin, "#!/bin/bash\nexec " . escapeshellarg( $this->dockerMockEnv()['SLIC_DOCKER_BIN'] ) . ' ' . $tag . ' "$@"' . "\n" );
		chmod( $composeBin, 0755 );

		return $composeBin;
	}
}
