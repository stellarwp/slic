<?php

namespace StellarWP\Slic\Test\Cli;

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
}
