<?php

namespace StellarWP\Slic\Test\Cli;

use StellarWP\Slic\Test\Support\Factories\Directory;

class ContentDirOwnerTest extends BaseTestCase {

	private function startAs( string $uid ): array {
		$this->setUpPluginsDir();
		$dockerLog = tempnam( sys_get_temp_dir(), 'slic-docker-log-' );

		$this->slicExec( 'start', array_merge( $this->dockerMockEnv(), [
			'SLIC_DOCKER_MOCK_LOG' => $dockerLog,
			'SLIC_UID'             => $uid,
			'SLIC_GID'             => $uid,
		] ) );

		return file( $dockerLog, FILE_IGNORE_NEW_LINES );
	}

	public function test_start_as_container_root_gives_the_plugins_directory_back(): void {
		$calls = $this->startAs( '0' );

		$this->assertNotEmpty(
			preg_grep( '#\sexec -T wordpress chown 0:0 /var/www/html/wp-content/plugins /var/www/html/wp-content/themes$#', $calls ),
			"WordPress's first start re-owns the mounted plugins directory to www-data; slic should give it back to the stack's user."
		);
	}

	public function test_start_as_a_host_user_leaves_ownership_alone(): void {
		$calls = $this->startAs( '1000' );

		$this->assertSame(
			[],
			preg_grep( '/ chown /', $calls ),
			'WordPress does not re-own directories when it runs as a non-root user.'
		);
	}

	public function test_start_passes_a_content_dir_with_a_space_as_one_argument(): void {
		$this->setUpPluginsDir();
		file_put_contents( getcwd() . '/test-plugin/.env.slic.local', "SLIC_WP_CONTENT_CONTAINER_DIR=/var/www/html/my content\n" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$calls = $this->runningMockCalls( 'start', '' );

		$this->assertContains(
			'exec|-T|wordpress|chown|0:0|/var/www/html/my content/plugins|/var/www/html/my content/themes',
			$this->tails( $calls, 'exec|' ),
			'Each content-directory path should reach chown as a single argument.'
		);
	}

	public function test_switching_a_running_site_to_an_isolated_plugin_gives_the_plugins_directory_back(): void {
		$siteDir = Directory::createTemp()->getAbsolutePath();
		mkdir( "$siteDir/wp" );
		touch( "$siteDir/wp/wp-load.php" );
		touch( "$siteDir/wp-config.php" );
		mkdir( "$siteDir/wp-content/themes", 0777, true );
		mkdir( "$siteDir/wp-content/plugins/my-plugin", 0777, true );
		file_put_contents( "$siteDir/wp-content/plugins/my-plugin/my-plugin.php", "<?php\n/**\n * Plugin Name: My Plugin\n */\n" );
		chdir( $siteDir );
		$this->slicExec( 'here' );
		$this->createdStackIds[] = realpath( "$siteDir/wp-content/plugins" );
		$this->slicExec( 'use site', $this->dockerMockEnv() );

		// The isolated plugin target mounts `_wordpress` instead of the site, so WordPress is recreated on it.
		$calls = $this->tails( $this->runningMockCalls( 'use my-plugin', 'wordpress' ), '' );
		$up    = array_search( 'up|--wait|--no-deps|wordpress', $this->tails( $calls, 'up|' ), true );

		$this->assertNotFalse( $up, 'The target switch should recreate the running WordPress service.' );
		$this->assertContains(
			'exec|-T|wordpress|chown|0:0|/var/www/html/wp-content/plugins|/var/www/html/wp-content/themes',
			$this->tails( $calls, 'exec|' ),
			'Recreating WordPress as container root re-owns the mounted plugins directory; slic should give it back.'
		);
	}

	/**
	 * Runs a command as container root against a Docker mock that reports the given services running.
	 *
	 * @return string[] The logged Docker calls, arguments joined by `|`.
	 */
	private function runningMockCalls( string $command, string $running ): array {
		$dockerLog = tempnam( sys_get_temp_dir(), 'slic-docker-log-' );
		$mock      = dirname( __DIR__ ) . '/_support/bin/docker-running-mock';

		$this->slicExec( $command, array_merge( $this->dockerMockEnv(), [
			'SLIC_DOCKER_BIN'          => $mock,
			'SLIC_DOCKER_COMPOSE_BIN'  => $mock,
			'SLIC_DOCKER_MOCK_LOG'     => $dockerLog,
			'SLIC_DOCKER_MOCK_RUNNING' => $running,
			'SLIC_UID'                 => '0',
			'SLIC_GID'                 => '0',
		] ) );

		return file( $dockerLog, FILE_IGNORE_NEW_LINES );
	}

	/**
	 * Strips the compose project and file options from each call, keeping calls whose remainder starts with $prefix.
	 *
	 * @return string[]
	 */
	private function tails( array $calls, string $prefix ): array {
		$tails = array_map( static fn( string $call ): string => preg_replace( '/^(?:-[pf]\|[^|]+\|)*/', '', $call ), $calls );

		return array_values( array_filter( $tails, static fn( string $tail ): bool => str_starts_with( $tail, $prefix ) ) );
	}
}
