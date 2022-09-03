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
	$stack   = slic_stack_array( true );
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
function service_dependencies( $service ) {
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
function service_requires( $service, ...$dependencies ) {
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
 * @return Closure A closure that should be called after the service is
 *                 up and running, to finish setting it up.
 */
function ensure_service_ready( $service ) {
	$noop = static function(){};

	switch ( $service ) {
		case 'wordpress':
			ensure_wordpress_ready();
			service_up_notify( $service );
			return static function ( $service ) {
				propagate_ip_address_of_to(
					[ 'wordpress' ],
					[ $service, 'slic' ],
					[ 'wordpress' => 'wordpress.test' ]
				);
				propagate_ip_address_of_to(
					[ 'wordpress' ],
					[ 'slic' ],
					[ 'wordpress' => 'wordpress.test' ]
				);
			};
		default:
			return $noop;
	}
}

/**
 * Ensures a service dependencies are all correctly set up, will
 * exit if not possible.
 *
 * @param string $service The service to ensure the dependencies for.
 *
 * @return Closure A closure that should be called after the service is
 *                 up and running to finish setting it up in respect to
 *                 its dependencies.
 */
function ensure_service_dependencies( $service ) {
	return ensure_services_running( service_dependencies( $service ) );
}

/**
 * Ensures a list of services is running and returns
 * a Closure that should be called to finish setting them up.
 *
 * @param array<string> $services A list of services to ensure
 *                                are running.
 *
 * @return Closure A closure that should be called after all
 *                 services are up to complete a service setup.
 */
function ensure_services_running( array $services ) {
	$on_up = [];
	foreach ( $services as $service ) {
		ensure_service_running( $service );
	}

	return static function ( $service ) use ( $on_up ) {
		foreach ( $on_up as $then ) {
			$then( $service );
		}
	};
}

/**
 * Returns whether a service is running or not.
 *
 * @param string $service The service to check.
 * @param bool $set If set, this value will either add or remove the service from the list
 *                  of running services.
 *
 * @return bool Whether a service is running or not.
 */
function service_running( $service, $set = null ) {
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
function get_service_id( $service ) {
	$root    = root();
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
function get_service_ip_address( $service_id ) {
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
function add_hosts_to_service( $service_id, array $hosts ) {
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
 * @param array<string> $of_services The list of services whose IP address should be propagated.
 * @param array<string> $to_services The list of services whose `/etc/hosts` file should be updated
 *                                   to include the IP addresses to hastname mappings of the previous
 *                                   list.
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
		$value = isset( $hostname_map[ $service ] ) ? $hostname_map[ $service ] : $service;
		$key = $ip_addresses[ $service ];

		return [ $key => $value ];
	}, $of_services ) );

	array_walk( $to_services_ids, static function ( $service_id ) use ( $hosts ) {
		add_hosts_to_service( $service_id, $hosts );
	}, $to_services_ids );
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
function ensure_service_running( $service, array $dependencies = null ) {
	if ( empty( $dependencies ) && service_running( $service ) ) {
		service_up_notify( $service );
		return 0;
	}

	$dependencies_on_up = $dependencies === null ?
		ensure_service_dependencies( $service )
		: ensure_services_running( $dependencies );

	if ( service_running( $service ) ) {
		service_up_notify( $service );
		return 0;
	}

	$own_on_up = ensure_service_ready( $service );

	$up_status = slic_realtime()( [ 'up', '-d', $service ] );
	service_running( $service, true );

	if ( $up_status !== 0 ) {
		return $up_status;
	}

	$dependencies_on_up( $service );
	$own_on_up( $service );

	service_up_notify( $service );

	return 0;
}

/**
 * Notifies about the up status of a service.
 *
 * @param string $service
 */
function service_up_notify( string $service ) : void {
	switch ( $service ) {
		case 'wordpress':
			echo colorize( PHP_EOL . "Your WordPress site is reachable at: <yellow>http://localhost:" . getenv( 'WORDPRESS_HTTP_PORT' ) . "</yellow>" . PHP_EOL );
		default:
			return;
	}
}
