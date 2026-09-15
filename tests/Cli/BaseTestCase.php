<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\TestCase;
use StellarWP\Slic\Test\Support\Factories\Directory;

abstract class BaseTestCase extends TestCase {
	protected ?string $initialDir = null;

	/**
	 * Stack IDs created during the test, to be cleaned up in tearDown.
	 *
	 * @var string[]
	 */
	private array $createdStackIds = [];

	private static string $dockerMockBin = '';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$dockerMockBin = dirname( __DIR__ ) . '/_support/bin/docker-mock';
	}

	public function setUp(): void {
		parent::setUp();
		$this->initialDir = getcwd();
	}

	public function tearDown(): void {
		// Restore working directory first so stack stop can resolve paths.
		chdir( $this->initialDir );

		// Unregister any stacks created during the test.
		foreach ( $this->createdStackIds as $stackId ) {
			$this->slicExec(
				'stack stop ' . escapeshellarg( $stackId ),
				$this->dockerMockEnv()
			);
		}

		parent::tearDown();
	}

	/**
	 * Execute a slic command and return the output.
	 *
	 * @param string               $command The command to execute, escaped if required.
	 * @param array<string,string> $env     Optional environment variables to set for the command.
	 *
	 * @return string The command output.
	 */
	protected function slicExec( string $command, array $env = [] ): string {
		$env['NO_COLOR'] = '1';

		$envString = '';
		foreach ( $env as $key => $value ) {
			$envString .= $key . '=' . escapeshellarg( $value ) . ' ';
		}

		$commandString = $envString . 'php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/slic.php' ) . ' ' . $command;

		// Redirect stderr to stdout to capture all output.
		return (string) shell_exec( $commandString . ' 2>&1' );
	}

	/**
	 * Returns env vars that mock docker binaries so that no real docker commands are executed.
	 *
	 * @return array<string,string>
	 */
	protected function dockerMockEnv(): array {
		return [
			'SLIC_DOCKER_BIN'         => self::$dockerMockBin,
			'SLIC_DOCKER_COMPOSE_BIN' => self::$dockerMockBin,
		];
	}

	/**
	 * Creates a temporary plugins directory with a plugin, chdirs into it, and runs `slic here`.
	 *
	 * The created stack is automatically unregistered during tearDown.
	 *
	 * @param string $pluginName The name of the plugin directory to create.
	 *
	 * @return string The absolute path to the plugins directory.
	 */
	protected function setUpPluginsDir( string $pluginName = 'test-plugin' ): string {
		$pluginsDir = Directory::createTemp()
		                       ->createPlugin( $pluginName )
		                       ->getAbsolutePath();
		chdir( $pluginsDir );
		$this->slicExec( 'here' );

		// Track the stack ID (resolved path) for cleanup.
		$this->createdStackIds[] = realpath( $pluginsDir );

		return $pluginsDir;
	}
}
