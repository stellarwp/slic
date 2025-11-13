<?php
/**
 * Functions to start, stop, set up services.
 */

namespace StellarWP\Slic;

use Exception;

/**
 * Returns the `docker compose` schema parsed from the `slic` files loaded in the current
 * request.
 *
 * @return array<string,array> The loaded `docker compose` format files, merged in array format.
 */
function stack_schema() {
	static $schema;

	if ( $schema !== null ) {
		return $schema;
	}

	require_once __DIR__ . '/../includes/Spyc/Spyc.php';

	$schemas = [];
	$stack = slic_stack_array( true );
	foreach ( $stack as $file ) {
		if ( ! is_readable( $file ) ) {
			echo magenta( "File $file cannot be found or is not readable." );
			exit( 1 );
		}

		try {
			$schemas[] = spyc_load_file( $file );
		} catch ( Exception $e ) {
			echo magenta( "Failed to parse contents of file $file: {$e->getMessage()}." );
			exit( 1 );
		}
	}

	$schema = array_merge_multi( ...$schemas );

	return $schema;
}

/**
 * Returns the `service` section of the `docker compose` format stack files loaded in the request
 * for `slic`.
 *
 * @return array<string,array> The `services` section of the loaded `docker compose` format files.
 */
function services_schema() {
	$stack_schema = stack_schema();

	return isset( $stack_schema['services'] ) ? $stack_schema['services'] : [];
}

/**
 * Returns the services in the stack.
 *
 * @return array<string> The services in the stack.
 */
function get_services() {
	$services = services_schema();
	$services = array_keys( $services );
	sort( $services );

	return $services;
}

/**
 * Ensures a list of services is running and returns
 * a Closure that should be called to finish setting them up.
 *
 * @param array<string> $services A list of services to ensure
 *                                are running.
 *
 * @return void On-up callbacks will be registered on the global
 *              callback stack.
 */
function ensure_services_running( array $services  ): void {
	foreach ( $services as $service ) {
		ensure_service_running( $service );
	}
}

/**
 * Returns whether a service is running or not.
 *
 * @param string $service The service to check.
 *
 * @return bool Whether a service is running or not.
 */
function service_running( string $service ) {
	$running_services = slic_cache_get( 'running_services' );

	if ( $running_services === null ) {
		$ps        = slic_process()( [ 'ps', '--services', '--filter', '"status=running"' ] );
		$ps_status = $ps( 'status' );
		if ( $ps_status !== 0 ) {
			return false;
		}
		$running_services = explode( "\n", $ps( 'string_output' ) );
		slic_cache_set( 'running_services', $running_services );
	}

	return in_array( $service, $running_services, true );
}

/**
 * Quietly tears down the stack, silencing any error messages.
 *
 * @param string|null $stack_id The stack to tear down. If null, uses current stack.
 * @return int The process exit status.
 */
function quietly_tear_down_stack( $stack_id = null ) {
	ob_start();
	setup_slic_env( root(), false, $stack_id );
	$status = teardown_stack( true, $stack_id );
	ob_end_clean();

	return $status;
}

/**
 * Returns the service container ID, if any.
 *
 * @param string $service The name of the service to return the container ID for, e.g. `wordpress`.
 * @param string|null $stack_id The stack to get the service from. If null, uses current stack.
 *
 * @return string|null The service container ID if found, `null` otherwise.
 */
function get_service_id( string $service, $stack_id = null ) {
	// Load stacks.php functions if not already loaded
	if ( ! function_exists( 'slic_stacks_get_project_name' ) ) {
		require_once __DIR__ . '/stacks.php';
	}

	// Get the project name for filtering
	if ( null === $stack_id ) {
		if ( ! function_exists( 'slic_current_stack' ) ) {
			require_once __DIR__ . '/slic.php';
		}
		$stack_id = slic_current_stack();
	}

	if ( null === $stack_id ) {
		// No stack found, fall back to old behavior (working_dir)
		$root = root();
		$command = "docker ps -f label=com.docker.compose.project.working_dir='$root' " .
		           "-f label=com.docker.compose.service=$service --format '{{.ID}}'";
	} else {
		// Use project name for filtering
		$project_name = slic_stacks_get_project_name( $stack_id );
		$command = "docker ps -f label=com.docker.compose.project='$project_name' " .
		           "-f label=com.docker.compose.service=$service --format '{{.ID}}'";
	}

	debug( "Executing command: $command" . PHP_EOL );
	exec( $command, $output, $status );

	return $status === 0 ? reset( $output ) : null;
}

/**
 * Ensures a service is running by ensuring all its pre-conditions and services
 * it depends on.
 *
 * @param string $service The name of the service to ensure running, e.g., `wordpress`.
 *
 * @return int The exit status of the command that will ensure the service is running;
 *             following UNIX convention, a `0` indicates a success, any other value indicates a
 *             failure.
 */
function ensure_service_running( string $service ): int {
	if ( service_running( $service ) ) {
		return 0;
	}
	// Wait for the service to be up|healthy, detached mode is implied.
	return slic_realtime()( [ 'up', '--wait', $service ] );
}
