<?php
/**
 * Functions for WordPress specific actions.
 */

namespace StellarWP\Slic;

use CallbackFilterIterator;
use FilesystemIterator;
use SplFileInfo;

/**
 * Generates a .htaccess file in the WP root if missing.
 */
function maybe_generate_htaccess() {
	$htaccess_path = root( '_wordpress/.htaccess' );
	$htaccess      = is_file( $htaccess_path ) && file_get_contents( $htaccess_path );

	if ( $htaccess ) {
		return;
	}

	$htaccess = <<< HTACCESS
# BEGIN WordPress

RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]

# END WordPress
HTACCESS;

	file_put_contents( $htaccess_path, $htaccess );
}

/**
 * Indicates whether the current dir has a local-config.php file.
 *
 * @param string $dir Path to search for local-config.
 *
 * @return bool
 */
function dir_has_local_config( $dir ): bool {
	return file_exists( "{$dir}/local-config.php" );
}

/**
 * Indicates whether the current dir has a wp-config.php file.
 *
 * @param string $dir Path to search for wp-config.
 *
 * @return bool
 */
function dir_has_wp_config( $dir ): bool {
	return file_exists( "{$dir}/wp-config.php" );
}

/**
 * Returns a list of the available plugins or themes.
 *
 * @return array<string,SplFileInfo> A map of each directory in the relevant plugins or themes directory to the
 *                                   corresponding file information.
 */
function wp_content_dir_list( $content_type = 'plugins' ): array {
	$function = "\\StellarWP\\Slic\\slic_{$content_type}_dir";
	$path     = $function();

	if ( ! is_dir( $path ) ) {
		return [];
	}

	$dirs    = [];
	$options = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

	$dir = new CallbackFilterIterator( new FilesystemIterator( $path, $options ),
		static function ( SplFileInfo $file ) {
			return $file->isDir();
		}
	);

	$allowed_subdirs = get_allowed_use_subdirectories();
	foreach ( iterator_to_array( $dir ) as $key => $value ) {
		$basename          = basename( $key );
		$dirs[ $basename ] = $value;
		foreach ( $allowed_subdirs as $subdir ) {
			$subdir_path = $value . '/' . $subdir;
			if ( file_exists( $subdir_path ) ) {
				$dirs[ $basename . '/' . $subdir ] = $subdir_path;
			}
		}
	}

	return $dirs;
}

/**
 * Returns the list of allowed subdirectories for slic use.
 *
 * @return array<string> Allowed subdirectories for use.
 */
function get_allowed_use_subdirectories(): array {
	return [ 'common' ];
}

/**
 * Ensures WordPress is correctly installed.
 *
 * @return bool Always `true` to indicate WordPress is
 *              correctly installed.
 */
function ensure_wordpress_installed(): bool {
	if ( slic_realtime()( cli_command( [ 'core', 'is-installed' ] ) ) === 0 ) {
		debug( "WordPress is already installed." . PHP_EOL );

		return true;
	}

	$install = [
		'core',
		'install',
		'--url=http://wordpress.test',
		'--title=Slic',
		'--admin_user=admin',
		'--admin_password=password',
		'--admin_email=admin@wordpress.test',
		'--skip-email',
	];
	if ( slic_realtime()( cli_command( $install ) ) !== 0 ) {
		// There will be debug detailing the issue.
		echo magenta( "Failed to install WordPress." );
		exit( 1 );
	}

	if ( slic_realtime()( cli_command( [ 'core', 'is-installed' ] ) ) !== 0 ) {
		echo magenta( "Failed to check WordPress is installed." );
		exit( 1 );
	}

	debug( "WordPress installed." . PHP_EOL );

	return true;
}

/**
 * Fetch and return WordPress current latest version string.
 *
 * The result is cached in file for a day.
 *
 * @return string The current latest version, or `1.0.0` if the information
 *                could not be retrieved.
 */
function get_wordpress_latest_version(): string {
	static $current_latest_version;

	if ( $current_latest_version !== null ) {
		return $current_latest_version;
	}

	$cache_file = cache( 'wp_latest_version.txt' );

	// Invalidate after a day.
	if ( is_readable( $cache_file ) && ( time() - (int) filectime( $cache_file ) ) < 86400 ) {
		debug( "Reading latest version string from cache file $cache_file." . PHP_EOL );

		$current_latest_version = file_get_contents( $cache_file );

		return $current_latest_version;
	}

	debug( "Fetching latest WordPress version string ..." . PHP_EOL );
	$json = file_get_contents( 'https://api.wordpress.org/core/version-check/1.7/' );

	if ( $json === false ) {
		debug( 'Fetching of WordPress latest version string failed, falling back to 1.0.0.' );

		// We could not tell, return something that will trigger a refresh.
		return '1.0.0';
	}

	$decoded = json_decode( $json, true );

	if ( $decoded !== false && isset( $decoded['offers'][0]['current'] ) ) {
		$current_latest_version = $decoded['offers'][0]['current'];

		debug( "Fetched WordPress latest version: $current_latest_version" . PHP_EOL );

		file_put_contents( $cache_file, $current_latest_version );

		return $current_latest_version;
	}

	debug( "Fetched latest version response malformed, falling back to 1.0.0" . PHP_EOL );

	// We could not tell, return something that will trigger a refresh.
	return '1.0.0';
}

/**
 * Ensure, failing if not possible, that WordPress is correctly set up, configured and installed.
 *
 * @param string|null $version The WordPress version to ensure the readiness of, `latest` if null.
 *
 * @return bool Always `true` to indicate success.
 */
function ensure_wordpress_ready( string $version = null ): bool {
	ensure_services_running( [ 'slic', 'wordpress' ] );
	ensure_wordpress_installed();

	return true;
}
