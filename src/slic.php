<?php
/**
 * slic cli functions.
 */

namespace StellarWP\Slic;

use function StellarWP\Slic\Env\backup_env_var;
use function StellarWP\Slic\Env\env_var_backup;

/**
 * Get the CLI header.
 *
 * @param string $cli_name CLI command name.
 * @param boolean $full Should the full heading be returned?
 * @param string|null $extra Extra message to add to the header.
 * @return string
 */
function cli_header( $cli_name, $full = false, $extra = null ) {
	$header_parts = [
		light_cyan( $cli_name ) . ' version ' . light_cyan( CLI_VERSION ),
		$full ? PHP_EOL : ' - ',
		'StellarWP local testing and development tool',
		PHP_EOL,
	];

	if ( ! $full ) {
		return implode( '', $header_parts ) . PHP_EOL;
	}

	$header_parts[0] = '                     ' . $header_parts[0];
	$header_parts[2] = '        ' . $header_parts[2];

	$message_start = <<< MESSAGE
	******************************************************************

	                                                 _.oo.
	                         <light_cyan>_.u[[/;:,.</light_cyan>         .odMMMMMM'
	                      <light_cyan>.o888UU[[[/;:-.</light_cyan>  .o@P^    MMM^
	                     <light_cyan>oN88888UU[[[/;::-.</light_cyan>        dP^
	                    <light_cyan>dNMMNN888UU[[[/;:--.</light_cyan>   .o@P^
	                   <light_cyan>,MMMMMMN888UU[[/;::-.</light_cyan> o@^
	                   <light_cyan>NNMMMNN888UU[[[/~.</light_cyan>o@P^
	                   <light_cyan>888888888UU[[[</light_cyan>/o@^<light_cyan>-..</light_cyan>
	                  o<light_cyan>I8888UU[[[</light_cyan>/o@P^<light_cyan>:--..</light_cyan>
	               .@^  <light_cyan>YUU[[[</light_cyan>/o@^<light_cyan>;::---..</light_cyan>
	             oMP     <light_cyan>^</light_cyan>/o@P^<light_cyan>;:::---..</light_cyan>
	          .dMMM    .o@^ ^<light_cyan>;::---...</light_cyan>
	         dMMMMMMM@^`       <light_cyan>`^^^^</light_cyan>
	        YMMMUP^
	         ^^

	MESSAGE;

	if ( $extra ) {
		$message_start = str_replace( 'light_cyan', 'red', $message_start );
	}

	$message_start .= implode( '', $header_parts );

	if ( $extra ) {
		$message_start .= PHP_EOL . $extra . PHP_EOL;
	}

	$message_end = <<< MESSAGE

	******************************************************************
	MESSAGE;

	return colorize( $message_start . $message_end . PHP_EOL . PHP_EOL );
}

/**
 * Returns whether or not the slic here command was done at the site level or not.
 *
 * @return bool
 */
function slic_here_is_site() {
	$env_wp_dir = getenv( 'SLIC_WP_DIR' );

	return SLIC_ROOT_DIR . '/_wordpress' !== $env_wp_dir
	       && './_wordpress' !== $env_wp_dir;
}

/**
 * Get the current directory name without any slashes or path.
 *
 * @return string Name of the current working directory. Empty string if not a readable directory or other error.
 */
function get_cwd_dir_name() {
	$cwd = getcwd();

	if (
		is_string( $cwd )
		&& is_dir( $cwd )
	) {
		return basename( $cwd );
	}

	return '';
}

/**
 * Gets all valid targets.
 *
 * Valid targets are:
 *   - Anything in the plugins directory.
 *   - If slic here was done on the site level, "site" is also a valid target.
 *
 * @param bool $as_array Whether to output as an array. If falsy, will output as a formatted string, including
 *                       headings, line breaks, and indentation.
 *
 * @return array|string
 */
function get_valid_targets( $as_array = true ) {
	$targets_str = '';

	$plugins = array_keys( dev_plugins() );
	sort( $plugins, SORT_NATURAL );

	$themes = array_keys( dev_themes() );
	sort( $themes, SORT_NATURAL );

	$targets = $plugins;

	if ( slic_here_is_site() ) {
		$targets     = array_merge( [ 'site' ], $plugins, $themes );
		$targets_str .= PHP_EOL . '  Site:' . PHP_EOL;
		$targets_str .= '    - site';
	}

	$targets_str .= PHP_EOL . "  Plugins:" . PHP_EOL;
	$targets_str .= implode(
		PHP_EOL, array_map(
			static function ( $target ) {
				return "    - {$target}";
			}, $plugins
		)
	);

	if ( slic_here_is_site() && $themes ) {
		$targets_str .= PHP_EOL . "  Themes:" . PHP_EOL;
		$targets_str .= implode(
			PHP_EOL, array_map(
				static function ( $target ) {
					return "    - {$target}";
				}, $themes
			)
		);
	}

	if ( empty( $as_array ) ) {
		return $targets_str;
	}

	return $targets;
}

/**
 * Checks a specified target is supported as a target.
 *
 * Valid targets are:
 *   - Anything in the plugins directory.
 *   - If slic here was done on the site level, "site" is also a valid target.
 *
 * @param string $target The target to check in the valid list of targets.
 * @param bool $exit Whether to exit if the target is invalid, or to return `false`.
 *
 * @return string|false $target The validated target or `false` to indicate the target is not valid if the `$exit`
 *                              parameter is set to `false`.
 */
function ensure_valid_target( $target, $exit = true ) {
	$targets = get_valid_targets();

	$targets_str = get_valid_targets( false );

	if ( empty( $target ) ) {
		$target = get_cwd_dir_name();

		if ( ! in_array( $target, $targets, true ) ) {
			echo magenta( "Detecting the current directory of '{$target}' as the target was not valid." . PHP_EOL . "Available targets are: " . PHP_EOL . "{$targets_str}" . PHP_EOL );
			if ( $exit ) {
				exit( 1 );
			}

			return false;
		}
	}

	if ( ! in_array( $target, $targets, true ) ) {
		echo magenta( "'{$target}' is not a valid target; available targets are:" . PHP_EOL . "{$targets_str}" . PHP_EOL );
		if ( $exit ) {
			exit( 1 );
		}

		return false;
	}

	return $target;
}

/**
 * Get the container relative path to the provided target.
 *
 * @param string $target Target with which to build the relative path from.
 *
 * @return string
 */
function get_target_relative_path( $target ) {
	if ( 'site' === $target ) {
		return '';
	}

	$plugin_dir = getenv( 'SLIC_PLUGINS_DIR' );
	$theme_dir  = getenv( 'SLIC_THEMES_DIR' );

	if ( file_exists( "{$plugin_dir}/{$target}" ) ) {
		$parent_path = $plugin_dir;
	} elseif ( file_exists( "{$theme_dir}/{$target}" ) ) {
		$parent_path = $theme_dir;
	} else {
		echo magenta( "Unable to locate a path to the desired target ({$target}). Searched in: " . PHP_EOL . "- {$plugin_dir}" . PHP_EOL . "- {$theme_dir}" );
		exit( 1 );
	}

	$parent_path = str_replace( getenv( 'SLIC_HERE_DIR' ) . '/', '', $parent_path );

	return "{$parent_path}/{$target}";
}

/**
 * Sets up the environment from the cli tool.
 *
 * @param string $root_dir The cli tool root directory.
 * @param bool $reset Whether to force a reset of the env vars or not, if already set up.
 */
function setup_slic_env( $root_dir, $reset = false ) {
	static $set;

	if ( ! $reset && $set === true ) {
		return;
	}

	$set = true;

	// Let's declare we're performing slics.
	putenv( 'STELLAR_SLIC=1' );
	// Backwards compat
	putenv( 'TRIBE_TRIC=1' );

	putenv( 'SLIC_VERSION=' . CLI_VERSION );

	setup_architecture_env();

	backup_env_var( 'COMPOSER_CACHE_DIR' );

	// Load the distribution version configuration file, the version-controlled one.
	load_env_file( $root_dir . '/.env.slic' );

	// Load the local overrides, this file is not version controlled.
	if ( file_exists( $root_dir . '/.env.slic.local' ) ) {
		load_env_file( $root_dir . '/.env.slic.local' );
	}

	// Load the current session configuration file.
	if ( file_exists( $root_dir . '/.env.slic.run' ) ) {
		load_env_file( $root_dir . '/.env.slic.run' );
	}

	/*
	 * Set the host env var to make xdebug work on Linux with host.docker.internal.
	 * This will already be set on Mac/Windows, and overriding it would break things.
	 * See extra_hosts: in slick-stack.yml.
	 */
	if ( PHP_OS === 'Linux' ) {
		putenv( sprintf( 'host=%s', getenv( 'XDH' ) ?: 'host.docker.internal' ) );
	}

	$default_wp_dir = root( '/_wordpress' );
	$wp_dir         = getenv( 'SLIC_WP_DIR' );

	if ( $wp_dir === './_wordpress' || $wp_dir === $default_wp_dir ) {
		// Default WordPress directory, inside slic.
		$wp_dir = ensure_dir( $default_wp_dir );
	} else if ( ! is_dir( $wp_dir ) ) {
		// Custom WordPress directory, it falls on the user to have it set up correctly.
		echo magenta( "WordPress directory $wp_dir does not exist; is it initialized?" );
		exit( 1 );
	}

	$wp_themes_dir = $wp_dir . '/wp-content/themes';

	putenv( 'SLIC_WP_DIR=' . $wp_dir );
	putenv( 'SLIC_PLUGINS_DIR=' . ensure_dir( getenv( 'SLIC_PLUGINS_DIR' ) ?: root( '_plugins' ) ) );
	putenv( 'SLIC_THEMES_DIR=' . ensure_dir( getenv( 'SLIC_THEMES_DIR' ) ?: $wp_themes_dir ) );
	putenv( 'SLIC_CACHE=' . cache() );

	if ( empty( getenv( 'COMPOSER_CACHE_DIR' ) ) ) {
		ensure_dir( root( '.cache' ) );
		putenv( 'COMPOSER_CACHE_DIR=' . cache( '/composer' ) );
	}

	// Most commands are nested shells that should not run with a time limit.
	remove_time_limit();
}

/**
 * Returns the current `use` target.
 *
 * @param bool $require Whether to require a target, and fail if not set, or not.
 *
 * @return string|string Either the current target or `false` if the target is not set. If `$require` is `true` then the
 *                       return value will always be a non empty string.
 */
function slic_target( $require = true ) {
	$using        = getenv( 'SLIC_CURRENT_PROJECT' );
	$using_subdir = getenv( 'SLIC_CURRENT_PROJECT_SUBDIR' );
	$using_full   = $using . ( $using_subdir ? '/' . $using_subdir : '' );

	if ( $require ) {
		return $using_full;
	}

	if ( empty( $using_full ) ) {
		echo magenta( "Use target not set; use the 'use' sub-command to set it." . PHP_EOL );
		exit( 1 );
	}

	return trim( $using_full );
}

/**
 * Switches the current `use` target.
 *
 * @param string $target Target to switch to.
 */
function slic_switch_target( $target ) {
	$root                 = root();
	$run_settings_file    = "{$root}/.env.slic.run";
	$target_relative_path = '';
	$subdir               = '';

	if ( slic_here_is_site() ) {
		$target_relative_path = get_target_relative_path( $target );
	}

	if ( false !== strpos( $target, '/' ) ) {
		list( $target, $subdir ) = explode( '/', $target );
	}

	$env_values = [
		'SLIC_CURRENT_PROJECT'               => $target,
		'SLIC_CURRENT_PROJECT_RELATIVE_PATH' => $target_relative_path,
		'SLIC_CURRENT_PROJECT_SUBDIR'        => $subdir,
	];

	write_env_file( $run_settings_file, $env_values, true );

	setup_slic_env( $root );
}

/**
 * Returns a map of the stack PHP services that relates the service to its pretty name.
 *
 * @return array<string,string> A map of the stack PHP services relating each service to its pretty name.
 */
function php_services() {
	return [
		'slic'        => 'slic',
		'wordpress'   => 'WordPress',
	];
}

/**
 * Restart the stack PHP services.
 *
 * @param bool $hard Whether to restart the PHP services using the `docker compose restart` command or by using a
 *                   tear-down and up again cycle.
 */
function restart_php_services( $hard = false ) {
	foreach ( php_services() as $service => $pretty_name ) {
		restart_service( $service, $pretty_name, $hard );
	}
}

/**
 * Restarts a stack services if it's running.
 *
 * @param string $service The name of the service to restart, e.g. `wordpress`.
 * @param string|null $pretty_name The pretty name to use for the service, or `null` to use the service name.
 * @param bool $hard Whether to restart the service using the `docker compose restart` command or to use full tear-down
 *                   and up again cycle.
 */
function restart_service( $service, $pretty_name = null, $hard = false ) {
	$pretty_name   = $pretty_name ?: $service;
	$slic          = docker_compose( slic_stack_array() );
	$slic_realtime = docker_compose_realtime( slic_stack_array() );

	$service_running = $slic( [ 'ps', '-q', $service ] )( 'string_output' );
	if ( ! empty( $service_running ) ) {
		echo colorize( PHP_EOL . "Restarting {$pretty_name} service..." . PHP_EOL );
		if ( $hard ) {
			$slic_realtime( [ 'rm', '--stop', '--force', $service ] );
			$slic_realtime( [ 'up', '-d', $service ] );
		} else {
			$slic_realtime( [ 'restart', $service ] );
		}
		echo colorize( PHP_EOL . "✅ <light_cyan>{$pretty_name} service restarted.</light_cyan>" . PHP_EOL );
	} else {
		echo colorize( PHP_EOL . "{$pretty_name} service was not running. Starting it." . PHP_EOL );
		$exit_status = ensure_service_running( $service );
		if ( $exit_status === 0 ) {
			echo colorize( "✅ <light_cyan>{$pretty_name} service started.</light_cyan>" . PHP_EOL );
		} else {
			echo colorize( "❌ <red>{$pretty_name} service could not be started.</red>" . PHP_EOL );
		}
	}
}

/**
 * Restarts all services in the stack.
 */
function restart_all_services() {
	command_stop();
	start_all_services();
}

/**
 * Starts all services in the stack.
 */
function start_all_services() {
	$services = get_services();
	foreach ( $services as $service ) {
		ensure_service_running( $service );
	}
}

/**
 * Returns the absolute path to the current plugins directory slic is using.
 *
 * @param string $path An optional path to append to the current slic plugin directory.
 *
 * @return string The absolute path to the current plugins directory slic is using.
 *
 */
function slic_plugins_dir( $path = '' ) {
	return slic_content_type_dir( 'plugins', $path );
}

/**
 * Returns the absolute path to the current plugins directory slic is using.
 *
 * @param string $path An optional path to append to the current slic plugin directory.
 *
 * @return string The absolute path to the current plugins directory slic is using.
 *
 */
function slic_themes_dir( $path = '' ) {
	return slic_content_type_dir( 'themes', $path );
}

/**
 * Returns the absolute path to the current content directory slic is using.
 *
 * @param string $path An optional path to append to the current slic content directory.
 *
 * @return string The absolute path to the current content directory slic is using.
 *
 */
function slic_content_type_dir( $content_type = 'plugins', $path = '' ) {
	$content_type_dir = getenv( 'SLIC_' . strtoupper( $content_type ) . '_DIR' );
	$root_dir         = root();

	if ( 'plugins' === $content_type ) {
		$default_path = '/_plugins';
	} elseif ( 'themes' === $content_type ) {
		$default_path = '/_wordpress/wp-content/themes';
	}

	if ( empty( $content_type_dir ) ) {
		// Use the default directory in slic repository.
		$dir = $root_dir . $default_path;
	} elseif ( is_dir( $content_type_dir ) ) {
		// Use the specified directory.
		$dir = $content_type_dir;
	} else {
		if ( 0 === strpos( $content_type_dir, '.' ) ) {
			// Resolve the './...' paths a relative to the root directory in slic repository.
			$dir = preg_replace( '/^\\./', $root_dir, $content_type_dir );
		} else {
			// Use a directory relative to the root directory in slic reopository.
			$dir = $root_dir . '/' . ltrim( $content_type_dir, '\\/' );
		}
	}

	return empty( $path ) ? $dir : $dir . '/' . ltrim( $path, '\\/' );
}

/**
 * Clones a company plugin in the current plugin root directory.
 *
 * @param string $plugin The plugin name, e.g. `the-events-calendar` or `event-tickets`.
 * @param string $branch The specific branch to clone. If not specified, then the default plugin repository branch
 *                       will be cloned.
 */
function clone_plugin( $plugin, $branch = null ) {
	$plugin_dir  = slic_plugins_dir();
	$plugin_path = slic_plugins_dir( $plugin );

	if ( ! file_exists( $plugin_dir ) ) {
		echo "Creating the plugins directory..." . PHP_EOL;
		if ( ! mkdir( $plugin_dir ) && ! is_dir( $plugin_dir ) ) {
			echo magenta( "Could not create {$plugin_dir} directory; please check the parent directory is writeable." );
			exit( 1 );
		}
	}

	// If the plugin path already exists, don't bother cloning.
	if ( file_exists( $plugin_path ) ) {
		return;
	}

	echo "Cloning {$plugin}..." . PHP_EOL;

	$repository = git_handle() . '/' . escapeshellcmd( $plugin );

	$clone_command = sprintf(
		'git clone %s --recursive git@%s:%s.git %s',
		null !== $branch ? '-b "' . $branch . '"' : '',
		git_domain(),
		$repository,
		escapeshellcmd( $plugin_path )
	);

	$clone_status = process_realtime( $clone_command );

	if ( 0 !== $clone_status ) {
		echo magenta( "Could not clone the {$repository} repository; please check your access rights to the repository." );
		exit( 1 );
	}
}

/**
 * Sets up the files required to run tests in the plugin using slic stack.
 *
 * @param string $plugin The plugin name, e.g. 'the-events-calendar` or `event-tickets`.
 */
function setup_plugin_tests( $plugin ) {
	$plugin_path    = slic_plugins_dir() . '/' . $plugin;
	$relative_paths = [ '' ];

	if ( file_exists( "{$plugin_path}/common" ) ) {
		$relative_paths[] = 'common';
	}

	foreach ( $relative_paths as $relative_path ) {
		$target_path   = "{$plugin_path}/{$relative_path}";
		$relative_path = empty( $relative_path ) ? '' : "{$relative_path}/";

		if ( write_slic_test_config( $target_path ) ) {
			echo colorize( "Created/updated <light_cyan>{$relative_path}test-config.slic.php</light_cyan> " .
			               "in {$plugin}." . PHP_EOL );
		}

		write_slic_env_file( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}.env.testing.slic</light_cyan> " .
		               "in <light_cyan>{$plugin}</light_cyan>." . PHP_EOL );


		write_codeception_config( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}codeception.slic.yml</light_cyan> in " .
		               "<light_cyan>{$plugin}</light_cyan>." . PHP_EOL );
	}
}

/**
 * Returns the git domain from which to clone git plugins.
 *
 * Configured using the `SLIC_GIT_DOMAIN` env variable.
 * Examples: gitlab.com, bitbucket.org, git.example.com
 *
 * @return string The git domain from which to clone plugins.
 */
function git_domain() {
	$domain = getenv( 'SLIC_GIT_DOMAIN' );

	return ! empty( $domain ) ? trim( $domain ) : 'github.com';
}

/**
 * Returns the handle (username) of the company from which to clone git plugins.
 *
 * Configured using the `SLIC_GIT_HANDLE` env variable.
 *
 * @return string The git handle from which to clone plugins.
 */
function git_handle() {
	$handle = getenv( 'SLIC_GIT_HANDLE' );

	return ! empty( $handle ) ? trim( $handle ) : 'the-events-calendar';
}

/**
 * Runs a process in passive mode in slic stack and returns the exit status.
 *
 * This approach is used when running commands that can be done in parallel or forked processes.
 *
 * @return \Closure The process closure to start a real-time process using slic stack.
 */
function slic_passive() {
	return docker_compose_passive( slic_stack_array() );
}

/**
 * Runs a process in slic stack and returns the exit status.
 *
 * @return \Closure The process closure to start a real-time process using slic stack.
 */
function slic_realtime() {
	return docker_compose_realtime( slic_stack_array() );
}

/**
 * Returns the process Closure to start a real-time process using slic stack.
 *
 * @return \Closure The process closure to start a real-time process using slic stack.
 */
function slic_process() {
	return docker_compose( slic_stack_array() );
}

/**
 * Tears down slic stack.
 */
function teardown_stack( $passive = false ) {
	if ( $passive ) {
		return slic_passive()( [ 'down', '--volumes', '--remove-orphans' ] );
	}

	return slic_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );
}

/**
 * Rebuilds the slic stack.
 */
function rebuild_stack() {
	echo "Building the stack images..." . PHP_EOL . PHP_EOL;

	if ( is_ci() ) {
		// In CI context do NOT build the image with XDebug and waste time on unused features.
		putenv( 'SLIC_WORDPRESS_DOCKERFILE=Dockerfile.base' );
	}

	slic_realtime()( [ 'build' ] );
	write_build_version();
	echo light_cyan( PHP_EOL . "Stack images built." . PHP_EOL . PHP_EOL );
}

/**
 * Write the current CLI_VERSION to the build-version file
 */
function write_build_version() {
	file_put_contents( SLIC_ROOT_DIR . '/.build-version', CLI_VERSION );
}

/**
 * Prints information about slic tool.
 */
function slic_info() {
    $config_vars = [
        'SLIC_TEST_SUBNET',
        'CLI_VERBOSITY',
        'CI',
        'TRAVIS_CI',
        'COMPOSER_CACHE_DIR',
        'CONTINUOUS_INTEGRATION',
        'GITHUB_ACTION',
        'SLIC_PHP_VERSION',
        'SLIC_COMPOSER_VERSION',
        'SLIC_CURRENT_PROJECT',
        'SLIC_CURRENT_PROJECT_RELATIVE_PATH',
        'SLIC_CURRENT_PROJECT_SUBDIR',
        'SLIC_HOST',
        'SLIC_PLUGINS',
        'SLIC_THEMES',
        'SLIC_GIT_DOMAIN',
        'SLIC_GIT_HANDLE',
        'SLIC_HERE_DIR',
        'SLIC_PLUGINS_DIR',
        'SLIC_THEMES_DIR',
        'SLIC_WP_DIR',
        'SLIC_INTERACTIVE',
        'SLIC_BUILD_PROMPT',
        'SLIC_BUILD_SUBDIR',
        'TERM',
        'XDK',
        'XDE',
        'XDH',
        'XDP',
        'UID',
        'SLIC_UID',
        'GID',
        'SLIC_GID',
        'MYSQL_ROOT_PASSWORD',
        'WORDPRESS_HTTP_PORT',
        'SSH_AUTH_SOCK',
    ];

	echo colorize( "<yellow>Configuration read from the following files:</yellow>" . PHP_EOL );
	$slic_root = root();
	echo implode( PHP_EOL, array_filter( [
			file_exists( $slic_root . '/.env.slic' ) ? "  - " . $slic_root . '/.env.slic' : null,
			file_exists( $slic_root . '/.env.slic.local' ) ? "  - " . $slic_root . '/.env.slic.local' : null,
			file_exists( $slic_root . '/.env.slic.run' ) ? "  - " . $slic_root . '/.env.slic.run' : null,
		] ) ) . PHP_EOL . PHP_EOL;

	echo colorize( "<yellow>Current configuration:</yellow>" . PHP_EOL );
	foreach ( $config_vars as $key ) {
		$value = print_r( getenv( $key ), true );

		if ( $key === 'SLIC_PLUGINS_DIR' && $value !== slic_plugins_dir() ) {
			// If the configuration is using a relative path, then expose the absolute path.
			$value .= ' => ' . slic_plugins_dir();
		}

		echo colorize( "  - <light_cyan>{$key}</light_cyan>: {$value}" . PHP_EOL );
	}

	echo PHP_EOL;
	echo colorize( "<yellow>Valid Targets:</yellow>" );
	$targets = get_valid_targets( true );
	echo PHP_EOL . implode( ', ', $targets );
}

/**
 * Returns the absolute path to the WordPress Core directory currently used by slic.
 *
 * The function will not check for the directory existence as we might be using this function to get a path to create.
 *
 * @param string $path An optional, relative, path to append to the WordPress Core directory absolute path.
 *
 * @return string The absolute path to the WordPress Core directory currently used by slic.
 */
function slic_wp_dir( $path = '' ) {
	$default = root( '/_wordpress' );

	$wp_dir = getenv( 'SLIC_WP_DIR' );

	if ( ! empty( $wp_dir ) ) {
		if ( ! is_dir( $wp_dir ) ) {
			// Relative path, resolve from root.
			$wp_dir = root( ltrim( preg_replace( '^\\./', '', $wp_dir ), '\\/' ) );
		}
	} else {
		$wp_dir = $default;
	}

	return empty( $path ) ? $wp_dir : $wp_dir . '/' . ltrim( $path, '\\/' );
}

/**
 * Prints the current composer-cache status to screen.
 */
function composer_cache_status() {
	$host_dir = getenv( 'COMPOSER_CACHE_DIR' );

	echo 'Composer cache directory: ' . ( $host_dir ? light_cyan( $host_dir ) : magenta( 'not set' ) ) . PHP_EOL;
}

/**
 * Handles the composer-cache command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function slic_handle_composer_cache( callable $args ) {
	$run_settings_file = root( '/.env.slic.run' );
	$toggle            = $args( 'toggle', 'status' );

	if ( 'status' === $toggle ) {
		composer_cache_status();

		return;
	}

	$value = $args( 'value', null );

	if ( 'unset' === $toggle ) {
		// Pick it up from env, if possible, or use the default one.
		$value = env_var_backup( 'COMPOSER_CACHE_DIR', cache( '/composer' ) );

		write_env_file( $run_settings_file, [ 'COMPOSER_CACHE_DIR' => $value ], true );
	}

	echo 'Composer cache directory: ' . ( $value ? light_cyan( $value ) : magenta( 'not set' ) );

	echo PHP_EOL . PHP_EOL;

	$restart_services = ask(
		'Would you like to restart the WordPress (NOT the database) and Codeception services now?',
		'yes'
	);
	if ( $restart_services ) {
		putenv( "COMPOSER_CACHE_DIR={$value}" );

		// Call for a hard restart to make sure the web-server will restart its php-fpm connection.
		restart_php_services( true );
	} else {
		echo colorize(
			PHP_EOL . PHP_EOL . "Tear down the stack with <light_cyan>down</light_cyan> and restart it to apply the new settings!" . PHP_EOL
		);
	}
}

/**
 * Prints the current build-prompt status to screen.
 */
function build_prompt_status() {
	$enabled = getenv( 'SLIC_BUILD_PROMPT' );

	echo 'Interactive status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Handles the build-prompt command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function slic_handle_build_prompt( callable $args ) {
	$run_settings_file = root( '/.env.slic.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		build_prompt_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Build Prompt status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'SLIC_BUILD_PROMPT' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'SLIC_BUILD_PROMPT' => $value ], true );
}

/**
 * Prints the current interactive status to screen.
 */
function interactive_status() {
	echo 'Interactive status is: ' . ( is_interactive() ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Returns whether the interactive mode is enabled.
 */
function is_interactive() {
	return (bool) getenv( 'SLIC_INTERACTIVE' );
}

/**
 * Handles the interactive command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function slic_handle_interactive( callable $args ) {
	$run_settings_file = root( '/.env.slic.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		interactive_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Interactive status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'SLIC_INTERACTIVE' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'SLIC_INTERACTIVE' => $value ], true );
}

/**
 * Prints the current XDebug status to screen.
 */
function xdebug_status() {
	$enabled = getenv( 'XDE' );
	$ide_key = getenv( 'XDK' );
	if ( empty( $ide_key ) ) {
		$ide_key = 'slic';
	}
	$localhost_port = getenv( 'WORDPRESS_HTTP_PORT' );
	if ( empty( $localhost_port ) ) {
		$localhost_port = '8888';
	}

	echo 'XDebug status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
	echo 'Remote host: ' . light_cyan( getenv( 'XDH' ) ) . PHP_EOL;
	echo 'Remote port: ' . light_cyan( getenv( 'XDP' ) ) . PHP_EOL;

	echo 'IDE Key: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo colorize( PHP_EOL . "You can override these values in the <light_cyan>.env.slic.local" .
	               "</light_cyan> file or by using the " .
	               "<light_cyan>'xdebug (host|key|port) <value>'</light_cyan> command." ) . PHP_EOL;


	echo PHP_EOL . 'Set up, in your IDE, a server with the following parameters to debug PHP requests:' . PHP_EOL;
	echo 'IDE key, or server name: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo 'Host: ' . light_cyan( 'http://localhost' . ( $localhost_port === '80' ? '' : ':' . $localhost_port ) ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
	               . slic_plugins_dir()
	               . '</light_cyan> => <light_cyan>/var/www/html/wp-content/plugins</light_cyan>' ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
	               . slic_wp_dir()
	               . '</light_cyan> => <light_cyan>/var/www/html</light_cyan>' );

	$default_mask = ( slic_wp_dir() === root( '/_wordpress' ) ) + 2 * ( slic_plugins_dir() === root( '/_plugins' ) );

	switch ( $default_mask ) {
		case 1:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: slic is using the default WordPress directory and a different plugins directory: ' .
			             'set path mappings correctly and keep that in mind.' );
			break;
		case 2:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: slic is using the default plugins directory and a different WordPress directory: ' .
			             'set path mappings correctly and keep that in mind.' );
			break;
		case 3;
		default:
			break;
	}
}

/**
 * Handles the XDebug command request.
 *
 * @param callable $args The closure that will produce the current XDebug request arguments.
 */
function slic_handle_xdebug( callable $args ) {
	$run_settings_file = root( '/.env.slic.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		xdebug_status();

		return;
	}

	$map = [
		'host' => 'XDH',
		'key'  => 'XDK',
		'port' => 'XDP',
	];
	if ( array_key_exists( $toggle, $map ) ) {
		$var = $args( 'value' );
		echo colorize( "Setting <light_cyan>{$map[$toggle]}={$var}</light_cyan>" ) . PHP_EOL . PHP_EOL;
		write_env_file( $run_settings_file, [ $map[ $toggle ] => $var ], true );
		echo PHP_EOL . PHP_EOL . colorize( "Tear down the stack with <light_cyan>down</light_cyan> and restart it to apply the new settings!" . PHP_EOL );

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'XDebug status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;

	if ( $value !== (int) getenv( 'XDE' ) ) {
		$xdebug_env_vars = [ 'XDE' => $value, 'XDEBUG_DISABLE' => 1 === $value ? 0 : 1 ];
		write_env_file( $run_settings_file, $xdebug_env_vars, true );
	}

	foreach ( [ 'slic', 'wordpress' ] as $service ) {
		if ( ! service_running( $service ) ) {
			continue;
		}

		echo PHP_EOL;

		if ( $value === 1 ) {
			// Enable XDebug in the service.
			echo colorize( "Enabling XDebug in <light_cyan>{$service}</light_cyan>..." );
			slic_realtime()( [ 'exec', $service, 'xdebug-on' ] );
		} else {
			echo colorize( "Disabling XDebug in <light_cyan>{$service}</light_cyan>..." );
			// Disable XDebug in the service.
			slic_realtime()( [ 'exec', $service, 'xdebug-off' ] );
		}
	}
}

/**
 * Updates the stack images by pulling the latest version of each.
 */
function update_stack_images() {
	echo "Updating the stack images..." . PHP_EOL . PHP_EOL;
	slic_realtime()( [ 'pull', '--include-deps' ] );
	echo light_cyan( PHP_EOL . PHP_EOL . "Stack images updated." . PHP_EOL );
}

/**
 * Check if a recognized command's required file exists in the specified directory.
 *
 * @param string $base_command Command name, such as 'composer' or 'npm'.
 * @param string $path The directory path in which to look for relevantly-required files (e.g. 'package.json').
 *
 * @return bool True if the path is a directory and the command doesn't have a known file requirement or the expected
 *              file does exist. False if the path is not a directory or a recognized command didn't find the
 *              relevantly-required file.
 */
function dir_has_req_build_file( $base_command, $path ) {
	// Bail if doesn't exist or is not a directory.
	if ( ! is_dir( $path ) ) {
		return false;
	}

	if ( 'composer' === $base_command ) {
		$req_file = 'composer.json';
	} elseif ( 'npm' === $base_command ) {
		$req_file = 'package.json';
	}

	// We don't know if we should handle so assume we should.
	if ( empty( $req_file ) ) {
		return true;
	}

	return is_file( rtrim( $path, '\\/' ) . '/' . $req_file );
}

/**
 * Maybe run the install process (e.g. Composer, NPM) on a given target.
 *
 * @param string $base_command Base command to run.
 * @param string $target Target to potentially run composer install against.
 * @param array $sub_directories Sub directories to prompt for additional execution.
 *
 * @return array Result of command execution.
 */
function maybe_build_install_command_pool( $base_command, $target, array $sub_directories = [] ) {
	// Only prompt if the target itself has has been identified as available to build. If any subs need to build, will auto-try.
	if ( dir_has_req_build_file( $base_command, slic_plugins_dir( $target ) ) ) {
		$run = ask(
			PHP_EOL . yellow( $target . ':' ) . " Would you like to run the {$base_command} install processes for this plugin?",
			'yes'
		);
	}

	if ( empty( $run ) ) {
		// Do not run the command on sub-directories if not running on the target.
		return [];
	}

	$subdirs_to_build = array_reduce( $sub_directories, static function ( array $buffer, $sub_directory ) use (
		$base_command
	) {
		$subdir_path = target_absolute_path( $sub_directory );
		if ( dir_has_req_build_file( $base_command, $subdir_path ) ) {
			$buffer[] = $sub_directory;
		}

		return $buffer;
	}, [] );

	return count( $subdirs_to_build ) ? build_command_pool( $base_command, [ 'install' ], $sub_directories ) : [];
}

/**
 * Run a command using the appropriate service.
 *
 * If any subdirectories are provided and are available in the target, then the user will be prompted to run the same
 * command on those subdirectories.
 *
 * @param string $base_command The base service command to run, e.g. `npm`, `composer`, etc.
 * @param array<string> $command The command to run, e.g. `['install','--save-dev']` in array format.
 * @param array<string> $sub_directories Sub directories to prompt for additional execution.
 * @param string $using An optional target to use in place of the specified one.
 *
 * @return array The built command pool.
 */
function build_command_pool( $base_command, array $command, array $sub_directories = [], $using = null ) {
	$using_alias = $using;
	$using       = $using ?: slic_target();
	$targets     = [];

	// If applicable, include target plugin before subdirectory plugins.
	$path = $using === 'site' ? slic_wp_dir() : slic_plugins_dir( slic_target() );
	if ( dir_has_req_build_file( $base_command, $path ) ) {
		$targets[] = 'target';
	}

	// Prompt for execution within subdirectories, if enabled.
	if ( getenv( 'SLIC_BUILD_SUBDIR' ) ) {
		foreach ( $sub_directories as $dir ) {
			$sub_target = $using_alias ? "{$using_alias}/{$dir}" : "{$using}/{$dir}";

			$question = PHP_EOL . yellow( $sub_target . ':' ) . " Would you like to run the {$base_command} command against {$sub_target}?";
			if (
				dir_has_req_build_file( $base_command, slic_plugins_dir( $sub_target ) )
				&& ask( $question, 'yes' )
			) {
				$targets[] = $dir;
			}
		}
	}

	// Build the command process.
	$command_process = static function ( $target, $subnet = '' ) use ( $using, $using_alias, $base_command, $command, $sub_directories ) {
		$target_name = $using_alias ?: $target;

		// If the command is wrapped in a bash -c "", then let's not spit out the bash -c "" part.
		if ( preg_match( '/bash -c "(.*)"/', $base_command, $results ) ) {
			$friendly_base_command = $results[1];
		} else {
			$friendly_base_command = $base_command;
		}

		// If the command is executing a dynamic script in the scripts directory, grab the command name.
		if ( preg_match( '!\. /slic-scripts/(\..*.sh)!', $friendly_base_command, $results ) ) {
			$file = escapeshellarg( SLIC_ROOT_DIR . '/' . trim( getenv( 'SLIC_SCRIPTS' ), '.' ) . '/' . $results[1] );
			$friendly_base_command = `tail -n 1 $file`;
		}

		$prefix      = "{$friendly_base_command}:" . light_cyan( $target_name );

		// Execute command as the parent.
		if ( 'target' !== $target ) {
			slic_switch_target( "{$using}/{$target}" );
			$sub_target_name = $using_alias ? "{$using_alias}/{$target}" : $target;
			$prefix          = "{$friendly_base_command}:" . yellow( $sub_target_name );
		}

		putenv( "SLIC_TEST_SUBNET={$subnet}" );

		$network_name = "slic{$subnet}";
		$status       = slic_passive()( array_merge( [
			'exec',
			'-T',
			'--user',
			sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
			'--workdir',
			escapeshellarg( get_project_container_path() ),
			$network_name,
			$base_command
		], $command ), $prefix );

		if ( ! empty( $subnet ) ) {
			do {
				/*
				 * Some containers might take time to terminate after yielding control back to the Docker daemon (zombies).
				 * If we try to remote the network when zombie containers are attached to it, we'll get the following error:
				 * "error while removing network: network <network_name> id <id> has active endpoints".
				 * When this happens, the return status of the command will be a `1`.
				 * We iterate until the status is a `0`.
				 */
				$network_rm_status = (int) process( "docker network rm {$network_name}_slic {$network_name}_default" )( 'status' );
			} while ( $network_rm_status !== 0 );
		}

		if ( 'target' !== $target ) {
			slic_switch_target( $using );
		}

		exit( pcntl_exit( $status ) );
	};

	$pool = [];

	// Build the pool with a target/container/command-specific key.
	foreach ( $targets as $target ) {
		$clean_command = implode( ' ', $command );

		$pool["{$target}:{$base_command}:{$clean_command}"] = [
			'target'    => $target,
			'container' => $base_command,
			'command'   => $command,
			'process'   => $command_process,
		];
	}

	return $pool;
}

/**
 * Executes a pool of commands in parallel.
 *
 * @param array $pool Pool of processes to execute in parallel.
 *     $pool[] = [
 *       'target'    => (string) Slic target.
 *       'container' => (string) Container on which to execute the command.
 *       'command'   => (array) The command to run, e.g. `['install', '--save-dev']` in array format.
 *       'process'   => (closure) The function to execute for each Slic target.
 *     ]
 *
 * @return int Result of combined command execution.
 */
function execute_command_pool( $pool ) {
	if ( ! $pool ) {
		return 0;
	}

	$using = slic_target();

	if ( count( $pool ) > 1 ) {
		$status = parallel_process( $pool );
		slic_switch_target( $using );

		return $status;
	}

	$pool_item = reset( $pool );

	return $pool_item['process']( $pool_item['target'] );
}

/**
 * Returns an array of arguments to correctly run a wp-cli command in the slic stack.
 *
 * @param array<string> $command The wp-cli command to run, anything after the `wp`; e.g. `['plugin', 'list']`.
 * @param bool $requirements Whether to ensure the requirements to run a cli command are met or not.
 *
 * @return array<string> The complete command arguments, ready to be used in the `slic` or `slic_realtime` functions.
 */
function cli_command( array $command = [], $requirements = false ) {
	if ( $requirements ) {
		ensure_wordpress_ready();
	}

	return array_merge( [ 'exec', '--workdir', '/var/www/html', 'slic', 'wp', '--allow-root' ], $command );
}

/**
 * Switches a plugin branch.
 *
 * The function will try to pull, and switch to, the branch from the plugin repository remotes if the branch is not
 * locally available.
 * If the branch is locally available, then the function will switch to the local version of th branch; this might not
 * be up-to-date with the remote: this is done by design as the sync of local and remote branches should be a developer
 * concern.
 *
 * @param string $branch The name of the branch to switch to, e.g. `release/B20.03`.
 * @param string|null $plugin The slug of the plugin to switch branch for; if not specified, then the current slic
 *                            target will be used.
 */
function switch_plugin_branch( $branch, $plugin = null ) {
	$cwd = getcwd();

	if ( false === $cwd ) {
		echo magenta( "Cannot get current working directory; is it accessible?" . PHP_EOL );
		exit( 1 );
	}

	$plugin     = null === $plugin ? slic_target() : $plugin;
	$plugin_dir = slic_plugins_dir( $plugin );

	echo light_cyan( "Temporarily using {$plugin}" . PHP_EOL );

	$changed = chdir( $plugin_dir );

	if ( false === $changed ) {
		echo magenta( "Cannot change to directory {$plugin_dir}; is it accessible?" . PHP_EOL );
		exit( 1 );
	}

	$current_branch = check_status_or_exit( process( 'git branch --show-current' ) )( 'string_output' );

	if ( $current_branch === $branch ) {
		// Already on the correct branch.
		return;
	}

	$locally_available = check_status_or_exit( process( 'git branch' ) )( 'output' );

	// Clean up the branch names.
	$locally_available = array_map( static function ( $branch ) {
		return trim( preg_replace( '/^\*\\s+/', '', $branch ) );
	}, $locally_available );

	if ( ! in_array( $branch, $locally_available, true ) ) {
		echo "Branch {$branch} not found locally: checking it out from remotes...";
		$status  = 1;
		$remotes = check_status_or_exit( process( 'git remote' ) )( 'output' );
		foreach ( $remotes as $remote ) {
			// Try fetching from each available remote.
			$command = sprintf( 'git checkout -b %1$s --recurse-submodules %2$s/%1$s', $branch, $remote );
			$status  = process_realtime( $command );
			if ( 0 === $status ) {
				// We're done.
				break;
			}
		}

		if ( 0 !== $status ) {
			// If we could not fetch from any remote we failed.
			echo magenta( "Remote branch fetch failed." . PHP_EOL );
			exit( 1 );
		}
	} else {
		echo "Branch {$branch} found locally: checking it out...";
		$command = 'git checkout --recurse-submodules ' . $branch;
		if ( 0 !== process_realtime( $command ) ) {
			echo magenta( "Branch switch failed." . PHP_EOL );
			exit( 1 );
		}
	}

	// Restore the current working directory to the previous value.
	echo light_cyan( 'Using ' . slic_target() . " once again". PHP_EOL );
	$restored = chdir( $cwd );

	if ( false === $restored ) {
		echo magenta( "Could not restore working directory {$cwd}" . PHP_EOL );
		exit( 1 );
	}
}

/**
 * If slic itself is out of date, prompt to update repo.
 */
function maybe_prompt_for_repo_update() {
	$remote_version = null;
	$check_date     = null;
	$cli_version    = CLI_VERSION;
	$today          = date( 'Y-m-d' );

	if ( file_exists( SLIC_ROOT_DIR . '/.remote-version' ) ) {
		list( $check_date, $remote_version ) = explode( ':', file_get_contents( SLIC_ROOT_DIR . '/.remote-version' ) );
	}

	if ( empty( $remote_version ) || empty( $check_date ) || $today > $check_date ) {
		$current_dir = getcwd();
		chdir( SLIC_ROOT_DIR );

		$tags = explode( "\n", shell_exec( 'git ls-remote --tags origin' ) );

		chdir( $current_dir );

		foreach ( $tags as &$tag ) {
			$tag_parts = explode( '/', $tag );
			$tag       = array_pop( $tag_parts );
		}

		natsort( $tags );

		$remote_version = array_pop( $tags );

		file_put_contents( SLIC_ROOT_DIR . '/.remote-version', "{$today}:{$remote_version}" );
	}

	// If the version of the CLI is the same as the most recently built version, bail.
	if ( version_compare( $remote_version, $cli_version, '<=' ) ) {
		return;
	}

	echo magenta( PHP_EOL . "****************************************************************" . PHP_EOL . PHP_EOL );
	echo colorize( "<magenta>Version</magenta> <yellow>{$remote_version}</yellow> <magenta>of slic is available! You are currently</magenta>" . PHP_EOL );
	echo magenta( "running version {$cli_version}. To update, execute the following:" . PHP_EOL . PHP_EOL );
	echo yellow( "                         slic upgrade" . PHP_EOL . PHP_EOL );
	echo magenta( "****************************************************************" . PHP_EOL );
}

/**
 * If slic stack is out of date, prompt for an execution of slic update.
 */
function maybe_prompt_for_stack_update() {
	$build_version = '0.0.1';
	$cli_version   = CLI_VERSION;

	if ( file_exists( SLIC_ROOT_DIR . '/.build-version' ) ) {
		$build_version = file_get_contents( SLIC_ROOT_DIR . '/.build-version' );
	}

	// If there isn't a .env.slic.run, this is likely a fresh install. Bail.
	if ( ! file_exists( SLIC_ROOT_DIR . '/.env.slic.run' ) ) {
		return;
	}

	// If the version of the CLI is the same as the most recently built version, bail.
	if ( version_compare( $build_version, $cli_version, '=' ) ) {
		return;
	}

	echo magenta( PHP_EOL . "****************************************************************" . PHP_EOL . PHP_EOL );
	echo yellow( "                  ____________    ____  __" . PHP_EOL );
	echo yellow( "                  |   ____\   \  /   / |  |" . PHP_EOL );
	echo yellow( "                  |  |__   \   \/   /  |  |" . PHP_EOL );
	echo yellow( "                  |   __|   \_    _/   |  |" . PHP_EOL );
	echo yellow( "                  |  |        |  |     |  |" . PHP_EOL );
	echo yellow( "                  |__|        |__|     |__|" . PHP_EOL . PHP_EOL );
	echo magenta( "Your slic containers are not up to date with the latest version." . PHP_EOL );
	echo magenta( "                  To update, please run:" . PHP_EOL . PHP_EOL );
	echo yellow( "                         slic update" . PHP_EOL . PHP_EOL );
	echo magenta( "****************************************************************" . PHP_EOL );
}

/**
 * Handles the build-subdir command request.
 *
 * @param callable $args The closure that will produce the current subdirectories build arguments.
 */
function slic_handle_build_subdir( callable $args ) {
	$run_settings_file = root( '/.env.slic.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		build_subdir_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Build Sub-directories status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'SLIC_BUILD_SUBDIR' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'SLIC_BUILD_SUBDIR' => $value ], true );
}

/**
 * Prints the current build-subdir status to screen.
 */
function build_subdir_status() {
	$enabled = getenv( 'SLIC_BUILD_SUBDIR' );

	echo 'Sub-directories build status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Build a command pool, suitable to be run using the `execute_command_pool` function, for multiple targets.
 *
 * If any subdirectories are provided and are available in the target, then the user will be prompted to run the same
 * command on those subdirectories.
 *
 * @param array<string> $targets An array of targets for the command pool; note the targets are NOT validated by
 *                                       this function and the validation should be done by the calling code.
 * @param string $base_command The base service command to run, e.g. `npm`, `composer`, etc.
 * @param array<string> $command The command to run, e.g. `['install','--save-dev']` in array format.
 * @param array<string> $sub_directories Sub directories to prompt for additional execution.
 *
 * @return array The built command pool for all the targets.
 */
function build_targets_command_pool( array $targets, $base_command, array $command, array $sub_directories = [] ) {
	$raw_command_pool = array_combine(
		$targets,
		array_map( static function ( $target ) use ( $base_command, $command, $sub_directories ) {
			return build_command_pool( $base_command, $command, $sub_directories, $target );
		}, $targets )
	);

	// Set the keys correctly to have the command prefixes correctly built.
	$command_pool = [];
	foreach ( $raw_command_pool as $target => $target_pool ) {
		foreach ( $target_pool as $target_key => $process ) {
			$key                  = preg_replace(
				[
					// Main target.
					'/^target:/',
					// Sub-directories.
					'/^([\w\d]+):/'
				],
				[
					// Replace with `<target>:`.
					$target . ':',
					// Replace with `<target>/<subdir>:`.
					$target . '/$1:'
				],
				$target_key
			);
			$command_pool[ $key ] = $process;
		}
	}

	return $command_pool;
}

/**
 * Returns the current target or exits if no target is set.
 *
 * @param string|null $reason The colorized reason why the target should be set.
 *
 * @return string The current target, if set, else the function will exit.
 */
function slic_target_or_fail( $reason = null ) {
	$target = slic_target();

	if ( empty( $target ) ) {
		$reason = $reason
			?: magenta( 'This command requires a target set using the ' )
			   . light_cyan( 'use' )
			   . magenta( ' command.' );
		echo colorize( $reason . PHP_EOL );
		exit( 1 );
	}

	return $target;
}

/**
 * Returns the absolute path to the current target.
 *
 * @param null|string $append_path A relative path to append to the target absolute path.
 *
 * @return string The absolute path to the current target.
 */
function target_absolute_path( $append_path = null ) {
	$here_abs_path    = rtrim( getenv( 'SLIC_HERE_DIR' ), '\\/' );
	$target_rel_path  = '/' . trim( slic_target(), '\\/' );
	$full_target_path = $here_abs_path . $target_rel_path;
	if ( empty( $append_path ) ) {
		return $full_target_path;
	}

	return $full_target_path . '/' . ltrim( $append_path, '\\/' );
}

/**
 * Compiles a list of the current target Codeception suites. The available suites are inferred, as Codeception does,
 * from the available suite configuration files.
 *
 * @return array<string> A list of the available target suites.
 */
function collect_target_suites() {
	// If the command is just `run`, without arguments, then collect the available suites and run them separately.
	$dir_iterator = new \DirectoryIterator( target_absolute_path( 'tests' ) );
	$suitesFilter = new \CallbackFilterIterator( $dir_iterator, static function ( \SplFileInfo $file ) {
		return $file->isFile() && preg_match( '/^.*\\.suite(\\.dist)?\\.yml$/', $file->getBasename() );
	} );
	$suites       = [];
	foreach ( $suitesFilter as $f ) {
		$suites[] = preg_replace( '/^([\\w-]+)\\.suite(\\.dist)?\\.yml$/u', '$1', $f->getBasename() );
	}

	return $suites;
}

/**
 * Returns whether the current system is ARM-based or not.
 *
 * The function will, on first run, create a flag file in
 * the `slic` root directory under the reasonable assumption
 * the architecture will not change on the same machine.
 *
 * @return bool Whether the current system is ARM-based or not.
 */
function is_arm64() {
	$arm64_architecture_file = __DIR__ . '/../.architecture_arm64';
	$x86_architecture_file   = __DIR__ . '/../.architecture_x86';
	if ( file_exists( $arm64_architecture_file ) ) {
		return true;
	} elseif ( file_exists( $x86_architecture_file ) ) {
		return false;
	}

	exec( PHP_BINARY . ' -i', $output, $result_code );

	if ( $result_code !== 0 ) {
		return false;
	}

	$is_arm64 = false !== strpos( implode( ' ', (array) $output ), 'arm64' );

	if ( $is_arm64 ) {
		touch( $arm64_architecture_file );

		return true;
	}

	touch( $x86_architecture_file );

	return false;
}

/**
 * Depending on the machine architecture, use an x86 or arm64
 * standalone Chrome container.
 *
 * @return void The function does not return any value and will
 *              have the side effect of setting up environment
 *              vars related to the current architecture.
 *
 * @see is_arm64() Used to detect the architecture.
 */
function setup_architecture_env() {
	if ( is_arm64() ) {
		putenv( 'SLIC_ARCHITECTURE=arm64' );
		putenv( 'SLIC_CHROME_CONTAINER=seleniarm/standalone-chromium:4.1.2-20220227' );
	} else {
		putenv( 'SLIC_ARCHITECTURE=x86' );
		putenv( 'SLIC_CHROME_CONTAINER=selenium/standalone-chrome:3.141.59' );
	}
}

/**
 * Creates and returns the path to the cache directory root or a path in it.
 *
 * Directories part of the path will be recursively created.
 *
 * @param string $path The path, relative to the cache directory root directory, to return the cache absolute path for.
 * @param bool $create Whether the directory required should be created if not present or not.
 *
 * @return string The absolute path to the created directory or file.
 */
function cache( $path = '/', $create = true ) {
	$cache_root_dir = __DIR__ . '/../.cache';

	if ( ! is_dir( $cache_root_dir ) && ! mkdir( $cache_root_dir, 0755, true ) && ! is_dir( $cache_root_dir ) ) {
		echo magenta( "Failed to create cache root directory {$cache_root_dir}." );
		exit( 1 );
	}

	$cache_root_dir = realpath( $cache_root_dir );

	if ( empty( $cache_root_dir ) ) {
		echo magenta( "Failed to resolve cache root directory real path." );
		exit( 1 );
	}

	$dir_sep   = DIRECTORY_SEPARATOR;
	$full_path = rtrim( realpath( $cache_root_dir ) . $dir_sep . ltrim( $path, $dir_sep ), $dir_sep );
	// If the last dot is closer to the end of the string than the last forward slash, assume it's a file.
	$last_dir_sep_end_offset = strpos( strrev( $full_path ), $dir_sep );
	$is_file                 = strpos( strrev( $full_path ), '.' ) < $last_dir_sep_end_offset;
	$dir_path                = $is_file ? substr( $full_path, 0, - $last_dir_sep_end_offset ) : $full_path;

	if ( ! is_dir( $dir_path ) && ! mkdir( $dir_path ) && ! is_dir( $dir_path ) ) {
		echo magenta( "Failed to create cache directory $dir_path." );
		exit( 1 );
	}

	return $full_path;
}
