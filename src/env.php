<?php
/**
 * Provides function to interact with environment variables.
 */

namespace TEC\Tric\Env;

/**
 * Returns the current backup vault.
 *
 * @return array<string,int|string> A map from backed up environment variables to their values.
 */
function backup_vault() {
	static $vault;

	return $vault;
}

/**
 * Backups an environment current value.
 *
 * @param string $key The name of the environment value to backup.
 *
 * @return void The method does not return any value and will backup the env var current value.
 */
function backup_env_var( $key ) {
	backup_vault()[ $key ] = getenv( $key );
}

/**
 * Returns the backed up value of an environment variable
 *
 * @param string $key     The name of the environment value to return the backed up value for.
 * @param mixed  $default The value to return should the environment variable not have a backup
 *                        or have an empty backup value.
 *
 * @return int|string|null The backed up environment variable value, or the default one.
 */
function env_var_backup( $key, $default = null ) {
	$vault = backup_vault();

	return isset( $vault[ $key ] ) ? $vault[ $key ] : $default;
}
