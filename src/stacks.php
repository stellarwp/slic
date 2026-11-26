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

		$state['xdebug_key'] = slic_stacks_xdebug_server_name($stack_id);

		$stacks[$stack_id] = array_merge([
			'created_at' => date('c'),
			'status' => 'created',
		], $state);

		return slic_stacks_write_registry($stacks);
	});
}

/**
 * Writes the state file of a worktree stack, taking the WordPress, plugins, themes and container content directories from its base stack.
 *
 * @param string $worktree_stack_id The worktree stack identifier.
 * @param string $base_stack_id The base stack identifier.
 * @param string $target The target the worktree is a checkout of.
 * @return string The path to the written state file.
 */
function slic_stacks_write_worktree_state_file($worktree_stack_id, $base_stack_id, $target) {
	$base_state_file = slic_stacks_get_state_file($base_stack_id);
	$base_env = is_file($base_state_file) ? read_env_file($base_state_file) : [];
	$state_file = slic_stacks_get_state_file($worktree_stack_id);

	$state = [
		'SLIC_HERE_DIR' => $base_stack_id,
		'SLIC_WP_DIR' => $base_env['SLIC_WP_DIR'] ?? getenv('SLIC_WP_DIR'),
		'SLIC_PLUGINS_DIR' => $base_env['SLIC_PLUGINS_DIR'] ?? getenv('SLIC_PLUGINS_DIR'),
		'SLIC_THEMES_DIR' => $base_env['SLIC_THEMES_DIR'] ?? getenv('SLIC_THEMES_DIR'),
		'SLIC_WP_CONTENT_CONTAINER_DIR' => $base_env['SLIC_WP_CONTENT_CONTAINER_DIR'] ?? getenv('SLIC_WP_CONTENT_CONTAINER_DIR'),
		'SLIC_CURRENT_PROJECT' => $target,
	];

	// A Composer cache set on the base stack with `slic composer-cache set` applies to its worktrees too.
	foreach (['COMPOSER_CACHE_DIR', 'SLIC_BASE_COMPOSER_CACHE_DIR', 'SLIC_COMPOSER_CACHE_IS_EXPLICIT'] as $key) {
		if (isset($base_env[$key])) {
			$state[$key] = $base_env[$key];
		}
	}

	// The worktree uses the subdirectory of the target the base stack uses, as set with `slic use <target>/<subdir>`.
	if (($base_env['SLIC_CURRENT_PROJECT'] ?? null) === $target) {
		foreach (['SLIC_CURRENT_PROJECT_SUBDIR', 'SLIC_CURRENT_PROJECT_RELATIVE_PATH', 'SLIC_CURRENT_PROJECT_CONTAINER_PATH'] as $key) {
			if (isset($base_env[$key])) {
				$state[$key] = $base_env[$key];
			}
		}
	}

	write_env_file($state_file, $state);

	return $state_file;
}

/**
 * Unregisters a stack from the registry (thread-safe).
 * Uses file locking to prevent race conditions during concurrent stack operations.
 *
 * This function automatically cleans up associated state files (.env.slic.run.<hash>)
 * when unregistering stacks. This ensures consistent cleanup regardless of which
 * code path triggers unregistration.
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
					echo "Remove them from this stack first:\n";

					foreach ($worktrees as $wt_id => $wt_state) {
						echo "  slic worktree remove " . ($wt_state['worktree_branch'] ?? $wt_state['worktree_dir']) . "\n";
					}

					return false;
				}

				// Remove all worktrees and their state files
				foreach (array_keys($worktrees) as $wt_id) {
					// Clean up worktree state file
					$wt_state_file = slic_stacks_get_state_file($wt_id);
					if (file_exists($wt_state_file)) {
						@unlink($wt_state_file);
					}
					unset($stacks[$wt_id]);
				}
			}
		}

		// Clean up the stack's state file before unregistering
		$state_file = slic_stacks_get_state_file($stack_id);
		if (file_exists($state_file)) {
			@unlink($state_file);
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
 * Returns whether a path is a directory or inside it.
 *
 * @param string $path    The path to check.
 * @param string $dir     The directory to check the path against.
 * @param bool   $windows Whether to read the paths as Windows paths; defaults to the current OS.
 *
 * @return bool Whether the path is the directory or inside it.
 */
function slic_stacks_path_is_within($path, $dir, $windows = DIRECTORY_SEPARATOR === '\\') {
	// Only Windows paths use backslashes as separators; on POSIX a backslash is a filename character.
	if ($windows) {
		$path = str_replace('\\', '/', $path);
		$dir  = str_replace('\\', '/', $dir);
	}
	// Trailing slashes prevent partial matches, e.g. /foo and /foobar.
	$path = rtrim($path, '/') . '/';
	$dir  = rtrim($dir, '/') . '/';

	return strpos($path, $dir) === 0;
}

/**
 * Resolves the stack ID from the current working directory.
 * Uses prioritized resolution to prefer worktree stacks over base stacks.
 *
 * Resolution priority:
 * 1. CWD is within a worktree stack directory (checked FIRST)
 * 2. CWD is within a base stack directory, the nearest one first
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

				if (slic_stacks_path_is_within($cwd_real, $worktree_real)) {
					return $stack_id;
				}
			}
		}
	}

	// PRIORITY 2: CWD is within a base stack directory; with nested stacks, the nearest one wins
	$nearest_stack_id = null;
	$nearest_length = -1;
	foreach ($stacks as $stack_id => $state) {
		if (!slic_stacks_is_worktree($stack_id)) {
			$stack_real = realpath($stack_id);
			if ($stack_real === false) {
				// realpath failed, use original stack_id
				$stack_real = $stack_id;
			}

			if (slic_stacks_path_is_within($cwd_real, $stack_real) && strlen($stack_real) > $nearest_length) {
				$nearest_stack_id = $stack_id;
				$nearest_length = strlen($stack_real);
			}
		}
	}

	return $nearest_stack_id;
}

/**
 * Resolves a stack ID from a given path.
 * Supports ~ expansion, stack IDs with @ format, and finds matching stack by exact or parent directory match.
 * Prioritizes worktree stacks over base stacks when the path points to a worktree directory.
 *
 * @param string $path The path to resolve (can be a filesystem path or a stack ID with @).
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

	// Check if this is already a stack ID with @ format
	if (strpos($path, '@') !== false) {
		$stacks = slic_stacks_list();
		// If it's a registered stack ID, return it as-is
		if (isset($stacks[$path])) {
			return $path;
		}
		// Not a registered stack ID and not an existing path whose name contains @, treat as invalid
		if (!file_exists($path)) {
			return null;
		}
	}

	$stacks = slic_stacks_list();

	// A path that does not exist names only itself: a registered stack whose directory is gone, or nothing.
	$real_path = realpath($path);
	if ($real_path === false) {
		return isset($stacks[$path]) ? $path : null;
	}
	$path = $real_path;

	// PRIORITY 1: Check if this path is a worktree directory (exact match)
	// This ensures worktree stacks take precedence over base stacks
	foreach ($stacks as $stack_id => $state) {
		if (slic_stacks_is_worktree($stack_id)) {
			$worktree_path = $state['worktree_full_path'] ?? null;
			if ($worktree_path) {
				$worktree_real = realpath($worktree_path);
				if ($worktree_real !== false && $worktree_real === $path) {
					return $stack_id;
				}
			}
		}
	}

	// PRIORITY 2: Try exact match with base stacks
	if (isset($stacks[$path])) {
		return $path;
	}

	// PRIORITY 3: Check if path is within a worktree directory
	foreach ($stacks as $stack_id => $state) {
		if (slic_stacks_is_worktree($stack_id)) {
			$worktree_path = $state['worktree_full_path'] ?? null;
			if ($worktree_path) {
				$worktree_real = realpath($worktree_path);
				if ($worktree_real !== false) {
					if (slic_stacks_path_is_within($path, $worktree_real)) {
						return $stack_id;
					}
				}
			}
		}
	}

	// PRIORITY 4: Find the nearest stack by checking parent directories
	$current_path = $path;
	while ($current_path !== dirname($current_path)) {
		// Check worktrees first
		foreach ($stacks as $stack_id => $state) {
			if (slic_stacks_is_worktree($stack_id)) {
				$worktree_path = $state['worktree_full_path'] ?? null;
				if ($worktree_path) {
					$worktree_real = realpath($worktree_path);
					if ($worktree_real !== false && $worktree_real === $current_path) {
						return $stack_id;
					}
				}
			}
		}
		// Then check base stacks
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
 * The key is built using the XDK configuration env var as root.
 *
 * Format: "{key_root:-slic}_{hash}" where hash is the first 8 characters of MD5
 * Example: "slic_a7f3c891"
 *
 * @param string $stack_id The stack identifier (absolute path).
 * @return string The XDebug server name.
 */
function slic_stacks_xdebug_server_name($stack_id) {
	// Use the same hash approach as project names for consistency
	$hash = substr(md5($stack_id), 0, 8);

	// Read XDK root from config files, not from getenv() which may be
	// overwritten by xdebug_setup_env_vars() with a full stack-specific key
	// (e.g. "slic_a1dc6067") instead of just the root "slic".
	$root = dirname(__DIR__);
	$config = read_env_file($root . '/.env.slic');
	if (file_exists($root . '/.env.slic.local')) {
		$config = array_merge($config, read_env_file($root . '/.env.slic.local'));
	}
	$key_root = $config['XDK'] ?? 'slic';

	return $key_root . '_' . $hash;
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
 * @return array|null Array with the keys 'wp', 'mysql' and 'redis' of the running services, or null if none is.
 */
function slic_stacks_read_ports_from_docker($stack_id) {
	$project_name = slic_stacks_get_project_name($stack_id);

	// Check if containers are actually running
	$check_command = docker_bin() . " ps -q -f label=com.docker.compose.project='$project_name' 2>/dev/null";
	$check_command_output = shell_exec( $check_command );
	$container_ids = $check_command_output ? trim( $check_command_output ) : '';

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
		$get_id_command = docker_bin() . " ps -q -f label=com.docker.compose.project='$project_name' " .
		                  "-f label=com.docker.compose.service=$service_name 2>/dev/null";
		$service_id = trim((string) shell_exec($get_id_command));

		if (empty($service_id)) {
			continue;
		}

		// Docker prints one mapping per host address, e.g. `0.0.0.0:32768`, `127.0.0.1:32768` or `[::]:32768`.
		$port_command = docker_bin() . " port $service_id $container_port 2>/dev/null";
		$mappings = explode("\n", trim((string) shell_exec($port_command)));
		$host_port = substr((string) strrchr($mappings[0], ':'), 1);

		if (!empty($host_port) && is_numeric($host_port)) {
			$ports[$port_key] = (int)$host_port;
		}
	}

	// A service that is not running, such as Redis, has no port.
	return $ports ?: null;
}

/**
 * Checks if a stack's containers are currently running.
 * Uses caching to avoid repeated Docker calls within a single execution.
 *
 * @param string $stack_id The stack identifier.
 * @return bool True if containers are running, false otherwise.
 */
function slic_stacks_is_running($stack_id) {
	// Cache to avoid repeated Docker calls in a single execution
	static $cache = [];

	if (isset($cache[$stack_id])) {
		return $cache[$stack_id];
	}

	$project_name = slic_stacks_get_project_name($stack_id);

	// Add timeout protection to prevent hanging on slow Docker
	// Use timeout command if available (2 seconds)
	$timeout_prefix = '';
	if (shell_exec('command -v timeout 2>/dev/null')) {
		$timeout_prefix = 'timeout 2 ';
	}

	// Check if containers are actually running
    $check_command = "{$timeout_prefix}" . docker_bin() . " ps -q -f label=com.docker.compose.project='$project_name' 2>/dev/null";
    $check_command_output = shell_exec($check_command);
	$container_ids = $check_command_output ? trim($check_command_output) : null;

	$is_running = !empty($container_ids);
	$cache[$stack_id] = $is_running;

	return $is_running;
}

/**
 * Ensures a stack has port assignments, reading them from Docker when its containers are running.
 * Updates the registry when the ports Docker reports have changed; otherwise falls back to the stored ports.
 *
 * @param string $stack_id The stack identifier.
 * @return bool True if ports are available, false otherwise.
 */
function slic_stacks_ensure_ports($stack_id) {
	$stack = slic_stacks_get($stack_id);

	if (null === $stack) {
		return false;
	}

	// Docker assigns new host ports whenever the containers are recreated, so read them first
	$ports = slic_stacks_read_ports_from_docker($stack_id);

	if (null !== $ports) {
		// Update the registry with the actual ports
		if (($stack['ports'] ?? null) !== $ports) {
			slic_stacks_update($stack_id, ['ports' => $ports]);
		}
		return true;
	}

	// Containers not running: fall back to the last ports Docker assigned, if any
	return !empty($stack['ports']) && is_array($stack['ports']);
}

/**
 * Checks if a stack ID represents a worktree.
 *
 * @param string $stack_id The stack identifier.
 * @return bool True if worktree.
 */
function slic_stacks_is_worktree($stack_id) {
	if (strpos($stack_id, '@') === false) {
		return false;
	}

	// A base stack's own path can contain @, so trust the registry for registered stacks.
	$stacks = slic_stacks_list();
	if (isset($stacks[$stack_id])) {
		return !empty($stacks[$stack_id]['is_worktree']);
	}

	return !is_dir($stack_id);
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

	// A base path can contain @, a worktree directory name cannot: split on the last @.
	$at = strrpos($stack_id, '@');
	$parts = [substr($stack_id, 0, $at), substr($stack_id, $at + 1)];

	// Validate that both components are non-empty
	if (empty($parts[0]) || empty($parts[1])) {
		return null; // Both base path and worktree directory are required
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
	// Start from the root of the git checkout the path is in, so a worktree is detected from its subdirectories too.
	while (!file_exists($path . '/.git') && dirname($path) !== $path) {
		$path = dirname($path);
	}

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

	require_once __DIR__ . '/worktree-utils.php';

	// Look for registered base stacks whose plugins or themes directory is the parent directory
	$stacks = slic_stacks_list();
	$parent_real = realpath($parent_dir);
	foreach ($stacks as $stack_id => $state) {
		// Skip existing worktrees
		if (slic_stacks_is_worktree($stack_id) || $parent_real === false) {
			continue;
		}

		$state_file = slic_stacks_get_state_file($stack_id);
		$stack_env = is_file($state_file) ? read_env_file($state_file) : [];
		$content_dirs = array_filter([$stack_env['SLIC_PLUGINS_DIR'] ?? null, $stack_env['SLIC_THEMES_DIR'] ?? null]);
		if (!in_array($parent_real, array_map('realpath', $content_dirs), true)) {
			continue;
		}

		// A worktree that is already registered needs no detection
		if (isset($stacks[$stack_id . '@' . $dir_name])) {
			return null;
		}

		// Check if directory name matches pattern: {target}-{branch-slug}
		// A sibling such as a submodule or another repository's worktree can share the prefix: ask the target's git.
		$target = $state['target'] ?? null;
		if ($target && strpos($dir_name, $target . '-') === 0 && slic_worktree_is_existing($parent_dir . '/' . $target, $path)) {
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

	// The stack ID is the directory `slic here` ran in; older files only have the plugins directory.
	$stack_id = ( $legacy_env['SLIC_HERE_DIR'] ?? '' ) ?: ( $legacy_env['SLIC_PLUGINS_DIR'] ?? '' ) ?: $root_dir . '/_plugins';

	// Create stack state
	$stack_state = [
		'stack_id'     => $stack_id,
		'project_name' => slic_stacks_get_project_name( $stack_id ),
		'state_file'   => basename( slic_stacks_get_state_file( $stack_id ) ),
		'xdebug_port'  => slic_stacks_xdebug_port( $stack_id ),
		'xdebug_key'   => slic_stacks_xdebug_server_name( $stack_id ),
		// Ports are Docker-assigned and read from Docker once the stack is running.
		'ports'        => null,
		'target'       => $legacy_env['SLIC_CURRENT_PROJECT'] ?? null,
		'created_at'   => date( 'c' ),
		'status'       => 'migrated',
	];

	// Register the stack
	if ( ! slic_stacks_register( $stack_id, $stack_state ) ) {
		return false;
	}

	// Back up the legacy file to .env.slic.run.backup
	if ( ! copy( $legacy_run_file, $legacy_run_file . '.backup' ) ) {
		return false;
	}

	// The settings `debug` and `composer set-version` write to .env.slic.run for all stacks stay there.
	$global_env = array_intersect_key( $legacy_env, array_flip( [ 'CLI_VERBOSITY', 'SLIC_COMPOSER_VERSION' ] ) );
	write_env_file( slic_stacks_get_state_file( $stack_id ), array_diff_key( $legacy_env, $global_env ) );
	write_env_file( $legacy_run_file, $global_env );

	echo colorize( PHP_EOL . "<yellow>✓ Migrated existing slic configuration to multi-stack format.</yellow>" . PHP_EOL );
	echo colorize( "Your previous configuration has been backed up to .env.slic.run.backup" . PHP_EOL . PHP_EOL );

	// The .env files are not loaded yet: read the settings 2.x ran Compose with, each file overriding the one before it.
	$legacy_settings = array_merge(
		getenv(),
		read_env_file( $root_dir . '/.env.slic' ),
		is_file( $root_dir . '/.env.slic.local' ) ? read_env_file( $root_dir . '/.env.slic.local' ) : [],
		$legacy_env
	);
	// 2.x ran Compose without -p: its project is COMPOSE_PROJECT_NAME or the slic directory, as Compose normalizes it.
	$legacy_project = ( $legacy_settings['COMPOSE_PROJECT_NAME'] ?? '' ) ?: basename( $root_dir );
	$legacy_project = ltrim( preg_replace( '/[^a-z0-9_-]/', '', strtolower( $legacy_project ) ), '_-' );
	$compose_bin    = ( $legacy_settings['SLIC_DOCKER_COMPOSE_BIN'] ?? '' ) ?: docker_compose_bin();
	$down_command   = $compose_bin . ' -p ' . escapeshellarg( $legacy_project ) . ' down --remove-orphans';
	exec( $down_command . ' 2>&1', $down_output, $down_status );
	// The registry now exists, so the migration never runs again: the user has to stop the 2.x containers.
	if ( 0 !== $down_status ) {
		echo colorize( "<magenta>Could not stop the slic 2.x containers. Stop them by running:</magenta>" . PHP_EOL . $down_command . PHP_EOL . PHP_EOL );
	}

	return true;
}
