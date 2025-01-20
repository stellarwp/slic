<?php
/**
 * Provides functions to fetch information and interact with the current project.
 */

namespace StellarWP\Slic;

function get_project_type() {
	$plugins_dir = realpath( getenv( 'SLIC_PLUGINS_DIR' ) );
	$themes_dir  = realpath( getenv( 'SLIC_THEMES_DIR' ) );
	$here_dir    = realpath( getenv( 'SLIC_HERE_DIR' ) );
	$is_plugin   = $here_dir === $plugins_dir;
	$is_theme    = $here_dir === $themes_dir;
	if ( ! ( $is_plugin || $is_theme ) ) {
		return 'site';
	}

	return $is_theme ? 'theme' : 'plugin';
}

function get_project_local_path() {
	switch ( get_project_type() ) {
		case 'plugin':
			return realpath( getenv( 'SLIC_PLUGINS_DIR' ) ) . DIRECTORY_SEPARATOR . slic_target();
		case 'theme':
			return realpath( getenv( 'SLIC_THEMES_DIR' ) ) . DIRECTORY_SEPARATOR . slic_target();
		default:
			return realpath( getenv( 'SLIC_HERE_DIR' ) );
	}
}

function get_project_container_path() {
	switch ( get_project_type() ) {
		case 'plugin':
			return '/var/www/html/wp-content/plugins' . '/' . slic_target();
		case 'theme':
			return '/var/www/html/wp-content/themes' . '/' . slic_target();
		default:
			return '/var/www/html';
	}
}

/**
 * Returns the .slicrc file as an array.
 *
 * @param string $project_root_path The path to the project root.
 *
 * @return array<string,mixed> The .slicrc file as an array.
 */
function project_get_slic_json( $project_root_path ) {
	if ( ! $project_root_path ) {
		return [];
	}

	if ( ! file_exists( $project_root_path . '/slic.json' ) ) {
		return [];
	}

	$slic_json = file_get_contents( $project_root_path . '/slic.json' );
	$slic_json = json_decode( $slic_json, true, 512, JSON_THROW_ON_ERROR );

	return $slic_json;
}

/**
 * Applies the .slicrc and/or composer.json file to the current environment.
 *
 * @param string $project_root_path The path to the project root.
 */
function project_apply_config( $project_root_path ) {
	if ( ! $project_root_path ) {
		return;
	}

	$has_error = false;

	$slic_env_local = '';
	if ( file_exists( $project_root_path . '/.env.slic.local' ) ) {
		$slic_env_local = file_get_contents( $project_root_path . '/.env.slic.local' );
	}

	try {
		$slic_json = project_get_slic_json( $project_root_path );
	} catch ( \Exception $e ) {
		$has_error = true;
		echo colorize( PHP_EOL .
			sprintf(
				"❌ <red>Error parsing slic.json file in %s: %s</red>",
				$project_root_path,
				$e->getMessage()
			) . PHP_EOL );
	}

	try {
		$composer_json = project_get_composer( $project_root_path );
	} catch ( \Exception $e ) {
		$has_error = true;
		echo colorize( PHP_EOL .
			sprintf(
				"❌ <red>Error parsing composer.json file in %s: %s</red>",
				$project_root_path,
				$e->getMessage()
			) . PHP_EOL );
	}

	if ( $has_error ) {
		echo colorize( PHP_EOL .
			sprintf(
				"<red>Could not properly read your project's PHP requirements. Resolve the errors and try again.</red>",
				$project_root_path
			) . PHP_EOL );
		return;
	}

	project_apply_php_version( $slic_env_local, $slic_json, $composer_json );
}

/**
 * Returns the composer.json file as an array.
 *
 * @param string $project_root_path The path to the project root.
 *
 * @return array<string,mixed> The composer.json file as an array.
 */
function project_get_composer( $project_root_path ) {
	if ( ! file_exists( $project_root_path . '/composer.json' ) ) {
		return null;
	}

	$composer_json = file_get_contents( $project_root_path . '/composer.json' );
	$composer_json = json_decode( $composer_json, true, 512, JSON_THROW_ON_ERROR );

	return $composer_json;
}

/**
 * Returns a slic-usable PHP version from the composer.json file.
 *
 * The PHP version is grabbed from config.platform.php if present and converted to a x.y format
 * if it is not already. If the PHP version is less than 7.4, then null is returned.
 *
 * @param array<string,mixed> $composer_json The composer.json file as an array.
 *
 * @return string|null The PHP version specified in the composer.json file or null if not found.
 */
function project_get_composer_php_version( $composer_json ) {
	if ( ! $composer_json ) {
		return null;
	}

	if ( empty( $composer_json['config']['platform']['php'] ) ) {
		return null;
	}

	$php_version = $composer_json['config']['platform']['php'];
	$php_version = preg_replace( '/^(\d\.\d)\..+/', '$1', $php_version );
	if ( strpos( $php_version, '.' ) === false ) {
		$php_version .= '.0';
	}

	if ( version_compare( $php_version, '7.4', '<' ) ) {
		return null;
	}

	return $php_version;
}

/**
 * Applies the PHP version specified in the .slicrc or composer.json file to the current environment.
 *
 * @param string $slic_env_local The .env.slic.local file as a string.
 * @param array<string,mixed> $slic_json The .slic.json file as an array.
 * @param array<string,mixed> $composer_json The composer.json file as an array.
 */
function project_apply_php_version( $slic_env_local, $slic_json, $composer_json ) {
	$current_php_version = getenv( 'SLIC_PHP_VERSION' );
	$project_php_version = $slic_json['phpVersion'] ?? project_get_composer_php_version( $composer_json );

	$slic_env_php_version = $project_php_version;

	if (
		$slic_env_local
		&& preg_match( '/SLIC_PHP_VERSION=([^\n]+)/m', $slic_env_local, $matches )
	) {
		$slic_env_php_version = trim( $matches[1] );
	}

	if ( $project_php_version && $project_php_version !== $current_php_version ) {
		slic_set_php_version( $project_php_version, false );
	}

	if ( $slic_env_php_version !== $project_php_version ) {
		echo colorize(
			PHP_EOL .
			"<red>Your .env.slic.local file's PHP version is different than either your slic.json or composer.json file. This will cause your project to rebuild the PHP containers any type you `slic use`.</red>" .
			PHP_EOL
		);
	}
}
