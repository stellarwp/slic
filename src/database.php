<?php

namespace TEC\Tric;


/**
 * Provides functions to manage and interact with the database services of the stack.
 */

/**
 * Starts the database service, if required, to ensure it's running.
 *
 * @return bool Always `true` when successful.
 */
function ensure_db_service_running() {
	// Start the service is not already started.
	$status = tric_passive()( [ 'up', '--detach', 'db' ] );

	if ( $status !== 0 ) {
		echo magenta( 'Failed to start or restart the database service; check the output for errors.' );
		exit( 1 );
	}

	return true;
}

/**
 * Returns the table prefix used in the default installation tables.
 *
 * @return string The table prefix used in the default installation tables.
 */
function get_table_prefix() {
	return 'wp_';
}

/**
 * Returns the list of the default prefixed table names that should be
 * in a healthy installation.
 *
 * @return array<string> The list of prefixed tables names that should be
 *                       found in a healthy WordPress installation.
 */
function get_default_tables_list() {
	$prefix = get_table_prefix();

	return array_map( static function ( $table_name ) use ( $prefix ) {
		return $prefix . $table_name;
	}, [
		'commentmeta',
		'comments',
		'links',
		'options',
		'postmeta',
		'posts',
		'term_relationships',
		'term_taxonomy',
		'termmeta',
		'terms',
		'usermeta',
		'users',
	] );
}

/**
 * Returns the value of the database host and port that should
 * be used to contact the database from outside the containers'
 * stack.
 *
 * @return string The database host an port in `host:port` format.
 */
function get_localhost_db_host() {
	return '127.0.0.1:' . getenv( 'TRIC_DB_LOCALHOST_PORT' );
}

/**
 * Returns an open mysqli connection handle opened accessing the
 * database from outside the containers' stack.
 *
 * @return false|\mysqli|null The database connection handle on success.
 */
function get_localhost_db_handle() {
	setup_tric_env( root() );

	return mysqli_connect(
		'127.0.0.1',
		get_db_user(),
		get_db_password(),
		get_db_name(),
		getenv( 'TRIC_DB_LOCALHOST_PORT' )
	);
}

/**
 * Returns the name of the database..
 *
 * @return string The name of the default database.
 */
function get_db_name() {
	return 'test';
}

/**
 * Returns the database user.
 *
 * @return string The database user.
 */
function get_db_user() {
	return 'root';
}

/**
 * Returns the database password.
 *
 * @return string The database password.
 */
function get_db_password() {
	return 'password';
}
