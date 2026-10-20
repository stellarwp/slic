<?php
/**
 * Completer for top-level slic commands.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class CommandCompleter
 *
 * Returns the list of all available slic commands for completion.
 * Results are cached for 24 hours as the command list rarely changes.
 *
 * @package StellarWP\Slic\Completions
 */
class CommandCompleter {
	/**
	 * Cache instance.
	 *
	 * @var CompletionCache
	 */
	private CompletionCache $cache;

	/**
	 * Cache TTL in seconds (24 hours).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 86400;

	/**
	 * Cache key for commands.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'commands';

	/**
	 * CommandCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets command completions, optionally filtered by a prefix.
	 *
	 * @param string $prefix Optional prefix to filter commands.
	 *
	 * @return array List of matching commands.
	 */
	public function get_completions( string $prefix = '' ): array {
		$commands = $this->get_commands();

		if ( empty( $prefix ) ) {
			return $commands;
		}

		return array_filter( $commands, function( $command ) use ( $prefix ) {
			return strpos( $command, $prefix ) === 0;
		} );
	}

	/**
	 * Gets the list of all available commands.
	 *
	 * Checks cache first, then scans the commands directory if needed.
	 *
	 * @return array List of command names.
	 */
	private function get_commands(): array {
		// Try cache first
		$cached = $this->cache->get( self::CACHE_KEY );
		if ( null !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Scan commands directory
		$commands = $this->scan_commands();

		// Cache the results
		$this->cache->set( self::CACHE_KEY, $commands, self::CACHE_TTL );

		return $commands;
	}

	/**
	 * Scans the commands directory to find all available commands.
	 *
	 * @return array List of command names.
	 */
	private function scan_commands(): array {
		// Determine slic root directory
		// This script is in src/completions/completers/, so go up 3 levels
		$slic_root = dirname( dirname( dirname( __DIR__ ) ) );
		$commands_dir = $slic_root . '/src/commands';

		if ( ! is_dir( $commands_dir ) ) {
			return [];
		}

		$files = glob( $commands_dir . '/*.php' );
		if ( false === $files ) {
			return [];
		}

		$commands = [];
		foreach ( $files as $file ) {
			$command = basename( $file, '.php' );
			// Exclude any files that shouldn't be commands (none currently, but good practice)
			if ( ! empty( $command ) && $command[0] !== '.' ) {
				$commands[] = $command;
			}
		}

		// Add command aliases (from slic.php)
		// The 'wp' command is an alias for 'cli', but both are valid for completion
		$aliases = [ 'wp' ];
		$commands = array_merge( $commands, $aliases );

		sort( $commands, SORT_NATURAL );

		return $commands;
	}
}
