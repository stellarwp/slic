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
	private const ROOT_FILES = [ '.env.slic.stacks', '.env.slic.run', '.env.slic.run.backup', '.build-version', '.env.slic.local' ];

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

	public function test_a_missing_2x_site_target_does_not_stop_the_migration(): void {
		$site = realpath( Directory::createTemp()->createPlugin( 'wp-content/plugins/test-plugin' )->getAbsolutePath() );
		mkdir( $site . '/wp-content/themes' );
		touch( $site . '/wp-config.php' );
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_HERE_DIR={$site}\nSLIC_PLUGINS_DIR={$site}/wp-content/plugins\nSLIC_THEMES_DIR={$site}/wp-content/themes\nSLIC_CURRENT_PROJECT=gone\n"
		);
		$this->createdStackIds[] = $site;
		chdir( $site );

		// Switching away from the removed target is how a 2.x user recovers, so the migration must not exit first.
		$output = $this->slicExec( 'use test-plugin', $this->dockerMockLogEnv() );

		$this->assertStringNotContainsString( 'Unable to resolve target', $output, 'A removed 2.x target should not stop the command.' );
		$this->assertStringContainsString( 'Using test-plugin', $output, 'The command should run after the migration.' );
		$this->assertStringContainsString( 'Migrated existing slic configuration', $output, 'The migration itself should still succeed.' );
		$this->assertStringContainsString( "ps -a --filter label=com.docker.compose.project\n", $output, 'The user should be told how to find the 2.x containers.' );
		$this->assertDockerCalled( '/ (down|rm) /', 'The migration should not stop or remove any container.', false );
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

	public function test_migration_stops_no_containers_and_says_how_to(): void {
		$env    = $this->dockerMockLogEnv();
		$output = $this->slicExec( 'using', $env );

		// Docker cannot tell which project is the 2.x one, so stopping one automatically could stop someone else's.
		$this->assertDockerCalled( '/ (down|rm|stop|kill) /', 'The migration should not stop or remove any container.', false );
		$this->assertStringContainsString( "  {$env['SLIC_DOCKER_BIN']} ps -a --filter label=com.docker.compose.project\n", $output, 'The user should be told how to list the Compose containers.' );
		$this->assertStringContainsString( "  {$env['SLIC_DOCKER_BIN']} rm -f <container ID>", $output, 'The user should be told how to remove the 2.x containers.' );
		// Command Prompt has no command substitution, no `VAR=value command` prefix and no single quotes.
		$this->assertStringNotContainsString( '$(', $output, 'The commands should not need a POSIX shell.' );
		$this->assertStringNotContainsString( "'", $output, 'The commands should not need a POSIX shell.' );
		$this->assertStringContainsString( 'Migrated existing slic configuration', $output, 'The migration itself should still succeed.' );
	}

	public function test_the_2x_notice_reaches_the_docker_endpoint_2x_used(): void {
		// 2.x restored the global plugins directory, overridden by the target, before it loaded the target's settings.
		$otherDir = $this->createTempDir();
		file_put_contents(
			self::slicRoot() . '/.env.slic.run',
			"SLIC_BASE_PLUGINS_DIR={$this->pluginsDir}\nSLIC_PLUGINS_DIR={$otherDir}\nSLIC_CURRENT_PROJECT=test-plugin\n"
		);
		file_put_contents( self::slicRoot() . '/.env.slic.local', "DOCKER_CONTEXT=root-endpoint\n" );
		file_put_contents( $this->pluginsDir . '/test-plugin/.env.slic.local', "DOCKER_CONTEXT=plugin-endpoint\nDOCKER_CONFIG=/plugin/docker-config\n" );

		$env    = $this->dockerMockLogEnv();
		$output = $this->slicExec( 'using', $env );

		// The endpoint goes in docker's own flags, which every shell passes as they are.
		$docker = $env['SLIC_DOCKER_BIN'] . ' --context ' . escapeshellarg( 'plugin-endpoint' ) . ' --config ' . escapeshellarg( '/plugin/docker-config' );
		$this->assertStringContainsString( "{$docker} ps -a ", $output, 'The listing should reach the Docker endpoint 2.x used for the target.' );
		$this->assertStringContainsString( "{$docker} rm -f ", $output, 'The removal should reach the Docker endpoint 2.x used for the target.' );
	}

	public function test_the_2x_notice_uses_the_docker_command_of_the_compose_bin(): void {
		// A Compose executable such as `docker --context remote compose` names its endpoint itself.
		$env = $this->dockerMockLogEnv();
		file_put_contents( self::slicRoot() . '/.env.slic.local', "SLIC_DOCKER_COMPOSE_BIN={$env['SLIC_DOCKER_BIN']} --context remote-qa compose --ansi never\nDOCKER_CONTEXT=other\n" );
		unset( $env['SLIC_DOCKER_COMPOSE_BIN'] );

		$output = $this->slicExec( 'using', $env );

		// Docker refuses a second endpoint flag, and the executable's own one is the one 2.x reached.
		$this->assertStringContainsString( "  {$env['SLIC_DOCKER_BIN']} --context remote-qa ps -a ", $output, 'The listing should reach the endpoint the Compose executable names.' );
		$this->assertStringContainsString( "  {$env['SLIC_DOCKER_BIN']} --context remote-qa rm -f ", $output, 'The removal should reach the endpoint the Compose executable names.' );
	}

	public function test_a_missing_2x_target_still_says_how_to_stop_the_containers(): void {
		file_put_contents( self::slicRoot() . '/.env.slic.run', "SLIC_PLUGINS_DIR={$this->pluginsDir}\nSLIC_CURRENT_PROJECT=missing-plugin\n" );

		$output = $this->slicExec( 'using', $this->dockerMockLogEnv() );

		$this->assertDockerCalled( '/ (down|rm) /', 'The migration should not stop or remove any container.', false );
		$this->assertStringContainsString( "ps -a --filter label=com.docker.compose.project\n", $output, 'The user should be told how to find the 2.x containers.' );
		$this->assertStringContainsString( 'Migrated existing slic configuration', $output, 'The migration itself should still succeed.' );
	}

	public function test_the_2x_target_settings_do_not_outlive_the_migration(): void {
		// A staged PHP version wins over the target's unless the target's leaks in looking like a command-line one.
		file_put_contents( self::slicRoot() . '/.env.slic.run', "SLIC_PHP_VERSION=8.1\nSLIC_PHP_VERSION_STAGED=1\n", FILE_APPEND );
		file_put_contents( $this->pluginsDir . '/test-plugin/.env.slic.local', "SLIC_PHP_VERSION=8.0\n" );

		$this->assertStringContainsString(
			'PHP version is staged to switch to 8.1',
			$this->slicExec( 'php-version', $this->dockerMockEnv() ),
			'The 2.x environment the migration reads should be restored afterwards.'
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
