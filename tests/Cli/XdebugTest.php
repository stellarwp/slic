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
			'/Executing command: .*XDP=9999 /',
			$output,
			'The stack should start with the XDebug port set by the xdebug port command.'
		);
	}
}
