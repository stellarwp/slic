<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\Attributes\DataProvider;

class XdebugTest extends BaseTestCase {

	public static function settingProvider(): array {
		return [
			'port' => [ 'port', '9999', 'Remote port: 9999' ],
			'key'  => [ 'key', 'my-ide-key', 'IDE Key (server name): my-ide-key' ],
		];
	}

	#[DataProvider( 'settingProvider' )]
	public function test_xdebug_setting_overrides_the_stack_default( string $setting, string $value, string $expected ): void {
		$this->setUpPluginsDir();

		$this->slicExec( "xdebug {$setting} {$value}" );

		$this->assertStringContainsString(
			$expected,
			$this->slicExec( 'xdebug status' ),
			"The xdebug {$setting} command should override the stack default."
		);
	}

	public function test_xdebug_port_reaches_the_docker_compose_commands(): void {
		$this->setUpPluginsDir();
		$this->slicExec( 'xdebug port 9999' );

		// Debug output prints the docker compose commands with their environment; the setting is global, so restore it.
		$this->slicExec( 'debug on' );
		try {
			$output = $this->slicExec( 'start', $this->dockerMockEnv() );
		} finally {
			$this->slicExec( 'debug off' );
		}

		$this->assertMatchesRegularExpression(
			"/Executing command: .*XDP='9999' /",
			$output,
			'The stack should start with the XDebug port set by the xdebug port command.'
		);
	}

	public function test_xdebug_key_with_a_space_reaches_the_docker_compose_commands(): void {
		$this->setUpPluginsDir();
		$this->slicExec( "xdebug key 'my key'" );

		$this->slicExec( 'start', $this->dockerMockLogEnv( [ 'XDK' ] ) );

		$this->assertDockerCalled(
			'/ up .*\[XDK=my key\]$/',
			'An IDE key with a space should reach docker compose as one value.'
		);
	}

	public function test_a_user_xdebug_port_wins_over_the_stack_default_and_the_xdebug_port_command_over_both(): void {
		$this->setUpPluginsDir();

		// The slic root's local settings are shared by every test, so restore them.
		$localFile = self::slicRoot() . '/.env.slic.local';
		$previous  = is_file( $localFile ) ? file_get_contents( $localFile ) : null;
		file_put_contents( $localFile, "XDP=9555\n" );
		try {
			$this->slicExec( 'start', $this->dockerMockLogEnv( [ 'XDP' ] ) );
			$this->slicExec( 'xdebug port 9009' );
			$status = $this->slicExec( 'xdebug status' );
		} finally {
			null === $previous ? unlink( $localFile ) : file_put_contents( $localFile, $previous );
		}

		$this->assertDockerCalled( '/ up .*\[XDP=9555\]$/', 'An XDP set in .env.slic.local should win over the stack default.' );
		$this->assertStringContainsString(
			'Remote port: 9009',
			$status,
			'The xdebug port command should win over an XDP set in .env.slic.local.'
		);
	}

	public function test_a_target_xdebug_port_reaches_docker_compose_and_the_status(): void {
		$pluginsDir = $this->setUpPluginsDir();
		file_put_contents( $pluginsDir . '/test-plugin/.env.slic.local', "XDP=9666\n" );
		$this->slicExec( 'use test-plugin' );

		$this->slicExec( 'start', $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled( '/ up .*\[XDP=9666\]$/', 'An XDP set in the target .env.slic.local should reach docker compose.' );
		$this->assertStringContainsString(
			'Remote port: 9666',
			$this->slicExec( 'xdebug status' ),
			'The xdebug status should report the XDP set in the target .env.slic.local.'
		);
	}

	public function test_a_target_xdebug_port_wins_when_the_target_also_sets_the_plugins_dir(): void {
		$pluginsDir = $this->setUpPluginsDir();
		$otherDir   = $this->createTempDir();
		mkdir( $otherDir . '/test-plugin' );
		touch( $otherDir . '/test-plugin/plugin.php' );
		// Once loaded, this plugins dir would resolve the target to the other copy, which has no XDP.
		file_put_contents( $pluginsDir . '/test-plugin/.env.slic.local', "XDP=9666\nSLIC_PLUGINS_DIR={$otherDir}\n" );
		$this->slicExec( 'use test-plugin' );

		$this->slicExec( 'start', $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled( '/ up .*\[XDP=9666\]$/', 'The XDP of the loaded target .env.slic.local should reach docker compose.' );
		$this->assertStringContainsString( 'Remote port: 9666', $this->slicExec( 'xdebug status' ) );
	}

	public function test_a_shell_xdebug_port_wins_when_php_does_not_populate_server_env_vars(): void {
		$this->setUpPluginsDir();
		// Without S in variables_order, $_SERVER has no environment variables; the leading : keeps the default ini dir.
		$iniDir = $this->createTempDir();
		file_put_contents( $iniDir . '/variables-order.ini', "variables_order = \"GPC\"\n" );

		$this->slicExec( 'start', [ 'XDP' => '9444', 'PHP_INI_SCAN_DIR' => ':' . $iniDir ] + $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled( '/ up .*\[XDP=9444\]$/', 'An XDP set in the shell should win over the stack default.' );
	}

	public function test_the_xdebug_port_command_wins_over_a_target_port_when_a_target_switch_recreates_services(): void {
		$pluginsDir = $this->setUpPluginsDir();
		mkdir( $pluginsDir . '/other-plugin' );
		touch( $pluginsDir . '/other-plugin/plugin.php' );
		// A different content directory changes the bind mounts, so the switch recreates the running service.
		file_put_contents( $pluginsDir . '/other-plugin/.env.slic.local', "XDP=9666\nSLIC_WP_CONTENT_CONTAINER_DIR=/var/www/html/other\n" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$this->slicExec( 'xdebug port 9777' );

		$this->slicExec( 'use other-plugin', [ 'SLIC_DOCKER_MOCK_RUNNING' => 'wordpress' ] + $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled(
			'/ up --wait --no-deps wordpress \[XDP=9777\]$/',
			'The recreated service should get the port set by the xdebug port command, not the target one.'
		);
		$this->assertStringContainsString( 'Remote port: 9777', $this->slicExec( 'xdebug status' ) );
	}

	public function test_a_shell_xdebug_port_reaches_docker_compose(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'start', [ 'XDP' => '9444' ] + $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled( '/ up .*\[XDP=9444\]$/', 'An XDP set in the shell should win over the stack default.' );
	}

	public function test_stopping_another_stack_uses_that_stack_target_xdebug_port(): void {
		$other = $this->setUpPluginsDir();
		file_put_contents( $other . '/test-plugin/.env.slic.local', "XDP=9666\n" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );
		$current = $this->setUpPluginsDir();
		file_put_contents( $current . '/test-plugin/.env.slic.local', "XDP=9555\n" );
		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$this->slicExec( 'stack stop ' . escapeshellarg( realpath( $other ) ), $this->dockerMockLogEnv( [ 'XDP' ] ) );

		$this->assertDockerCalled(
			'/^-p slic_' . substr( md5( realpath( $other ) ), 0, 8 ) . ' .* down .*\[XDP=9666\]$/',
			"The other stack should be stopped with its own target's port, not the current stack's."
		);
	}

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
