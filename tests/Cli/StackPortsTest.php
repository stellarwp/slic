<?php

namespace StellarWP\Slic\Test\Cli;

class StackPortsTest extends BaseTestCase {

	public function test_wordpress_is_published_on_a_docker_assigned_port(): void {
		exec( 'docker compose version 2>/dev/null', $output, $status );
		if ( 0 !== $status ) {
			$this->markTestSkipped( 'docker compose is not installed.' );
		}
		$this->setUpPluginsDir();

		// `config` only renders the stack configuration; it starts nothing, so the real docker compose is safe here.
		$config = $this->slicExec( 'dc config', [ 'SSH_AUTH_SOCK' => sys_get_temp_dir() . '/slic-test-ssh-agent.sock' ] );

		$this->assertMatchesRegularExpression(
			'/target: 80\s+published: "0"/',
			$config,
			'Each stack should publish WordPress on its own Docker-assigned port so stacks can run side by side.'
		);
	}

	public function test_ports_set_by_the_user_are_published(): void {
		exec( 'docker compose version 2>/dev/null', $output, $status );
		if ( 0 !== $status ) {
			$this->markTestSkipped( 'docker compose is not installed.' );
		}
		$this->setUpPluginsDir();

		// `config` only renders the stack configuration; it starts nothing, so the real docker compose is safe here.
		$config = $this->slicExec( 'dc config', [
			'SSH_AUTH_SOCK'             => sys_get_temp_dir() . '/slic-test-ssh-agent.sock',
			'WORDPRESS_HTTP_PORT'       => '8123',
			'SLIC_DB_LOCALHOST_PORT'    => '8124',
			'SLIC_REDIS_LOCALHOST_PORT' => '8125',
		] );

		foreach ( [ 80 => '8123', 3306 => '8124', 6379 => '8125' ] as $target => $published ) {
			$this->assertMatchesRegularExpression(
				"/target: {$target}\s+published: \"{$published}\"/",
				$config,
				"A port set by the user should be published for container port {$target}."
			);
		}
	}

	public function test_info_reports_the_wordpress_port_docker_assigned(): void {
		$this->setUpPluginsDir();

		$this->assertMatchesRegularExpression(
			'/- WORDPRESS_HTTP_PORT: 44444$/m',
			$this->slicExec( 'info', $this->runningStackEnv( 'wordpress=44444,db=55555,redis=56666' ) ),
			'Without a pinned port, info should report the port Docker assigned to the stack.'
		);
	}

	public function test_stack_info_reports_the_ports_docker_assigned_last(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'stack info', $this->runningStackEnv( 'wordpress=11111,db=22222,redis=33333' ) );

		$this->assertStringContainsString(
			'WordPress: 44444',
			$this->slicExec( 'stack info', $this->runningStackEnv( 'wordpress=44444,db=55555,redis=56666' ) ),
			'Recreated containers get new host ports, and stack info should report them.'
		);
	}

	public function test_stack_info_reads_ports_when_a_service_is_not_running(): void {
		$this->setUpPluginsDir();

		$this->assertStringNotContainsString(
			'trim(): Passing null',
			$this->slicExec( 'stack info', $this->runningStackEnv( 'wordpress=11111,db=22222' ) ),
			'A service without a container should not produce PHP notices.'
		);
	}

	/**
	 * Returns env vars that mock docker as if the stack containers were running with the given host ports.
	 *
	 * @param string $ports The running services and their host ports, e.g. `wordpress=8080,db=3307`.
	 *
	 * @return array<string,string>
	 */
	private function runningStackEnv( string $ports ): array {
		return array_merge( $this->dockerMockEnv(), [
			'SLIC_DOCKER_BIN'        => dirname( __DIR__ ) . '/_support/bin/docker-ports-mock',
			'SLIC_DOCKER_MOCK_PORTS' => $ports,
		] );
	}
}
