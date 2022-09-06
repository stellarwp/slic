<?php
/**
 * Functions to start, stop, set up services.
 */

namespace StellarWP\Slic;

use Closure;
use Exception;

/**
 * Returns the `docker-compose` schema parsed from the `slic` files loaded in the current
 * request.
 *
 * @return array<string,array> The loaded `docker-compose` format files, merged in array format.
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
 * Returns the `service` section of the `docker-compose` format stack files loaded in the request
 * for `slic`.
 *
 * @return array<string,array> The `services` section of the loaded `docker-compose` format files.
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
 * Returns a list of dependencies for service.
 *
 * Dependencies are read from the stack docker-compose format file in the `links` and
 * `depends_on` sections.
 *
 * @param string $service The name of the service to get the dependencies for.
 *
 * @return array<string> A list of the service dependencies; empty if the service has no
 *                       dependencies.
 */
function service_dependencies( string $service ) {
	$services_schema = services_schema();
	$service_links = isset( $services_schema[ $service ]['links'] ) ? $services_schema[ $service ]['links'] : [];
	$service_dependencies = isset( $services_schema[ $service ]['depends_on'] ) ? $services_schema[ $service ]['depends_on'] : [];

	return array_merge( $service_links, $service_dependencies );
}

/**
 * Checks whether a service requires at least one of the listed services in the context of the
 * container stack either by means of `links` or `depends_on` specification.
 *
 * @param string $service         The service to check dependencies for.
 * @param string ...$dependencies A list of dependencies to check.
 *
 * @return false Whether the specified service requires at least one of the dependencies or not.
 */
function service_requires( string $service, ...$dependencies ) {
	if ( empty( $dependencies ) ) {
		return false;
	}

	return (bool) count( array_intersect( service_dependencies( $service ), $dependencies ) );
}

/**
 * Ensures a service is ready to run.
 *
 * @param string $service The service to check.
 *
 * @return void The service readiness is ensured; if required
 *              by the service, a callback that should be called
 *              after the service is ready is registered in the
 *              Services callback stack.
 */
function ensure_service_ready( string $service ): void {
	$propagate_wordpress_address = static function () {
		// If wordpress isn't running, there's no IP address to propagate.
		if ( ! service_running( 'wordpress' ) ) {
			return;
		}

		propagate_ip_address_of_to(
			[ 'wordpress' ],
			[ 'wordpress', 'slic', 'chrome' ],
			[ 'wordpress' => 'wordpress.test' ]
		);
	};

	switch ( $service ) {
		case 'wordpress':
			ensure_wordpress_ready();
			services_callback_stack()->add( 'propagate_wp_address', $propagate_wordpress_address );
			services_callback_stack()->add( 'wordpress_notify', '\StellarWP\Slic\service_wordpress_notify' );
			break;
		case 'slic':
		case 'chrome':
			services_callback_stack()->add( 'propagate_wp_address', $propagate_wordpress_address );
			break;
		default:
			break;
	}
}

/**
 * Ensures a service dependencies are all correctly set up, will
 * exit if not possible.
 *
 * @param string $service The service to ensure the dependencies for.
 *
 * @return void
 */
function ensure_service_dependencies( string $service ): void {
	ensure_services_running_no_callbacks( service_dependencies( $service ) );
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
	ensure_services_running_no_callbacks( $services );
	services_callback_stack()->call();
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
function ensure_services_running_no_callbacks( array $services  ): void {
	// Impose an order to make sure dependencies are optimized.
	$order = [ 'db', 'redis', 'chrome', 'slic', 'wordpress' ];
	usort( $services, static function ( $a, $b ) use ( $order ) {
		$a_index = array_search( $a, $order, true );
		$b_index = array_search( $b, $order, true );

		return $a_index <=> $b_index;
	} );
	foreach ( $services as $service ) {
		ensure_service_running_no_callbacks( $service );
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
	$ps = slic_process()( [ 'ps', '--services', '--filter', '"status=running"' ] );
	$ps_status = $ps( 'status' );

	if ( $ps_status !== 0 ) {
		return false;
	}

	$running_services = explode( "\n", $ps( 'string_output' ) );

	return in_array( $service, $running_services, true );
}

/**
 * Quietly tears down the stack, silencing any error messages.
 *
 * @return int The process exit status.
 */
function quietly_tear_down_stack() {
	ob_start();
	setup_slic_env( root() );
	$status = teardown_stack( true );
	ob_end_clean();

	return $status;
}

/**
 * Returns the service container ID, if any.
 *
 * @param string $service The name of the service to return the container ID for, e.g. `wordpress`.
 *
 * @return string|null The service container ID if found, `null` otherwise.
 */
function get_service_id( string $service ) {
	$root = root();
	$command = "docker ps -f label=com.docker.compose.project.working_dir='$root' " .
	           "-f label=com.docker.compose.service=$service --format '{{.ID}}'";
	debug( "Executing command: $command" . PHP_EOL );
	exec( $command, $output, $status );

	return $status === 0 ? reset( $output ) : null;
}

/**
 * Returns the IP address of a stack service, if it's up.
 *
 * @param string $service_id The service container ID, e.g. `15148db6f216`.
 *
 * @return string|null The service IP address if the service is up and the IP address
 *                     could be found, `null` otherwise.
 */
function get_service_ip_address( string $service_id ) {
	$command = "docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $service_id";
	debug( "Executing command: $command" . PHP_EOL );
	exec( $command, $output, $status );

	return $status === 0 ? reset( $output ) : null;
}

/**
 * Updates a running container /etc/hosts file to add further mappings.
 *
 * Updating a running container `/etc/hosts` file can only be done as the `root` user (UID and GID 0).
 * The `/etc/hosts` file is `rw` (read and write) for the `root` user, and `r` only for all other users.
 * Since the `x` (execute) mode is missing for any user, we cannot use `sed` to update in-place as
 * that requires "swapping" the updated file and that requires execute privileges.
 * The following code reads the current file contents as `root`, updates them, and replaces the file
 * completely using as `root`.
 *
 * @param string               $service_id The service docker container ID.
 * @param array<string,string> $hosts      A map from IP addresses to the hostnames that will be added
 *                                         to the container `/etc/hosts` file.
 *
 * @return true To indicate the operation was completed correctly, the function
 *              will `exit` with an error, otherwise.
 */
function add_hosts_to_service( string $service_id, array $hosts ) {
	// Read the current contents of the `/etc/hosts` file.
	$read_command = "docker exec -u '0:0' $service_id bash -c 'cat /etc/hosts'";
	debug( "Executing command: $read_command" . PHP_EOL );
	exec( $read_command, $output, $status );

	if ( $status !== 0 ) {
		echo magenta( "Could not get service $service_id /etc/hosts file contents." );
		exit( 1 );
	}

	// If a line is already present in the file, do not re-add it.
	$new_lines = array_filter( $output, static function ( $line ) use ( $hosts ) {
		list( $ip, $hostname ) = explode( "\t", $line, 2 );

		return ! in_array( $hostname, $hosts, true );
	} );

	// The format conventionally used in the `/etc/hosts` file is `<ip_address>\t<hostname>`.
	$hosts_string = '';
	foreach ( $hosts as $ip_address => $hostname ) {
		$hosts_string .= "$ip_address\t$hostname\n";
	}

	$new_lines = implode( "\n", $new_lines ) . "\n" . $hosts_string;

	// Write the new lines replacing the `/etc/hosts` file contents.
	$write_command = "docker exec -u '0:0' $service_id bash -c 'echo -e \"$new_lines\\\n\" > /etc/hosts'";
	debug( "Executing command: $write_command" . PHP_EOL );
	exec( $write_command, $output, $status );

	if ( $status !== 0 ) {
		echo magenta( "Could not update service $service_id /etc/hosts file contents." );
		exit( 1 );
	}

	return true;
}

/**
 * Updates the `/etc/hosts` file of a list of services to include the IP address of another set of
 * services.
 *
 * @param array<string>        $of_services  The list of services whose IP address should be propagated.
 * @param array<string>        $to_services  The list of services whose `/etc/hosts` file should be updated
 *                                           to include the IP addresses to hastname mappings of the previous
 *                                           list.
 * @param array<string,string> $hostname_map A map from service names to the hostnames they should be
 *                                           mapped to.
 *
 * @return void The method does not return any value and will have the side effect of updating the
 *              `/etc/hosts` file of services.
 */
function propagate_ip_address_of_to( array $of_services, array $to_services, array $hostname_map = [] ) {
	if ( empty( $of_services ) || empty( $to_services ) ) {
		return;
	}

	// There might be intersection between source and destination services.
	$all_services = array_unique( array_merge( $of_services, $to_services ) );
	$services_ids = array_combine(
		$all_services,
		array_map( 'StellarWP\Slic\get_service_id', $all_services )
	);

	$of_services_ids = array_intersect_key( $services_ids, array_combine( $of_services, array_fill( 0, count( $of_services ), true ) ) );
	$to_services_ids = array_intersect_key( $services_ids, array_combine( $to_services, array_fill( 0, count( $to_services ), true ) ) );

	$ip_addresses = array_combine(
		$of_services,
		array_map( 'StellarWP\Slic\get_service_ip_address', $of_services_ids )
	);

	$hosts = array_merge( ...array_map( static function ( $service ) use ( $ip_addresses, $hostname_map ) {
		$value = $hostname_map[ $service ] ?? $service;
		$key = $ip_addresses[ $service ];

		return [ $key => $value ];
	}, $of_services ) );

	array_walk( $to_services_ids, static function ( $service_id ) use ( $hosts ) {
		$service_id && add_hosts_to_service( $service_id, $hosts );
	}, $to_services_ids );
}

/**
 * Ensures a service is running by ensuring all its pre-conditions and services
 * it depends on.
 *
 * @param string        $service      The name of the service to ensure running, e.g., `wordpress`.
 * @param array<string> $dependencies The list of services that should be running.
 *
 * @return int The exit status of the command that will ensure the service is running;
 *             following UNIX convention, a `0` indicates a success, any other value indicates a
 *             failure.
 */
function ensure_service_running( string $service, array $dependencies = [] ): int {
	$status = ensure_service_running_no_callbacks( $service, $dependencies );

	services_callback_stack()->call();

	return $status;
}

/**
 * Ensures a service is running by ensuring all its pre-conditions and services
 * it depends on.
 *
 * @param string        $service      The name of the service to ensure running, e.g., `wordpress`.
 * @param array<string> $dependencies The list of services that should be running.
 *
 * @return int The exit status of the command that will ensure the service is running;
 *             following UNIX convention, a `0` indicates a success, any other value indicates a
 *             failure.
 */
function ensure_service_running_no_callbacks( string $service, array $dependencies = [] ): int {
	if ( empty( $dependencies ) && service_running( $service ) ) {
		return 0;
	}

	if ( empty( $dependencies ) ) {
		ensure_service_dependencies( $service );
	} else {
		ensure_services_running_no_callbacks( $dependencies );
	}

	if ( service_running( $service ) ) {
		return 0;
	}

	ensure_service_ready( $service );

	$up_status = slic_realtime()( [ 'up', '-d', $service ] );
	service_running( $service );

	if ( $up_status !== 0 ) {
		return $up_status;
	}

	return 0;
}

/**
 * Returns the singleton instance of the Services callback stack.
 *
 * @return Callback_Stack The singleton instance of the Services callback stack.
 */
function services_callback_stack(): Callback_Stack {
	static $callback_stack;

	if ( ! $callback_stack instanceof Callback_Stack ) {
		$callback_stack = new Callback_Stack();
	}

	return $callback_stack;
}
