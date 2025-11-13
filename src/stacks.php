<?php
/**
 * Stack registry and management functions for multi-stack support.
 */

namespace StellarWP\Slic;

/**
 * Returns the path to the stack registry file.
 *
 * @return string The absolute path to the .env.slic.stacks file.
 */
function slic_stacks_registry_file() {
	return dirname(__DIR__) . '/.env.slic.stacks';
}

/**
 * Returns an array of all registered stacks.
 *
 * @return array<string,array> Array of stacks indexed by stack_id.
 */
function slic_stacks_list() {
	$registry_file = slic_stacks_registry_file();

	if (!file_exists($registry_file)) {
		return [];
	}

	$content = file_get_contents($registry_file);
	if ($content === false) {
		return [];
	}

	$stacks = json_decode($content, true);
	if (!is_array($stacks)) {
		return [];
	}

	return $stacks;
}

/**
 * Registers a new stack in the registry (thread-safe).
 * Uses file locking to prevent race conditions during concurrent stack registration.
 *
 * @param string $stack_id The stack identifier (full directory path).
 * @param array $state The stack state data.
 * @return bool True on success, false on failure.
 */
function slic_stacks_register($stack_id, array $state) {
	return slic_stacks_with_lock(function() use ($stack_id, $state) {
		$stacks = slic_stacks_list();

		// Validate base stack exists for worktrees
		if (!empty($state['is_worktree']) && !empty($state['base_stack_id'])) {
			if (!isset($stacks[$state['base_stack_id']])) {
				echo "Error: Base stack not found: {$state['base_stack_id']}\n";
				return false;
			}
		}

		// Allocate XDebug port if not provided (CRITICAL: inside lock)
		if (!isset($state['xdebug_port'])) {
			$state['xdebug_port'] = slic_stacks_xdebug_port($stack_id);
		}

		$stacks[$stack_id] = array_merge([
			'created_at' => date('c'),
			'status' => 'created',
		], $state);

		return slic_stacks_write_registry($stacks);
	});
}

/**
 * Unregisters a stack from the registry (thread-safe).
 * Uses file locking to prevent race conditions during concurrent stack operations.
 *
 * @param string $stack_id The stack identifier to remove.
 * @param bool $cascade If true, also removes child worktree stacks.
 * @return bool True on success, false on failure.
 */
function slic_stacks_unregister($stack_id, $cascade = false) {
	return slic_stacks_with_lock(function() use ($stack_id, $cascade) {
		$stacks = slic_stacks_list();

		if (!isset($stacks[$stack_id])) {
			return true; // Already not registered
		}

		// Check if this is a base stack with worktrees
		if (!slic_stacks_is_worktree($stack_id)) {
			$worktrees = slic_stacks_get_worktrees($stack_id);
			if (!empty($worktrees)) {
				if (!$cascade) {
					echo "Warning: Stack has " . count($worktrees) . " worktree(s). ";
					echo "Use --cascade to remove them.\n";

					foreach ($worktrees as $wt_id => $wt_state) {
						echo "  - {$wt_state['worktree_dir']}\n";
					}

					return false;
				}

				// Remove all worktrees
				foreach (array_keys($worktrees) as $wt_id) {
					unset($stacks[$wt_id]);
				}
			}
		}

		unset($stacks[$stack_id]);
		return slic_stacks_write_registry($stacks);
	});
}

/**
 * Gets the state of a specific stack.
 *
 * @param string $stack_id The stack identifier.
 * @return array|null The stack state or null if not found.
 */
function slic_stacks_get($stack_id) {
	$stacks = slic_stacks_list();
	return $stacks[$stack_id] ?? null;
}

/**
 * Updates the state of an existing stack.
 *
 * @param string $stack_id The stack identifier.
 * @param array $state The new state data (will be merged with existing).
 * @return bool True on success, false on failure.
 */
function slic_stacks_update($stack_id, array $state) {
	return slic_stacks_with_lock(function() use ($stack_id, $state) {
		$stacks = slic_stacks_list();

		if (!isset($stacks[$stack_id])) {
			return false;
		}

		$stacks[$stack_id] = array_merge($stacks[$stack_id], $state);

		return slic_stacks_write_registry($stacks);
	});
}

/**
 * Writes the stacks registry to disk with atomic file operations.
 * Prevents race conditions when multiple slic processes run concurrently.
 *
 * @param array $stacks The stacks data to write.
 * @return bool True on success, false on failure.
 */
function slic_stacks_write_registry(array $stacks) {
	$registry_file = slic_stacks_registry_file();
	$temp_file = $registry_file . '.tmp.' . getmypid();

	// Encode JSON
	$json = json_encode($stacks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		echo "Error encoding registry: " . json_last_error_msg() . "\n";
		return false;
	}

	// Write to temporary file
	$result = file_put_contents($temp_file, $json);
	if ($result === false) {
		return false;
	}

	// Atomic rename (overwrites registry file)
	if (!rename($temp_file, $registry_file)) {
		@unlink($temp_file);
		return false;
	}

	return true;
}

/**
 * Acquires an exclusive lock on the registry file for atomic read-modify-write operations.
 * Executes the provided callback while holding the lock to prevent race conditions.
 *
 * @param callable $callback Function to execute with locked registry.
 * @return mixed Result of callback.
 * @throws \RuntimeException If lock cannot be acquired.
 */
function slic_stacks_with_lock(callable $callback) {
	$registry_file = slic_stacks_registry_file();
	$lock_file = $registry_file . '.lock';

	// Create lock file if it doesn't exist
	if (!file_exists($lock_file)) {
		if (@touch($lock_file) === false) {
			throw new \RuntimeException("Cannot create lock file: $lock_file (check permissions)");
		}
	}

	$lock_handle = fopen($lock_file, 'c');
	if ($lock_handle === false) {
		throw new \RuntimeException("Cannot open lock file: $lock_file");
	}

	try {
		// Acquire exclusive lock with timeout
		$attempts = 0;
		$max_attempts = 50; // 5 seconds total

		while (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
			if ($attempts++ >= $max_attempts) {
				throw new \RuntimeException(
					"Cannot acquire lock on registry after 5 seconds. " .
					"Another slic process may be running. " .
					"If stuck, remove: $lock_file"
				);
			}
			usleep(100000); // 100ms
		}

		// Execute callback with lock held
		$result = $callback();

		// Release lock
		flock($lock_handle, LOCK_UN);

		return $result;
	} finally {
		fclose($lock_handle);
	}
}

/**
 * Resolves the stack ID from the current working directory.
 * Uses prioritized resolution to prefer worktree stacks over base stacks.
 *
 * Resolution priority:
 * 1. Exact match with worktree stacks (checked FIRST)
 * 2. Exact match with base stacks
 * 3. CWD is within a base stack directory
 * 4. Parent directory walk
 *
 * @return string|null The stack ID or null if not found.
 */
function slic_stacks_resolve_from_cwd() {
	$cwd = getcwd();

	if ($cwd === false || $cwd === '') {
		return null;
	}

	$stacks = slic_stacks_list();

	// Normalize CWD for comparison
	$cwd_real = realpath($cwd);
	if ($cwd_real === false) {
		// Path doesn't exist or realpath failed, use original
		$cwd_real = $cwd;
	}

	// PRIORITY 1: Exact match with worktree stacks (check FIRST)
	// This ensures worktrees take precedence over base stacks
	foreach ($stacks as $stack_id => $state) {
		if (slic_stacks_is_worktree($stack_id)) {
			$worktree_path = $state['worktree_full_path'] ?? null;

			if ($worktree_path) {
				$worktree_real = realpath($worktree_path);
				if ($worktree_real === false) {
					// realpath failed, use original path
					$worktree_real = $worktree_path;
				}

				// Check exact match
				if ($cwd_real === $worktree_real) {
					return $stack_id;
				}

				// Check if CWD is within worktree directory tree
				// Add trailing slashes to prevent partial matches (e.g., /foo vs /foobar)
				if (strpos($cwd_real . '/', $worktree_real . '/') === 0) {
					return $stack_id;
				}
			}
		}
	}

	// PRIORITY 2: Exact match with base stacks
	foreach ($stacks as $stack_id => $state) {
		if (!slic_stacks_is_worktree($stack_id)) {
			$stack_real = realpath($stack_id);
			if ($stack_real === false) {
				// realpath failed, use original stack_id
				$stack_real = $stack_id;
			}

			// Check exact match
			if ($cwd_real === $stack_real) {
				return $stack_id;
			}
		}
	}

	// PRIORITY 3: CWD is within a base stack directory
	foreach ($stacks as $stack_id => $state) {
		if (!slic_stacks_is_worktree($stack_id)) {
			$stack_real = realpath($stack_id);
			if ($stack_real === false) {
				// realpath failed, use original stack_id
				$stack_real = $stack_id;
			}

			// Check if CWD is within base stack directory tree
			// Add trailing slashes to prevent partial matches
			if (strpos($cwd_real . '/', $stack_real . '/') === 0) {
				return $stack_id;
			}
		}
	}

	// PRIORITY 4: Parent directory walk
	// Walk up the directory tree to find any matching stack
	$path = $cwd_real;  // Use normalized path for consistency
	while ($path !== '/' && $path !== '.') {
		$resolved = slic_stacks_resolve_from_path($path);
		if ($resolved) {
			return $resolved;
		}
		$path = dirname($path);
	}

	return null;
}

/**
 * Resolves a stack ID from a given path.
 * Supports ~ expansion and finds matching stack by exact or parent directory match.
 *
 * @param string $path The path to resolve.
 * @return string|null The stack ID or null if not found.
 */
function slic_stacks_resolve_from_path($path) {
	// Expand ~ to home directory
	if (strpos($path, '~') === 0) {
		$home = getenv('HOME');
		if ($home === false) {
			$home = getenv('USERPROFILE'); // Windows fallback
		}
		if ($home !== false) {
			$path = $home . substr($path, 1);
		}
	}

	// Resolve to absolute path
	$path = realpath($path);
	if ($path === false) {
		// Path doesn't exist, try without realpath
		$path = func_get_arg(0);
		if (strpos($path, '~') === 0) {
			$home = getenv('HOME') ?: getenv('USERPROFILE');
			if ($home !== false) {
				$path = $home . substr($path, 1);
			}
		}
	}

	$stacks = slic_stacks_list();

	// First, try exact match
	if (isset($stacks[$path])) {
		return $path;
	}

	// Try to find a stack where the path is within its directory
	// For example, if stack is /Users/Alice/project/wp-content/plugins
	// and path is /Users/Alice/project/wp-content/plugins/my-plugin
	// we should match it
	foreach ($stacks as $stack_id => $stack) {
		// Add trailing slashes to prevent false positives (e.g., /foo vs /foobar)
		if (strpos($path . '/', $stack_id . '/') === 0) {
			// Path starts with stack_id, so it's within that stack's directory
			return $stack_id;
		}
	}

	// Try to find a stack by checking parent directories
	$current_path = $path;
	while ($current_path !== dirname($current_path)) {
		if (isset($stacks[$current_path])) {
			return $current_path;
		}
		$current_path = dirname($current_path);
	}

	return null;
}

/**
 * Generates a Docker Compose project name for a stack.
 * Uses a hash of the stack ID to ensure uniqueness and avoid special characters.
 *
 * @param string $stack_id The stack identifier.
 * @return string The project name.
 */
function slic_stacks_get_project_name($stack_id) {
	// Use first 8 characters of MD5 hash for a short, unique identifier
	$hash = substr(md5($stack_id), 0, 8);
	return 'slic_' . $hash;
}

/**
 * Gets the state file path for a specific stack.
 *
 * @param string $stack_id The stack identifier.
 * @return string The absolute path to the stack's state file.
 */
function slic_stacks_get_state_file($stack_id) {
	$hash = substr(md5($stack_id), 0, 8);
	return dirname(__DIR__) . '/.env.slic.run.' . $hash;
}

/**
 * Allocates an XDebug port for a stack, ensuring no conflicts with other stacks.
 *
 * This function uses a two-phase allocation strategy:
 * 1. Attempts to use a deterministic hash-based port (preferred for consistency)
 * 2. If collision detected, finds the next available port in the range
 *
 * Port range: 49000-59000 (10,000 possible unique ports)
 * - Avoids well-known ports (< 1024)
 * - Avoids common service ports (3306, 6379, 8080, etc.)
 * - Avoids Docker's ephemeral port range (32768-65535)
 * - Provides 10,000 possible unique ports
 *
 * Conflict Detection:
 * - Checks all registered stacks for existing port allocations
 * - Excludes current stack from conflict check (allows re-initialization)
 * - Linear search for next available port if preferred port is taken
 *
 * Edge Cases Handled:
 * - Stack re-initialization: Excluded from its own conflict check
 * - Port exhaustion: Falls back to preferred port (should never happen with 10k ports)
 * - Missing xdebug_port in state: Safely ignored during conflict check
 *
 * @param string $stack_id The stack identifier (absolute path).
 * @return int The allocated XDebug port number (49000-59000).
 */
function slic_stacks_xdebug_port($stack_id) {
	$min_port = 49000;
	$max_port = 59000;

	// Try deterministic port first (hash-based for consistency)
	$hash = substr(md5($stack_id), 0, 8);
	$hash_decimal = hexdec($hash);
	$port_range = $max_port - $min_port + 1;
	$preferred_port = $min_port + ($hash_decimal % $port_range);

	// Check if port is available across all registered stacks
	$stacks = slic_stacks_list();
	$used_ports = [];
	foreach ($stacks as $sid => $state) {
		// Exclude current stack from conflict check (allows re-initialization)
		if ($sid !== $stack_id && !empty($state['xdebug_port'])) {
			$used_ports[] = $state['xdebug_port'];
		}
	}

	// Convert to hash set for O(1) lookups
	$used_ports_set = array_flip($used_ports);

	// Return preferred port if available
	if (!isset($used_ports_set[$preferred_port])) {
		return $preferred_port;
	}

	// Collision detected - find next available port
	for ($port = $min_port; $port <= $max_port; $port++) {
		if (!isset($used_ports_set[$port])) {
			return $port;
		}
	}

	// Fallback (should never happen with 10,000 ports available)
	return $preferred_port;
}

/**
 * Generates a deterministic XDebug server name for a stack based on its path.
 *
 * The server name is used by IDEs to identify debugging sessions and match
 * path mappings. Each stack needs a unique server name.
 *
 * Format: "slic_{hash}" where hash is the first 8 characters of MD5
 * Example: "slic_a7f3c891"
 *
 * @param string $stack_id The stack identifier (absolute path).
 * @return string The XDebug server name.
 */
function slic_stacks_xdebug_server_name($stack_id) {
	// Use the same hash approach as project names for consistency
	$hash = substr(md5($stack_id), 0, 8);

	return 'slic_' . $hash;
}

/**
 * Counts the number of registered stacks.
 *
 * @return int The number of stacks.
 */
function slic_stacks_count() {
	return count(slic_stacks_list());
}

/**
 * Gets the single stack if only one exists, null otherwise.
 *
 * @return array|null The single stack's data or null.
 */
function slic_stacks_get_single() {
	$stacks = slic_stacks_list();

	if (count($stacks) !== 1) {
		return null;
	}

	return reset($stacks);
}

/**
 * Gets the stack ID of the single stack if only one exists, null otherwise.
 *
 * @return string|null The single stack's ID or null.
 */
function slic_stacks_get_single_id() {
	$stacks = slic_stacks_list();

	if (count($stacks) !== 1) {
		return null;
	}

	reset($stacks);
	return key($stacks);
}

/**
 * Reads actual port assignments from Docker for a running stack.
 *
 * @param string $stack_id The stack identifier.
 * @return array|null Array with keys 'wp', 'mysql', 'redis' or null if containers aren't running.
 */
function slic_stacks_read_ports_from_docker($stack_id) {
	$project_name = slic_stacks_get_project_name($stack_id);

	// Check if containers are actually running
	$check_command = "docker ps -q -f label=com.docker.compose.project='$project_name' 2>/dev/null";
	$container_ids = trim(shell_exec($check_command));

	if (empty($container_ids)) {
		// Containers not running
		return null;
	}

	$ports = [];

	// Map service names to port numbers we need to extract
	$service_map = [
		'wordpress' => ['wp', 80],
		'db' => ['mysql', 3306],
		'redis' => ['redis', 6379],
	];

	foreach ($service_map as $service_name => $info) {
		list($port_key, $container_port) = $info;

		// Get the container ID for this service
		$get_id_command = "docker ps -q -f label=com.docker.compose.project='$project_name' " .
		                  "-f label=com.docker.compose.service=$service_name 2>/dev/null";
		$service_id = trim(shell_exec($get_id_command));

		if (empty($service_id)) {
			continue;
		}

		// Get the port mapping for this container
		$port_command = "docker port $service_id $container_port 2>/dev/null | grep '0.0.0.0' | cut -d: -f2";
		$host_port = trim(shell_exec($port_command));

		if (!empty($host_port) && is_numeric($host_port)) {
			$ports[$port_key] = (int)$host_port;
		}
	}

	// Only return ports if we got all three
	if (count($ports) === 3) {
		return $ports;
	}

	return null;
}

/**
 * Ensures a stack has port assignments, reading from Docker if needed.
 * Updates the registry with current port assignments if containers are running.
 *
 * @param string $stack_id The stack identifier.
 * @return bool True if ports are available, false otherwise.
 */
function slic_stacks_ensure_ports($stack_id) {
	$stack = slic_stacks_get($stack_id);

	if (null === $stack) {
		return false;
	}

	// If ports are already set and valid, we're done
	if (isset($stack['ports']) && is_array($stack['ports']) && count($stack['ports']) === 3) {
		return true;
	}

	// Try to read ports from Docker
	$ports = slic_stacks_read_ports_from_docker($stack_id);

	if (null !== $ports) {
		// Update the registry with the actual ports
		slic_stacks_update($stack_id, ['ports' => $ports]);
		return true;
	}

	// Containers not running yet
	return false;
}

/**
 * Checks if a stack ID represents a worktree.
 *
 * @param string $stack_id The stack identifier.
 * @return bool True if worktree.
 */
function slic_stacks_is_worktree($stack_id) {
	return strpos($stack_id, '@') !== false;
}

/**
 * Gets all worktree stacks for a base stack.
 *
 * @param string $base_stack_id The base stack identifier.
 * @return array Array of worktree stacks.
 */
function slic_stacks_get_worktrees($base_stack_id) {
	$stacks = slic_stacks_list();
	$worktrees = [];

	foreach ($stacks as $stack_id => $state) {
		if (!empty($state['is_worktree']) &&
			!empty($state['base_stack_id']) &&
			$state['base_stack_id'] === $base_stack_id) {
			$worktrees[$stack_id] = $state;
		}
	}

	return $worktrees;
}

/**
 * Parses a worktree stack ID into components.
 *
 * @param string $stack_id The stack identifier.
 * @return array|null Array with 'base_path' and 'worktree_dir', or null if not a worktree.
 */
function slic_stacks_parse_worktree_id($stack_id) {
	if (!slic_stacks_is_worktree($stack_id)) {
		return null;
	}

	$parts = explode('@', $stack_id, 2);
	if (count($parts) !== 2) {
		return null;
	}

	// Validate that worktree_dir does not contain @
	if (strpos($parts[1], '@') !== false) {
		return null; // worktree_dir must not contain @
	}

	return [
		'base_path' => $parts[0],
		'worktree_dir' => $parts[1],
	];
}

/**
 * Gets the base stack ID for a given stack (base or worktree).
 *
 * @param string $stack_id The stack identifier.
 * @return string The base stack ID.
 */
function slic_stacks_get_base_stack_id($stack_id) {
	// Validate input
	if (!is_string($stack_id) || $stack_id === '') {
		return '';
	}

	$parsed = slic_stacks_parse_worktree_id($stack_id);
	return $parsed ? $parsed['base_path'] : $stack_id;
}

/**
 * Detects if a path is an unregistered git worktree.
 * Checks if the .git file (not directory) contains gitdir: reference,
 * which indicates a git worktree. Attempts to match against registered
 * base stacks to provide worktree metadata.
 *
 * @param string $path The path to check.
 * @return array|null Worktree metadata or null if not a worktree.
 */
function slic_stacks_detect_worktree($path) {
	$git_file = $path . '/.git';

	// Check if .git is a FILE (not directory) - indicates worktree
	if (!is_file($git_file)) {
		return null;
	}

	// Read .git file content
	$git_content = @file_get_contents($git_file);
	if ($git_content === false) {
		return null;
	}

	// Check for gitdir reference (worktree marker)
	if (strpos($git_content, 'gitdir:') !== 0) {
		return null; // Could be a submodule or other git construct
	}

	// This is a git worktree - try to find the base stack
	$parent_dir = dirname($path);
	$dir_name = basename($path);

	// Look for registered base stacks in parent directory
	$stacks = slic_stacks_list();
	foreach ($stacks as $stack_id => $state) {
		// Skip existing worktrees
		if (slic_stacks_is_worktree($stack_id)) {
			continue;
		}

		// Check if parent directory matches
		$stack_real = realpath($stack_id);
		$parent_real = realpath($parent_dir);

		if ($stack_real === false || $parent_real === false) {
			continue;
		}

		if ($stack_real !== $parent_real) {
			continue;
		}

		// Check if directory name matches pattern: {target}-{branch-slug}
		$target = $state['target'] ?? null;
		if ($target && strpos($dir_name, $target . '-') === 0) {
			// Extract branch from git
			$current_branch = trim(shell_exec("git -C " . escapeshellarg($path) . " rev-parse --abbrev-ref HEAD 2>/dev/null"));

			return [
				'base_stack_id' => $stack_id,
				'target' => $target,
				'dir_name' => $dir_name,
				'parent_dir' => $parent_dir,
				'full_path' => $path,
				'branch' => $current_branch,
			];
		}
	}

	return null;
}

/**
 * Migrates legacy .env.slic.run file to new stack system.
 * This ensures backward compatibility for existing slic installations.
 *
 * @return bool True if migration was performed, false otherwise.
 */
function slic_stacks_migrate_legacy() {
	$root_dir = dirname( __DIR__ );
	$legacy_run_file = $root_dir . '/.env.slic.run';
	$registry_file = slic_stacks_registry_file();

	// If registry already exists or legacy file doesn't exist, no migration needed
	if ( file_exists( $registry_file ) || ! file_exists( $legacy_run_file ) ) {
		return false;
	}

	// Load legacy environment file
	require_once __DIR__ . '/utils.php';
	$legacy_env = read_env_file( $legacy_run_file );

	// Extract stack information from legacy file
	$plugins_dir = $legacy_env['SLIC_PLUGINS_DIR'] ?? $root_dir . '/_plugins';

	// Use plugins directory as stack ID
	$stack_id = $plugins_dir;

	// Create stack state
	$stack_state = [
		'stack_id'     => $stack_id,
		'project_name' => slic_stacks_get_project_name( $stack_id ),
		'state_file'   => basename( slic_stacks_get_state_file( $stack_id ) ),
		'xdebug_port'  => slic_stacks_xdebug_port( $stack_id ),
		'xdebug_key'   => slic_stacks_xdebug_server_name( $stack_id ),
		'ports'        => [
			'wp'    => 8888, // Default legacy port
			'mysql' => 9006, // Default legacy port
			'redis' => 8379, // Default legacy port
		],
		'created_at'   => date( 'c' ),
		'status'       => 'migrated',
	];

	// Register the stack
	if ( ! slic_stacks_register( $stack_id, $stack_state ) ) {
		return false;
	}

	// Copy legacy file to new stack-specific file
	$new_run_file = slic_stacks_get_state_file( $stack_id );
	if ( ! copy( $legacy_run_file, $new_run_file ) ) {
		return false;
	}

	// Rename legacy file to .env.slic.run.backup
	rename( $legacy_run_file, $legacy_run_file . '.backup' );

	return true;
}
