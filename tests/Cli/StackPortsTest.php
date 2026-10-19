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
}
