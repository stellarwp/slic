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
 * @param string $service The service to check dependencies for.
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
