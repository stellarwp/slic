<?php

namespace StellarWP\Slic\Test\Cli;

class ParallelCommandPoolTest extends BaseTestCase {

	public function test_composer_install_runs_in_the_slic_service_for_the_plugin_and_its_subdirectory(): void {
		$pluginsDir = $this->setUpPluginsDir( 'test-plugin' );
		mkdir( $pluginsDir . '/test-plugin/common' );
		file_put_contents( $pluginsDir . '/test-plugin/composer.json', '{}' );
		file_put_contents( $pluginsDir . '/test-plugin/common/composer.json', '{}' );
		$this->slicExec( 'use test-plugin' );
		$this->slicExec( 'interactive off' );
		$this->slicExec( 'build-prompt off' );
		$dockerLog = tempnam( sys_get_temp_dir(), 'slic-docker-log-' );

		$this->slicExec( 'composer install', array_merge( $this->dockerMockEnv(), [
			'SLIC_DOCKER_MOCK_LOG' => $dockerLog,
		] ) );

		$calls = file( $dockerLog, FILE_IGNORE_NEW_LINES );
		$execs = [];
		foreach ( $calls as $call ) {
			if ( preg_match( '/ exec .* --workdir (\S+) (\S+) composer install$/', $call, $matches ) ) {
				$execs[ $matches[1] ] = $matches[2];
			}
		}
		ksort( $execs );

		$this->assertSame(
			[
				'/var/www/html/wp-content/plugins/test-plugin'        => 'slic',
				'/var/www/html/wp-content/plugins/test-plugin/common' => 'slic',
			],
			$execs,
			'Composer should run in the slic service for both the plugin and its common directory.'
		);
		$this->assertSame(
			[],
			preg_grep( '/^network rm /', $calls ),
			'No per-target networks are created, so none should be removed.'
		);
	}
}
