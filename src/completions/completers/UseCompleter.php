<?php
/**
 * Completer for the 'use' command.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

/**
 * Class UseCompleter
 *
 * Returns the list of valid targets (plugins, themes, site) and their subdirectories
 * for the 'use' command. Results are cached for 5 minutes as the filesystem can change.
 *
 * @package StellarWP\Slic\Completions
 */
class UseCompleter {
	/**
	 * Cache instance.
	 *
	 * @var CompletionCache
	 */
	private CompletionCache $cache;

	/**
	 * Cache TTL in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const CACHE_TTL = 300;

	/**
	 * Cache key for targets.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'targets';

	/**
	 * UseCompleter constructor.
	 *
	 * @param CompletionCache $cache The cache instance.
	 */
	public function __construct( CompletionCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Gets target completions, optionally filtered by a prefix.
	 *
	 * @param string $prefix Optional prefix to filter targets.
	 *
	 * @return array List of matching targets and subdirectories.
	 */
	public function get_completions( string $prefix = '' ): array {
		$targets = $this->get_targets();

		if ( empty( $prefix ) ) {
			return $targets;
		}

		return array_filter( $targets, function( $target ) use ( $prefix ) {
			return strpos( $target, $prefix ) === 0;
		} );
	}

	/**
	 * Gets the list of all available targets and their subdirectories.
	 *
	 * Checks cache first, then scans the filesystem if needed.
	 *
	 * @return array List of target names and subdirectories.
	 */
	private function get_targets(): array {
		// Try cache first
		$cached = $this->cache->get( self::CACHE_KEY );
		if ( null !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Scan filesystem for targets
		$targets = $this->scan_targets();

		// Cache the results
		$this->cache->set( self::CACHE_KEY, $targets, self::CACHE_TTL );

		return $targets;
	}

	/**
	 * Scans the filesystem to find all available targets and subdirectories.
	 *
	 * This includes:
	 * - All valid targets from get_valid_targets() (plugins, themes, site)
	 * - Subdirectories within plugins that contain composer.json or standard test directories
	 *
	 * @return array List of target names and subdirectories in "plugin/subdir" format.
	 */
	private function scan_targets(): array {
		// Load slic functions
		$slic_root = dirname( dirname( dirname( __DIR__ ) ) );
		require_once $slic_root . '/src/slic.php';

		// Get base targets (plugins, themes, site)
		$base_targets = \StellarWP\Slic\get_valid_targets( true );
		if ( ! is_array( $base_targets ) ) {
			$base_targets = [];
		}

		$all_targets = $base_targets;

		// For each plugin target, scan for valid subdirectories
		foreach ( $base_targets as $target ) {
			// Skip 'site' and themes
			if ( $target === 'site' || strpos( $target, 'theme' ) !== false ) {
				continue;
			}

			$subdirs = $this->scan_plugin_subdirectories( $target );
			foreach ( $subdirs as $subdir ) {
				$all_targets[] = $target . '/' . $subdir;
			}
		}

		sort( $all_targets, SORT_NATURAL );

		return $all_targets;
	}

	/**
	 * Scans a plugin directory for valid subdirectories.
	 *
	 * A subdirectory is considered valid if it contains:
	 * - A composer.json file, OR
	 * - Standard test directories (tests/, tests/wpunit/, etc.)
	 *
	 * @param string $plugin_name The plugin name/directory.
	 *
	 * @return array List of valid subdirectory names.
	 */
	private function scan_plugin_subdirectories( string $plugin_name ): array {
		// Get the plugins directory using slic's plugin directory resolution
		$plugin_dir = \StellarWP\Slic\slic_plugins_dir( $plugin_name );

		if ( ! is_dir( $plugin_dir ) ) {
			return [];
		}

		$subdirs = [];
		$entries = @scandir( $plugin_dir );

		if ( false === $entries ) {
			return [];
		}

		foreach ( $entries as $entry ) {
			// Skip hidden files, . and ..
			if ( $entry[0] === '.' ) {
				continue;
			}

			$full_path = $plugin_dir . '/' . $entry;

			// Must be a directory
			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			// Check if it's a valid subdirectory
			if ( $this->is_valid_subdirectory( $full_path ) ) {
				$subdirs[] = $entry;
			}
		}

		sort( $subdirs, SORT_NATURAL );

		return $subdirs;
	}

	/**
	 * Checks if a subdirectory is valid for the 'use' command.
	 *
	 * A subdirectory is valid if it contains:
	 * - A composer.json file, OR
	 * - A tests/ directory with standard test structure
	 *
	 * @param string $dir_path Full path to the subdirectory.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_subdirectory( string $dir_path ): bool {
		// Check for composer.json
		if ( file_exists( $dir_path . '/composer.json' ) ) {
			return true;
		}

		// Check for tests directory
		if ( is_dir( $dir_path . '/tests' ) ) {
			return true;
		}

		// Check for codeception.yml (alternative test setup)
		if ( file_exists( $dir_path . '/codeception.yml' ) ) {
			return true;
		}

		return false;
	}
}
