<?php

namespace StellarWP\Slic\Test\Cli;

class XdebugTest extends BaseTestCase {

	public function test_xdebug_status_shows_current_state(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug status' );

		$this->assertStringContainsString(
			'XDebug status is:',
			$output,
			'The xdebug status command should display the current XDebug state.'
		);
		$this->assertStringContainsString(
			'Remote host:',
			$output,
			'The xdebug status command should display the remote host.'
		);
		$this->assertStringContainsString(
			'Remote port:',
			$output,
			'The xdebug status command should display the remote port.'
		);
		$this->assertStringContainsString(
			'IDE Key (server name):',
			$output,
			'The xdebug status command should display the IDE key.'
		);
	}

	public function test_xdebug_on_activates(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug on' );

		$this->assertStringContainsString(
			'XDebug status: on',
			$output,
			'The xdebug on command should confirm XDebug is activated.'
		);
	}

	public function test_xdebug_off_deactivates(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug off' );

		$this->assertStringContainsString(
			'XDebug status: off',
			$output,
			'The xdebug off command should confirm XDebug is deactivated.'
		);
	}

	public function test_xdebug_port_sets_port(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug port 9009' );

		$this->assertStringContainsString(
			'Setting XDP=9009',
			$output,
			'The xdebug port command should confirm the port is being set.'
		);
		$this->assertStringContainsString(
			'Tear down the stack with down and restart it to apply the new settings!',
			$output,
			'The xdebug port command should remind the user to restart.'
		);
	}

	public function test_xdebug_host_sets_host(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug host 192.168.1.2' );

		$this->assertStringContainsString(
			'Setting XDH=192.168.1.2',
			$output,
			'The xdebug host command should confirm the host is being set.'
		);
		$this->assertStringContainsString(
			'Tear down the stack with down and restart it to apply the new settings!',
			$output,
			'The xdebug host command should remind the user to restart.'
		);
	}

	public function test_xdebug_key_sets_key(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug key mykey' );

		$this->assertStringContainsString(
			'Setting XDK=mykey',
			$output,
			'The xdebug key command should confirm the IDE key is being set.'
		);
		$this->assertStringContainsString(
			'Tear down the stack with down and restart it to apply the new settings!',
			$output,
			'The xdebug key command should remind the user to restart.'
		);
	}

	public function test_xdebug_no_args_shows_help_or_status(): void {
		$this->setUpPluginsDir();

		$output = $this->slicExec( 'xdebug' );

		$this->assertStringContainsString(
			'XDebug status:',
			$output,
			'Running xdebug with no arguments should show the current XDebug status.'
		);
	}
}
