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
 * Registers a new stack in the registry.
 *
 * @param string $stack_id The stack identifier (full directory path).
 * @param array $state The stack state data.
 * @return bool True on success, false on failure.
 */
function slic_stacks_register($stack_id, array $state) {
	$stacks = slic_stacks_list();
	$stacks[$stack_id] = $state;

	return slic_stacks_write_registry($stacks);
}

/**
 * Unregisters a stack from the registry.
 *
 * @param string $stack_id The stack identifier to remove.
 * @return bool True on success, false on failure.
 */
function slic_stacks_unregister($stack_id) {
	$stacks = slic_stacks_list();

	if (!isset($stacks[$stack_id])) {
		return true; // Already not registered
	}

	unset($stacks[$stack_id]);

	return slic_stacks_write_registry($stacks);
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
	$stacks = slic_stacks_list();

	if (!isset($stacks[$stack_id])) {
		return false;
	}

	$stacks[$stack_id] = array_merge($stacks[$stack_id], $state);

	return slic_stacks_write_registry($stacks);
}

/**
 * Writes the stacks registry to disk.
 *
 * @param array $stacks The stacks data to write.
 * @return bool True on success, false on failure.
 */
function slic_stacks_write_registry(array $stacks) {
	$registry_file = slic_stacks_registry_file();
	$json = json_encode($stacks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

	if ($json === false) {
		return false;
	}

	$result = file_put_contents($registry_file, $json);
	return $result !== false;
}

/**
 * Resolves the stack ID from the current working directory.
 * Matches exact path or finds parent directory that matches a registered stack.
 *
 * @return string|null The stack ID or null if not found.
 */
function slic_stacks_resolve_from_cwd() {
	$cwd = getcwd();

	if ($cwd === false) {
		return null;
	}

	return slic_stacks_resolve_from_path($cwd);
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
		if (strpos($path, $stack_id) === 0) {
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
 * Generates a deterministic XDebug port for a stack based on its path.
 *
 * Port range: 49000-59000 (10,000 possible unique ports)
 * - Avoids well-known ports (< 1024)
 * - Avoids common service ports (3306, 6379, 8080, etc.)
 * - Avoids Docker's ephemeral port range (32768-65535)
 * - Provides 10,000 possible unique ports
 *
 * @param string $stack_id The stack identifier (absolute path).
 * @return int The XDebug port number (49000-59000).
 */
function slic_stacks_xdebug_port($stack_id) {
	// Use the same hash approach as project names for consistency
	$hash = substr(md5($stack_id), 0, 8);

	// Convert hex hash to decimal and map to port range
	$hash_decimal = hexdec($hash);

	// Port range: 49000 to 59000 (10,000 ports)
	$min_port = 49000;
	$max_port = 59000;
	$port_range = $max_port - $min_port + 1;

	// Map hash to port range
	$port = $min_port + ($hash_decimal % $port_range);

	return $port;
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
