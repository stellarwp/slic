<?php
/**
 * Functions for WordPress specific actions.
 */

namespace TEC\Tric;

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
function dir_has_local_config( $dir ) {
	return file_exists( "{$dir}/local-config.php" );
}

/**
 * Indicates whether the current dir has a wp-config.php file.
 *
 * @param string $dir Path to search for wp-config.
 *
 * @return bool
 */
function dir_has_wp_config( $dir ) {
	return file_exists( "{$dir}/wp-config.php" );
}

/**
 * Returns a list of the available plugins or themes.
 *
 * @return array<string,SplFileInfo> A map of each directory in the relevant plugins or themes directory to the corresponding file
 *                                   information.
 */
function wp_content_dir_list( $content_type = 'plugins' ) {
	$function = "\\TEC\\Tric\\tric_{$content_type}_dir";
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
 * Returns the list of allowed subdirectories for tric use.
 *
 * @return array<string> Allowed subdirectories for use.
 */
function get_allowed_use_subdirectories() {
	return [ 'common' ];
}

/**
 * Scaffolds the WordPress installation used by tric, if required.
 */
function scaffold_installation() {
	$wp_config_file = tric_wp_dir( '/wp-config.php' );

	if ( is_file( $wp_config_file ) ) {
		return;
	}

	// Spin up the WordPress container NOT binding plugins and themes.
	$stack_array = [ '-f', '"' . stack( '.build' ) . '"' ];
	check_status_or_exit( docker_compose( $stack_array )( [ 'up', '-d', 'wordpress' ] ) );
	$has_time = 30;
	echo 'Scaffolding WordPress file structure ...';
	while ( ! is_file( $wp_config_file ) && $has_time -- ) {
		if ( ! $has_time -- ) {
			echo "\n" . magenta( 'Failed to scaffold WordPress directory structure!' );
			exit( 1 );
		}
		print '.';
		sleep( 1 );
	}
	echo ( light_cyan( ' done' ) ) . "\n";
	check_status_or_exit( docker_compose( $stack_array )( [ 'down' ] ) );
}

/**
 * Installs wordpress, if required.
 */
function install_wordpress() {
	$sense_installation = tric_process()( cli_command( [ 'core', 'is-installed', ], 'site-cli' ) );
	$install_wordpress  = static function () {
		check_status_or_exit( tric_process()( cli_command( [
			'core',
			'install',
			'--path=/var/www/html',
			'--url=http://wordpress.test',
			'--title=Tric',
			'--admin_user=admin',
			'--admin_password=admin',
			'--admin_email=admin@wordpress.test',
			'--skip-email',
		], 'site-cli' ) ) );
	};
	check_status_or( $sense_installation, $install_wordpress );
}

function ensure_wordpress_files() {
	$wp_root_dir = getenv( 'TRIC_WP_DIR' );

	// Ensure the destination directory exists.
	if ( ! is_dir( $wp_root_dir ) && ! mkdir( $wp_root_dir, 0755, false ) && ! is_dir( $wp_root_dir ) ) {
		echo magenta( "Failed to create WordPress root directory {$wp_root_dir}." );
		exit( 1 );
	}

	// Download WordPress.
	$zip_file = cache( '/wordpress/wordpress.zip' );
	if ( ! is_file( $zip_file ) ) {
		$source_url = 'https://wordpress.org/latest.zip';
		$zip_file   = download_file( $source_url, $zip_file );

		if ( $zip_file === false ) {
			echo magenta( "Failed to download WordPress file from $source_url." );
			exit( 1 );
		}
	}

	// Unzip WordPress.
	if ( ! is_file( $wp_root_dir . '/wp-load.php' ) ) {
		if ( ! unzip_file( $zip_file, $wp_root_dir ) ) {
			echo magenta( "Failed to extract WordPress file $zip_file to $wp_root_dir." );
		}
		exit( 1 );
	}

	return true;
}

function ensure_wordpress_configured() {

}

function ensure_wordpress_installed() {
}
