<?php
/**
 * Functions to start, stop, set up services.
 */

namespace TEC\Tric;

/**
 * Returns the `docker-compose` schema parsed from the `tric` files loaded in the current
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
	$stack   = tric_stack_array( true );
	foreach ( $stack as $file ) {
		if ( ! is_readable( $file ) ) {
			echo magenta( "File $file cannot be found or is not readable." );
			exit( 1 );
		}

		try {
			$schemas[] = spyc_load_file( $file );
		} catch ( \Exception $e ) {
			echo magenta( "Failed to parse contents of file $file: {$e->getMessage()}." );
			exit( 1 );
		}
	}

	$schema = array_merge_multi( ...$schemas );

	return $schema;
}

/**
 * Returns the `service` section of the `docker-compose` format stack files loaded in the request
 * for `tric`.
 *
 * @return array<string,array> The `services` section of the loaded `docker-compose` format files.
 */
function services_schema() {
	$stack_schema = stack_schema();

	return isset( $stack_schema['services'] ) ? $stack_schema['services'] : [];
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

	$services_schema      = services_schema();
	$service_links        = isset( $services_schema[ $service ]['links'] ) ? $services_schema[ $service ]['links'] : [];
	$service_dependencies = isset( $services_schema[ $service ]['depends_on'] ) ? $services_schema[ $service ]['depends_on'] : [];

	return (bool) count( array_intersect( array_merge( $service_links, $service_dependencies ), $dependencies ) );
}

/**
 * Ensures a service dependencies are all correctly set up, will
 * exit if not possible.
 *
 * @param string $service The service to ensure the dependencies for.
 */
function ensure_service_dependencies( $service ) {
	if ( $service === 'wordpress' || service_requires( $service, 'wordpress' ) ) {
		ensure_wordpress_ready();
		ensure_service_running( 'wordpress', false );
		propagate_ip_address_of_to( [ 'wordpress' ], [ $service ], [ 'wordpress' => 'wordpress.test' ] );
	}
}

/**
 * Returns whether a service is running or not.
 *
 * @param $service
 *
 * @return bool Whether a service is running or not.
 */
function service_running( $service ) {
	$ps               = tric_process()( [ 'ps', '--services', '--filter', '"status=running"' ] );
	$ps_status        = $ps( 'status' );
	$running_services = explode( "\n", $ps( 'string_output' ) );

	return $ps_status === 0 && in_array( $service, $running_services, true );
}

/**
 * Quietly tears down the stack, silencing any error messages.
 *
 * @return int The process exit status.
 */
function quietly_tear_down_stack() {
	ob_start();
	setup_tric_env( root() );
	$status = teardown_stack( true );
	ob_end_clean();

	return $status;
}

function get_service_id( $service ) {
	$root    = root();
	$command = "docker ps -f label=com.docker.compose.project.working_dir='$root' " .
	           "-f label=com.docker.compose.service=$service --format '{{.ID}}'";
	debug( "Executing command: $command\n" );
	exec( $command, $output, $status );

	return $status === 0 ? reset( $output ) : null;
}

function get_service_ip_address( $service_id ) {
	$command = "docker inspect --format '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $service_id";
	debug( "Executing command: $command\n" );
	exec( $command, $output, $status );

	return $status === 0 ? reset( $output ) : null;
}

function add_hosts_to_service( $service_id, array $hosts ) {
	$read_command = "docker exec -u '0:0' $service_id bash -c 'cat /etc/hosts'";
	debug( "Executing command: $read_command\n" );
	exec( $read_command, $output, $status );

	if ( $status !== 0 ) {
		echo magenta( "Could not get service $service_id /etc/hosts file contents." );
		exit( 1 );
	}

	/*
	 * The /etc/hosts file will be read top-to-bottom and will stop at the 1st occurrence.
	 * While the container is running, the file cannot be moved or replaced; lines cannot be
	 * removed from it; we'll prepend the new lines to it.
	 * Lines have the following format: <ip>\t<hostname>.
	 */

	$new_lines = array_filter( $output, static function ( $line ) use ( $hosts ) {
		list( $ip, $hostname ) = explode( "\t", $line, 2 );

		return ! in_array( $hostname, $hosts, true );
	} );

	$hosts_string = '';
	foreach ( $hosts as $ip_address => $hostname ) {
		$hosts_string .= "$ip_address\t$hostname\n";
	}

	$new_lines = implode( "\n", $new_lines ) . "\n" . $hosts_string;

	$write_command = "docker exec -u '0:0' $service_id bash -c 'echo -e \"$new_lines\"\\n' > /etc/hosts";
	debug( "Executing command: $write_command\n" );
	exec( $write_command, $output, $status );

	if ( $status !== 0 ) {
		echo magenta( "Could not update service $service_id /etc/hosts file contents." );
		exit( 1 );
	}

	exec( $read_command, $output, $status );

	return true;
}

function propagate_ip_address_of_to( array $of_services, array $to_services, array $hostname_map = [] ) {
	if ( empty( $of_services ) || empty( $to_services ) ) {
		return;
	}

	$all_services = array_unique( array_merge( $of_services, $to_services ) );
	$services_ids = array_combine(
		$all_services,
		array_map( 'TEC\Tric\get_service_id', $all_services )
	);

	$of_services_ids = array_intersect_key( $services_ids, array_combine( $of_services, array_fill( 0, count( $of_services ), true ) ) );
	$to_services_ids = array_intersect_key( $services_ids, array_combine( $to_services, array_fill( 0, count( $to_services ), true ) ) );

	$ip_addresses = array_combine(
		$of_services,
		array_map( 'TEC\Tric\get_service_ip_address', $of_services_ids )
	);

	$hosts = array_merge( ...array_map( static function ( $service ) use ( $ip_addresses, $hostname_map ) {
		$value = isset( $hostname_map[ $service ] ) ? $hostname_map[ $service ] : $service;
		$key   = $ip_addresses[ $service ];

		return [ $key => $value ];
	}, $to_services ) );

	array_walk( $to_services_ids, static function ( $service_id ) use ( $hosts ) {
		add_hosts_to_service( $service_id, $hosts );
	}, $to_services_ids );
//	export _IP=$$(docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' wp-browser_wordpress) && \
//	docker exec -u 0 wp-browser_php_$(PHP_VERSION) bash -c "echo '$${_IP} wordpress.test' >> /etc/hosts"

}

function ensure_service_running( $service, $ensure_dependencies = true ) {
	if ( $ensure_dependencies ) {
		ensure_service_dependencies( $service );
	}

	if ( ! service_running( $service ) ) {
		return tric_realtime()( [ 'up', '-d', $service ] );
	}

	return 0;
}
