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
