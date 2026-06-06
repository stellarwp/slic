<?php

namespace StellarWP\Slic\Test\Cli;

class WpCliTest extends BaseTestCase {

	public function test_wp_help_shows_usage(): void {
		$output = $this->slicExec( 'wp help' );

		$this->assertStringContainsString(
			'Runs a wp-cli command',
			$output,
			'The wp help output should describe the command purpose.'
		);

		$this->assertStringContainsString(
			'USAGE:',
			$output,
			'The wp help output should contain a USAGE section.'
		);
	}

	public function test_wp_passes_commands_with_target(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'wp plugin list', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The wp command should not produce an unknown command error.'
		);
	}

	public function test_cli_is_alias_for_wp(): void {
		$wpOutput  = $this->slicExec( 'wp help' );
		$cliOutput = $this->slicExec( 'cli help' );

		$this->assertStringContainsString(
			'Runs a wp-cli command',
			$cliOutput,
			'The cli help output should describe the same command as wp.'
		);

		$this->assertStringContainsString(
			'USAGE:',
			$cliOutput,
			'The cli help output should contain a USAGE section.'
		);

		$this->assertStringContainsString(
			'cli',
			$cliOutput,
			'The cli help output should reference the cli command name.'
		);

		$this->assertEquals(
			$wpOutput,
			$cliOutput,
			'The wp and cli help outputs should be identical.'
		);
	}

	public function test_site_cli_help_shows_usage(): void {
		$output = $this->slicExec( 'site-cli help' );

		$this->assertStringContainsString(
			'Waits for WordPress to be correctly set up to run a wp-cli command',
			$output,
			'The site-cli help output should describe waiting for WordPress.'
		);

		$this->assertStringContainsString(
			'USAGE:',
			$output,
			'The site-cli help output should contain a USAGE section.'
		);
	}

	public function test_site_cli_passes_commands_with_target(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec( 'site-cli plugin list', $this->dockerMockEnv() );

		$this->assertStringNotContainsString(
			'Unknown command',
			$output,
			'The site-cli command should not produce an unknown command error.'
		);
	}

	public function test_site_cli_install_decline_exits_without_running(): void {
		$this->setUpPluginsDir();

		$this->slicExec( 'use test-plugin', $this->dockerMockEnv() );

		$output = $this->slicExec(
			'site-cli _install',
			$this->dockerMockEnv(),
			"no\n"
		);

		$this->assertStringContainsString(
			'Do you really want to run it?',
			$output,
			'The _install subcommand should prompt for confirmation.'
		);
		$this->assertStringNotContainsString(
			'--admin_user=admin',
			$output,
			'Declining should prevent the install command from running.'
		);
	}
}
