<?php
/**
 * Functions to scaffold plugins for use.
 */

namespace StellarWP\Slic;

/**
 * Creates an `.env.testing.slic` file, if one exists it will be overwritten.
 *
 * @param string $plugin_path The plugin path.
 *
 * @return bool Whether or not the .env.testing.slic was created.
 */
function write_slic_env_file( $plugin_path ) {
	$mysql_root_password = getenv( 'MYSQL_ROOT_PASSWORD' );

	$plugin_env          = '';
	$candidate_env_files = [
		'.env.testing',
		'.env',
		'.env.dist'
	];
	foreach ( $candidate_env_files as $candidate ) {
		$candidate_full_path = rtrim( $plugin_path, '\\/' ) . '/' . ltrim( $candidate, '\\/' );
		if ( ! file_exists( $candidate_full_path ) ) {
			continue;
		}
		$plugin_env = (string) file_get_contents( $candidate_full_path );
		break;
	}

	$wp_domain = 'wordpress.test';

	$replace = [];

	$replace['wp_root_folder'] = [
		'env' => [
			'WP_ROOT_FOLDER',
		],
		'value' => '/var/www/html',
	];

	$replace['wp_url'] = [
		'env' => [
			'WP_URL',
			'TEST_SITE_WP_URL',
			'WP_CHROMEDRIVER_URL',
		],
		'value' => 'http://' . $wp_domain,
	];

	$replace['wp_domain'] = [
		'env' => [
			'WP_DOMAIN',
			'TEST_SITE_WP_DOMAIN',
		],
		'value' => $wp_domain,
	];

	$replace['wp_db_port'] = [
		'env' => [
			'DB_PORT',
			'WP_DB_PORT',
			'TEST_DB_PORT',
			'TEST_SITE_DB_PORT',
		],
		'value' => 3306,
	];

	$replace['wp_db_host'] = [
		'env' => [
			'DB_HOST',
			'WP_DB_HOST',
			'WP_TEST_DB_HOST',
			'TEST_DB_HOST',
			'TEST_SITE_DB_HOST',
		],
		'value' => 'db',
	];

	$replace['wp_db_name'] = [
		'env' => [
			'DB_NAME',
			'WP_DB_NAME',
			'WP_TEST_DB_NAME',
			'TEST_DB_NAME',
			'TEST_SITE_DB_NAME',
		],
		'value' => 'test',
	];

	$replace['wp_db_password'] = [
		'env' => [
			'DB_PASSWORD',
			'WP_DB_PASSWORD',
			'WP_TEST_DB_PASSWORD',
			'TEST_DB_PASSWORD',
			'TEST_SITE_DB_PASSWORD',
		],
		'value' => $mysql_root_password,
	];

	$replace['wp_table_prefix'] = [
		'env' => [
			'TEST_TABLE_PREFIX',
			'WP_TABLE_PREFIX',
			'WORDPRESS_TABLE_PREFIX',
			'TEST_SITE_TABLE_PREFIX',
		],
		'value' => 'wp_',
	];

	$replace['chromedriver_host'] = [
		'env' => [
			'CHROMEDRIVER_HOST',
		],
		'value' => 'chrome',
	];

	foreach ( $replace as $env_strings ) {
		foreach ( $env_strings['env'] as $find ) {
			$plugin_env = preg_replace( "/{$find}=.*/", "{$find}={$env_strings['value']}", $plugin_env );
		}
	}

	if ( false === strpos( $plugin_env, 'USING_CONTAINERS=' ) ) {
		$plugin_env .= "\n# We're using Docker to run the tests.\nUSING_CONTAINERS=1\n";
	} else {
		// In `slic`, we're most definitely using containers.
		$plugin_env = str_replace( 'USING_CONTAINERS=0', 'USING_CONTAINERS=1', $plugin_env );
	}

	$file = $plugin_path . '/.env.testing.slic';
	$put =  file_put_contents( $file, $plugin_env );

	if ( false === $put ) {
		echo magenta( "Could not write {$file}; please check the directory exists and is writeable.\n" );
		exit( 1 );
	}
}

/**
 * Returns the lines that should be written to a `tests-config.php` file for slic to work correctly.
 *
 * @param array<string,string> $overrides A map of lines to write, where the key is the type of entry and the value are
 *                                        the lines to write for that entry.
 *                                        E.g. `[ 'define_plugins_dir' => "define( 'WP_PLUGIN_DIR', '/plugins' );" ]`.
 *
 * @return array<string,string> A map of the lines to write.
 */
function get_slic_test_config_lines( array $overrides = [] ) {
	$defaults = [];

	return array_merge( $defaults, $overrides );
}

/**
 * Creates a `test_config.slic.php` file, if one exists it will be overwritten.
 *
 * The function will not write anything if there are no test config lines to write.
 *
 * @param string               $plugin_path  The plugin path.
 * @param array<string,string> $config_lines A map of lines to write, where the key is the type of entry and the value
 *                                           are the lines to write for that entry.
 *                                           E.g. `[ 'define_plugins_dir' => "define( 'WP_PLUGIN_DIR', '/plugins' );" ]`.
 *
 * @return bool Whether or not the test-config.php was created.
 */
function write_slic_test_config( $plugin_path, array $config_lines = [] ) {
	$file = $plugin_path . '/test-config.slic.php';

	$test_config_lines = get_slic_test_config_lines( $config_lines );

	if ( empty( $test_config_lines ) ) {
		// There's no need for a slic test config file, let's skip this.
		return false;
	}

	$put = file_put_contents( $file, "<?php\n" . implode( "\n", $test_config_lines ) );

	if ( false === $put ) {
		echo magenta( "Could not write {$file}; please check the directory exists and is writeable." . PHP_EOL );
		exit( 1 );
	}

	return true;
}

/**
 * Creates a `codeception.slic.yml` file if needed.
 *
 * The file is the one slic will load, using the `-c` or `--configuration` option, on top of the usual Codeception
 * configuration files.
 * This function will override the existing file by design: users should be able to change some values, or update slic,
 * and have that reflected in a new configuration file.
 *
 * @param string $plugin_path The plugin path.
 *
 * @see The run command for more information.
 *
 */
function write_codeception_config( $plugin_path ) {
	$file = $plugin_path . '/codeception.slic.yml';

	$codeception = <<< CONFIG
params:
  # read dynamic configuration parameters from the .env file
  - .env.testing.slic
CONFIG;

	$test_config_lines = get_slic_test_config_lines();

	if ( ! empty( $test_config_lines ) ) {
		// Add a section for a custom test configuration file only if required.
		$wploader_test_config = <<< WPLOADER_TEST_CONFIG
modules:
  config:
    WPLoader:
      configFile: test-config.slic.php
WPLOADER_TEST_CONFIG;

		$codeception .= $wploader_test_config;
	}

	$put = file_put_contents( $file, $codeception );

	if ( false === $put ) {
		echo magenta( "Could not write {$file}; please check the directory exists and is writeable." . PHP_EOL );
		exit( 1 );
	}
}
