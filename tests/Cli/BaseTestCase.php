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
	private static string $gitMockDir = '';
	private string $slicCacheDir = '';

	/**
	 * Temporary directories created during the test, removed in tearDown.
	 *
	 * @var string[]
	 */
	private array $tempDirs = [];

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		self::$dockerMockBin = dirname( __DIR__ ) . '/_support/bin/docker-mock';
		self::$gitMockDir = dirname( __DIR__ ) . '/_support/bin/git-mock-dir';
	}

	public function setUp(): void {
		parent::setUp();
		$this->initialDir = getcwd();
		$this->slicCacheDir = sys_get_temp_dir() . '/slic-test-cache-' . uniqid( '', true );
		mkdir( $this->slicCacheDir . '/completions', 0777, true );
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

		// Remove the temporary cache directory.
		if ( $this->slicCacheDir !== '' && is_dir( $this->slicCacheDir ) ) {
			$this->removeDirectory( $this->slicCacheDir );
		}

		// Remove any temporary directories created during the test.
		foreach ( $this->tempDirs as $dir ) {
			if ( is_dir( $dir ) ) {
				$this->removeDirectory( $dir );
			}
		}

		parent::tearDown();
	}

	/**
	 * Execute a slic command and return the output.
	 *
	 * When `$stdin` is provided, `proc_open()` is used to pipe the content to the process's stdin
	 * and `SLIC_INTERACTIVE` is set to `'1'`. This path is intended for small payloads only; for
	 * commands that produce more than ~64 KB of combined output, the sequential read of stdout then
	 * stderr could deadlock.
	 *
	 * @param string               $command The command to execute, escaped if required.
	 * @param array<string,string> $env     Optional environment variables to set for the command.
	 * @param string|null          $stdin   Optional stdin content to pipe to the process.
	 *
	 * @return string The command output (stdout and stderr merged).
	 */
	protected function slicExec( string $command, array $env = [], ?string $stdin = null ): string {
		$env['NO_COLOR'] = '1';
		$env['SLIC_INTERACTIVE'] = $stdin !== null ? '1' : '0';
		// Provide a dummy SSH_AUTH_SOCK to prevent setup_id() from exiting in CI.
		if ( ! isset( $env['SSH_AUTH_SOCK'] ) && empty( getenv( 'SSH_AUTH_SOCK' ) ) ) {
			$env['SSH_AUTH_SOCK'] = '/tmp/fake-ssh-agent.sock';
		}
		// Use a temporary cache directory to avoid polluting the real cache.
		if ( ! isset( $env['SLIC_CACHE_DIR'] ) && $this->slicCacheDir !== '' ) {
			$env['SLIC_CACHE_DIR'] = $this->slicCacheDir;
		}

		$envString = '';
		foreach ( $env as $key => $value ) {
			$envString .= $key . '=' . escapeshellarg( $value ) . ' ';
		}

		$commandString = $envString . 'php ' . escapeshellarg( dirname( __DIR__, 2 ) . '/slic.php' ) . ' ' . $command;

		if ( $stdin !== null ) {
			$descriptors = [
				0 => [ 'pipe', 'r' ],
				1 => [ 'pipe', 'w' ],
			];
			// Redirect stderr to stdout so output ordering matches the shell_exec path.
			$process = proc_open( $commandString . ' 2>&1', $descriptors, $pipes );

			if ( ! is_resource( $process ) ) {
				$this->fail( "proc_open() failed for command: $commandString" );
			}

			$written = fwrite( $pipes[0], $stdin );
			if ( $written === false || $written < strlen( $stdin ) ) {
				fclose( $pipes[0] );
				$this->fail( "fwrite() to stdin pipe failed or wrote only $written of " . strlen( $stdin ) . " bytes for command: $commandString" );
			}
			fclose( $pipes[0] );
			$output = stream_get_contents( $pipes[1] );
			fclose( $pipes[1] );
			proc_close( $process );

			return (string) $output;
		}

		// Close stdin to prevent interactive prompts from blocking, and redirect stderr to stdout.
		return (string) shell_exec( $commandString . ' </dev/null 2>&1' );
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
	 * Returns env vars that replace git with a mock that always fails.
	 *
	 * Prevents real git clone calls from hanging on SSH authentication prompts.
	 *
	 * @return array<string,string>
	 */
	protected function gitMockEnv(): array {
		return [
			'PATH' => self::$gitMockDir . ':' . getenv( 'PATH' ),
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

	/**
	 * Creates a temporary directory that is automatically cleaned up in tearDown.
	 *
	 * @return string The absolute path to the created directory.
	 */
	protected function createTempDir(): string {
		$dir = sys_get_temp_dir() . '/slic-test-' . uniqid( '', true );
		mkdir( $dir, 0777, true );
		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function removeDirectory( string $dir ): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $dir );
	}
}
