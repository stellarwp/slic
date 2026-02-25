<?php

namespace StellarWP\Slic\Test\Cli;

class HostIpTest extends BaseTestCase {

	/**
	 * Returns env vars that mock docker to return a fake IP for host-ip commands.
	 *
	 * @return array<string,string>
	 */
	private function hostIpMockEnv(): array {
		return [
			'SLIC_DOCKER_BIN'         => dirname( __DIR__ ) . '/_support/bin/docker-mock-host-ip',
			'SLIC_DOCKER_COMPOSE_BIN' => dirname( __DIR__ ) . '/_support/bin/docker-mock',
		];
	}

	public function test_host_ip_returns_ip_address(): void {
		$output = $this->slicExec( 'host-ip', $this->hostIpMockEnv() );

		$this->assertMatchesRegularExpression(
			'/\d+\.\d+\.\d+\.\d+/',
			$output,
			'The host-ip command should return an IP address.'
		);
	}

	public function test_host_ip_returns_nonblank(): void {
		$output = $this->slicExec( 'host-ip', $this->hostIpMockEnv() );

		$this->assertNotEmpty(
			trim( $output ),
			'The host-ip command should return non-empty output.'
		);
	}
}
