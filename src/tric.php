<?php
/**
 * tric cli functions.
 */

namespace Tribe\Test;

/**
 * Returns whether or not the tric here command was done at the site level or not.
 *
 * @return bool
 */
function tric_here_is_site() {
	return TRIC_ROOT_DIR . '/_wordpress' !== getenv( 'TRIC_WP_DIR' )
		&& './_wordpress' !== getenv( 'TRIC_WP_DIR' );
}

/**
 * Checks a specified target is supported as a target.
 *
 * Valid targets are:
 *   - Anything in the plugins directory.
 *   - If tric here was done on the site level, "site" is also a valid target.
 *
 * @param string $target The target to check in the valid list of targets.
 */
function ensure_valid_target( $target ) {
	$targets_str = '';
	$plugins = array_keys( dev_plugins() );
	$themes  = array_keys( dev_themes() );
	$targets = $plugins;

	if ( tric_here_is_site() ) {
		$targets = array_merge( [ 'site' ], $plugins, $themes );
		$targets_str .= PHP_EOL . '  Site:' . PHP_EOL;
		$targets_str .= '    - site';
	}

	$targets_str .= PHP_EOL . "  Plugins:" . PHP_EOL;
	$targets_str .= implode( PHP_EOL, array_map( static function ( $target ) {
		return "    - {$target}";
	}, $plugins ) );

	if ( tric_here_is_site() && $themes ) {
		$targets_str .= PHP_EOL . "  Themes:" . PHP_EOL;
		$targets_str .= implode( PHP_EOL, array_map( static function ( $target ) {
			return "    - {$target}";
		}, $themes ) );
	}

	if ( false === $target ) {
		echo magenta( "This command needs a target argument; available targets are:\n${targets_str}\n" );
		exit( 1 );
	}

	if ( ! in_array( $target, $targets, true ) ) {
		echo magenta( "'{$target}' is not a valid target; available targets are:\n${targets_str}\n" );
		exit( 1 );
	}
}

/**
 * Get the container relative path to the provided target.
 *
 * @param $target Target with which to build the relative path from.
 *
 * @return string
 */
function get_target_relative_path( $target ) {
	if ( 'site' === $target ) {
		return '';
	}

	$plugin_dir = getenv( 'TRIC_PLUGINS_DIR' );
	$theme_dir  = getenv( 'TRIC_THEMES_DIR' );

	if ( file_exists( "{$plugin_dir}/{$target}" ) ) {
		$parent_path = $plugin_dir;
	} elseif ( file_exists( "{$theme_dir}/{$target}" ) ) {
		$parent_path = $theme_dir;
	} else {
		echo magenta( "Unable to locate a path to the desired target ({$target}). Searched in: \n- {$plugin_dir}\n- {$theme_dir}" );
		exit( 1 );
	}

	$parent_path = str_replace( getenv( 'TRIC_HERE_DIR' ) . '/', '', $parent_path );

	return "{$parent_path}/{$target}";
}

/**
 * Sets up the environment form the cli tool.
 *
 * @param string $root_dir The cli tool root directory.
 */
function setup_tric_env( $root_dir ) {
	// Let's declare we're performing trics.
	putenv( 'TRIBE_TRIC=1' );

	$os = os();
	if ( $os === 'macOS' || $os === 'Windows' ) {
		// Do not fix file modes on hosts that implement user ID and group ID remapping at the Docker daemon level.
		putenv( 'FIXUID=0' );
	}

	// Load the distribution version configuration file, the version-controlled one.
	load_env_file( $root_dir . '/.env.tric' );

	// Load the local overrides, this file is not version controlled.
	if ( file_exists( $root_dir . '/.env.tric.local' ) ) {
		load_env_file( $root_dir . '/.env.tric.local' );
	}

	// Load the current session configuration file.
	if ( file_exists( $root_dir . '/.env.tric.run' ) ) {
		load_env_file( $root_dir . '/.env.tric.run' );
	}

	$wp_dir = getenv( 'TRIC_WP_DIR' );
	if ( empty( $wp_dir ) ) {
		$wp_dir = root( '_wordpress' );
	} elseif ( ! is_dir( $wp_dir ) ) {
		$wp_dir_path = root( ltrim( $wp_dir, './' ) );

		if (
			is_dir( dirname( $wp_dir_path ) )
			&& ! is_dir( $wp_dir_path )
			&& ! mkdir( $wp_dir_path )
			&& ! is_dir( $wp_dir_path )
		) {
			// If the WordPress directory does not exist, then create it now.
			echo magenta( "Cannot create the {$wp_dir_path} directory" );
			exit( 1 );
		}

		$wp_dir = realpath( $wp_dir_path );
	}

	// Whatever the case, the WordPress directory should now exist.
	if ( ! is_dir( $wp_dir ) ) {
		echo magenta( "The WordPress directory ({$wp_dir}) does not exist." );
		exit( 1 );
	}

	maybe_generate_htaccess();

	$plugins_dir = getenv( 'TRIC_PLUGINS_DIR' );
	if ( empty( $plugins_dir ) ) {
		$plugins_dir = root( '_plugins' );
	} elseif ( ! is_dir( $plugins_dir ) ) {
		$plugin_dir_path = root( ltrim( $plugins_dir, './' ) );

		if (
			is_dir( basename( $plugin_dir_path ) )
			&& ! is_dir( $plugin_dir_path )
			&& ! mkdir( $plugin_dir_path )
			&& ! is_dir( $plugin_dir_path )
		) {
			echo magenta( "Cannot create the {$plugin_dir_path} directory." );
			exit( 1 );
		}
		$plugins_dir = realpath( $plugin_dir_path );
	}

	putenv( 'TRIC_WP_DIR=' . $wp_dir );
	putenv( 'TRIC_PLUGINS_DIR=' . $plugins_dir );

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
function tric_target( $require = true ) {
	$using = getenv( 'TRIC_CURRENT_PROJECT' );
	if ( $require ) {
		return $using;
	}
	if ( empty( $using ) ) {
		echo magenta( "Use target not set; use the 'use' sub-command to set it.\n" );
		exit( 1 );
	}

	return trim( $using );
}

/**
 * Switches the current `use` target.
 *
 * @param string $target Target to switch to.
 */
function tric_switch_target( $target ) {
	$root                 = root();
	$run_settings_file    = "{$root}/.env.tric.run";
	$target_relative_path = '';

	if ( tric_here_is_site() ) {
		$target_relative_path = get_target_relative_path( $target );
	}

	$env_values = [
		'TRIC_CURRENT_PROJECT'               => $target,
		'TRIC_CURRENT_PROJECT_RELATIVE_PATH' => $target_relative_path,
	];

	write_env_file( $run_settings_file, $env_values, true );

	setup_tric_env( $root );
}

/**
 * Returns a map of the stack PHP services that relates the service to its pretty name.
 *
 * @return array<string,string> A map of the stack PHP services relating each service to its pretty name.
 */
function php_services() {
	return [
		'wordpress'   => 'WordPress',
		'codeception' => 'Codeception',
	];
}

/**
 * Restart the stack PHP services.
 *
 * @param bool $hard Whether to restart the PHP services using the `docker-compose restart` command or by using a
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
 * @param string      $service     The name of the service to restart, e.g. `wordpress`.
 * @param string|null $pretty_name The pretty name to use for the service, or `null` to use the service name.
 * @param bool $hard Whether to restart the service using the `docker-compose restart` command or to use full tear-down
 *                   and up again cycle.
 */
function restart_service( $service, $pretty_name = null, $hard = false ) {
	$pretty_name   = $pretty_name ?: $service;
	$tric          = docker_compose( tric_stack_array() );
	$tric_realtime = docker_compose_realtime( tric_stack_array() );

	$service_running = $tric( [ 'ps', '-q', $service ] )( 'string_output' );
	if ( ! empty( $service_running ) ) {
		echo colorize( "Restarting {$pretty_name} service...\n" );
		if ( $hard ) {
			$tric_realtime( [ 'rm', '--stop', '--force', $service ] );
			$tric_realtime( [ 'up', '-d', $service ] );
		} else {
			$tric_realtime( [ 'restart', $service ] );
		}
		echo colorize( "<light_cyan>{$pretty_name} service restarted.</light_cyan>\n" );
	} else {
		echo colorize( "{$pretty_name} service was not running.\n" );
	}
}

/**
 * Returns the absolute path to the current plugins directory tric is using.
 *
 * @param string $path An optional path to append to the current tric plugin directory.
 *
 * @return string The absolute path to the current plugins directory tric is using.
 *
 */
function tric_plugins_dir( $path = '' ) {
	return tric_content_type_dir( 'plugins', $path );
}

/**
 * Returns the absolute path to the current plugins directory tric is using.
 *
 * @param string $path An optional path to append to the current tric plugin directory.
 *
 * @return string The absolute path to the current plugins directory tric is using.
 *
 */
function tric_themes_dir( $path = '' ) {
	return tric_content_type_dir( 'themes', $path );
}

/**
 * Returns the absolute path to the current content directory tric is using.
 *
 * @param string $path An optional path to append to the current tric content directory.
 *
 * @return string The absolute path to the current content directory tric is using.
 *
 */
function tric_content_type_dir( $content_type = 'plugins', $path = '' ) {
	$content_type_dir = getenv( 'TRIC_' . strtoupper( $content_type ) . '_DIR' );
	$root_dir         = root();

	if ( 'plugins' === $content_type ) {
		$default_path = '/_plugins';
	} elseif ( 'themes' === $content_type ) {
		$default_path = '/_wordpress/wp-content/themes';
	}

	if ( empty( $content_type_dir ) ) {
		// Use the default directory in tric repository.
		$dir = $root_dir . $default_path;
	} elseif ( is_dir( $content_type_dir ) ) {
		// Use the specified directory.
		$dir = $content_type_dir;
	} else {
		if ( 0 === strpos( $content_type_dir, '.' ) ) {
			// Resolve the './...' paths a relative to the root directory in tric repository.
			$dir = preg_replace( '/^\\./', $root_dir, $content_type_dir );
		} else {
			// Use a directory relative to the root directory in tric reopository.
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
	$plugin_dir  = tric_plugins_dir();
	$plugin_path = tric_plugins_dir( $plugin );

	if ( ! file_exists( $plugin_dir ) ) {
		echo "Creating the plugins directory...\n";
		if ( ! mkdir( $plugin_dir ) && ! is_dir( $plugin_dir ) ) {
			echo magenta( "Could not create {$plugin_dir} directory; please check the parent directory is writeable." );
			exit( 1 );
		}
	}

	// If the plugin path already exists, don't bother cloning.
	if ( file_exists( $plugin_path ) ) {
		return;
	}

	echo "Cloning {$plugin}...\n";

	$repository = github_company_handle() . '/' . escapeshellcmd( $plugin );

	$clone_command = sprintf(
		'git clone %s --recursive git@github.com:%s.git %s',
		null !== $branch ? '-b "' . $branch . '"' : '',
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
 * Sets up the files required to run tests in the plugin using tric stack.
 *
 * @param string $plugin The plugin name, e.g. 'the-events-calendar` or `event-tickets`.
 */
function setup_plugin_tests( $plugin ) {
	$plugin_path    = tric_plugins_dir() . '/' . $plugin;
	$relative_paths = [ '' ];

	if ( file_exists( "{$plugin_path}/common" ) ) {
		$relative_paths[] = 'common';
	}

	foreach ( $relative_paths as $relative_path ) {
		$target_path   = "{$plugin_path}/{$relative_path}";
		$relative_path = empty( $relative_path ) ? '' : "{$relative_path}/";

		if ( write_tric_test_config( $target_path ) ) {
			echo colorize( "Created/updated <light_cyan>{$relative_path}test-config.tric.php</light_cyan> " .
			               "in {$plugin}.\n" );
		}

		write_tric_env_file( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}.env.testing.tric</light_cyan> " .
		               "in <light_cyan>{$plugin}</light_cyan>.\n" );


		write_codeception_config( $target_path );
		echo colorize( "Created/updated <light_cyan>{$relative_path}codeception.tric.yml</light_cyan> in " .
		               "<light_cyan>{$plugin}</light_cyan>.\n" );
	}
}

/**
 * Returns the handle (username) of the company to clone plugins from.
 *
 * Configured using the `TRIC_GITHUB_COMPANY_HANDLE` env variable.
 *
 * @return string The handle of the company to clone plugins from.
 */
function github_company_handle() {
	$handle = getenv( 'TRIC_GITHUB_COMPANY_HANDLE' );

	return ! empty( $handle ) ? trim( $handle ) : 'moderntribe';
}

/**
 * Runs a process in tric stack and returns the exit status.
 *
 * @return \Closure The process closure to start a real-time process using tric stack.
 */
function tric_realtime() {
	return docker_compose_realtime( tric_stack_array() );
}

/**
 * Returns the process Closure to start a real-time process using tric stack.
 *
 * @return \Closure The process closure to start a real-time process using tric stack.
 */
function tric_process() {
	return docker_compose( tric_stack_array() );
}

/**
 * Tears down tric stack.
 */
function teardown_stack() {
	tric_realtime()( [ 'down', '--volumes', '--remove-orphans' ] );
}

/**
 * Rebuilds the tric stack.
 */
function rebuild_stack() {
	echo "Building the stack images...\n\n";
	tric_realtime()( [ 'build' ] );
	echo light_cyan( "\nStack images built.\n\n" );
}

/**
 * Prints information about tric tool.
 */
function tric_info() {
	$config_vars = [
		'TRIC_TEST_SUBNET',
		'CLI_VERBOSITY',
		'TRIC_CURRENT_PROJECT',
		'TRIC_CURRENT_PROJECT_RELATIVE_PATH',
		'TRIC_GITHUB_COMPANY_HANDLE',
		'TRIC_HERE_DIR',
		'TRIC_PLUGINS_DIR',
		'TRIC_THEMES_DIR',
		'TRIC_WP_DIR',
		'XDK',
		'XDE',
		'XDH',
		'XDP',
		'MYSQL_ROOT_PASSWORD',
		'WORDPRESS_HTTP_PORT',
	];

	echo colorize( "<yellow>Configuration read from the following files:</yellow>\n" );
	$tric_root = root();
	echo implode( "\n", array_filter( [
			file_exists( $tric_root . '/.env.tric' ) ? "  - " . $tric_root . '/.env.tric' : null,
			file_exists( $tric_root . '/.env.tric.local' ) ? "  - " . $tric_root . '/.env.tric.local' : null,
			file_exists( $tric_root . '/.env.tric.run' ) ? "  - " . $tric_root . '/.env.tric.run' : null,
		] ) ) . "\n\n";

	echo colorize( "<yellow>Current configuration:</yellow>\n" );
	foreach ( $config_vars as $key ) {
		$value = print_r( getenv( $key ), true );

		if ( $key === 'TRIC_PLUGINS_DIR' && $value !== tric_plugins_dir() ) {
			// If the configuration is using a relative path, then expose the absolute path.
			$value .= ' => ' . tric_plugins_dir();
		}

		echo colorize( "  - <light_cyan>{$key}</light_cyan>: {$value}\n" );
	}
}

/**
 * Returns the absolute path to the WordPress Core directory currently used by tric.
 *
 * The function will not check for the directory existence as we might be using this function to get a path to create.
 *
 * @param string $path An optional, relative, path to append to the WordPress Core directory absolute path.
 *
 * @return string The absolute path to the WordPress Core directory currently used by tric.
 */
function tric_wp_dir( $path = '' ) {
	$default = root( '/_wordpress' );

	$wp_dir = getenv( 'TRIC_WP_DIR' );

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
 * Prints the current build-prompt status to screen.
 */
function build_prompt_status() {
	$enabled = getenv( 'TRIC_INTERACTIVE' );

	echo 'Interactive status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Handles the build-prompt command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function tric_handle_build_prompt( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		build_prompt_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Build Prompt status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'TRIC_BUILD_PROMPT' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'TRIC_BUILD_PROMPT' => $value ], true );
}

/**
 * Prints the current interactive status to screen.
 */
function interactive_status() {
	$enabled = getenv( 'TRIC_INTERACTIVE' );

	echo 'Interactive status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Handles the interactive command request.
 *
 * @param callable $args The closure that will produce the current interactive request arguments.
 */
function tric_handle_interactive( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		interactive_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'Interactive status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'TRIC_INTERACTIVE' ) ) {
		return;
	}

	write_env_file( $run_settings_file, [ 'TRIC_INTERACTIVE' => $value ], true );
}

/**
 * Prints the current XDebug status to screen.
 */
function xdebug_status() {
	$enabled = getenv( 'XDE' );
	$ide_key = getenv( 'XDK' );
	if ( empty( $ide_key ) ) {
		$ide_key = 'tric';
	}
	$localhost_port = getenv( 'WORDPRESS_HTTP_PORT' );
	if ( empty( $localhost_port ) ) {
		$localhost_port = '8888';
	}

	echo 'XDebug status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
	echo 'Remote host: ' . light_cyan( getenv( 'XDH' ) ) . PHP_EOL;
	echo 'Remote port: ' . light_cyan( getenv( 'XDP' ) ) . PHP_EOL;

	echo 'IDE Key: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo colorize( PHP_EOL . "You can override these values in the <light_cyan>.env.tric.local" .
			"</light_cyan> file or by using the " .
			"<light_cyan>'xdebug (host|key|port) <value>'</light_cyan> command." ) . PHP_EOL;


	echo PHP_EOL . 'Set up, in your IDE, a server with the following parameters to debug PHP requests:' . PHP_EOL;
	echo 'IDE key, or server name: ' . light_cyan( $ide_key ) . PHP_EOL;
	echo 'Host: ' . light_cyan( 'http://localhost' . ( $localhost_port === '80' ? '' : ':' . $localhost_port ) ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
			. tric_plugins_dir()
			. '</light_cyan> => <light_cyan>/var/www/html/wp-content/plugins</light_cyan>' ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
		. tric_wp_dir()
		. '</light_cyan> => <light_cyan>/var/www/html</light_cyan>' );

	$default_mask = ( tric_wp_dir() === root( '/_wordpress' ) ) + 2 * ( tric_plugins_dir() === root( '/_plugins' ) );

	switch ( $default_mask ) {
		case 1:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: tric is using the default WordPress directory and a different plugins directory: ' .
				'set path mappings correctly and keep that in mind.' );
			break;
		case 2:
			echo PHP_EOL . PHP_EOL;
			echo yellow( 'Note: tric is using the default plugins directory and a different WordPress directory: ' .
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
function tric_handle_xdebug( callable $args ) {
	$run_settings_file = root( '/.env.tric.run' );
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
		write_env_file( $run_settings_file, [ $map[ $toggle ] => $var ] );
		echo PHP_EOL . PHP_EOL . colorize( "Tear down the stack with <light_cyan>down</light_cyan> and restar it to apply the new settings!\n" );

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;
	echo 'XDebug status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) );

	if ( $value === (int) getenv( 'XDE' ) ) {
		return;
	}

	$xdebug_env_vars = [ 'XDE' => $value, 'XDEBUG_DISABLE' => 1 === $value ? 0 : 1 ];
	write_env_file( $run_settings_file, $xdebug_env_vars, true );

	echo "\n\n";

	$restart_services = ask(
		'Would you like to restart the WordPress (NOT the database) and Codeception services now?',
		'yes'
	);
	if ( $restart_services ) {
		foreach ( $xdebug_env_vars as $key => $value ) {
			putenv( "{$key}={$value}" );
		}
		// Call for a hard restart to make sure the web-server will restart its php-fpm connection.
		restart_php_services( true );
	} else {
		echo colorize(
			"\n\nTear down the stack with <light_cyan>down</light_cyan> and restar it to apply the new settings!\n"
		);
	}
}

/**
 * Updates the stack images by pulling the latest version of each.
 */
function update_stack_images() {
	echo "Updating the stack images...\n\n";
	tric_realtime()( [ 'pull', '--include-deps' ] );
	echo light_cyan( "\n\nStack images updated.\n" );
}

/**
 * Maybe runs composer install on a given target
 *
 * @param string $target Target to potentially run composer install against.
 */
function tric_maybe_run_build_install( $command, $target ) {
	$run = ask(
		"\nWould you like to run the {$command} build processes for this plugin?",
		'yes'
	);

	if ( empty( $run ) ) {
		return;
	}

	$current_target = tric_target();

	if ( $current_target !== $target ) {
		tric_switch_target( $target );
	}

	$function = "\Tribe\Test\\tric_run_{$command}_command";
	$function( [ 'install' ] );

	if ( $current_target !== $target ) {
		tric_switch_target( $current_target );
	}
}

/**
 * Maybe runs composer install on a given target
 *
 * @param string $target Target to potentially run composer install against.
 */
function tric_maybe_run_composer_install( $target ) {
	return tric_maybe_run_build_install( 'composer', $target );
}

/**
 * Maybe runs npm install on a given target
 *
 * @param string $target Target to potentially run npm install against.
 */
function tric_maybe_run_npm_install( $target ) {
	return tric_maybe_run_build_install( 'npm', $target );
}

/**
 * Run a command using the `npm` service.
 *
 * If `common` is available in the target and the command dos not fail, then the user will be prompted to run the same
 * command on `common`.
 *
 * @param array<string> $command The `npm` command to run, e.g. `['install','--save-dev']` in array format.
 */
function tric_run_npm_command( array $command ) {
	$using = tric_target();
	echo light_cyan( "Using {$using}\n" );

	setup_id();
	$status = tric_realtime()( array_merge( [ 'run', '--rm', 'npm' ], $command ) );

	if ( 0 !== $status ) {
		// If the composer command failed there's no point in trying the same on `common`
		return;
	}

	if ( ! file_exists( tric_plugins_dir( "{$using}/common" ) ) ) {
		return;
	}

	if ( ask( "\nWould you like to run that npm command against common?", 'yes' ) ) {
		tric_switch_target( "{$using}/common" );

		echo light_cyan( "Temporarily using " . tric_target() . "\n" );

		tric_realtime()( array_merge( [ 'run', '--rm', 'npm' ], $command ) );

		tric_switch_target( $using );

		echo light_cyan( "Using " . tric_target() . " once again\n" );
	}
}

/**
 * Run a command using the `composer` service.
 *
 * If `common` is available in the target and the command dos not fail, then the user will be prompted to run the same
 * command on `common`.
 *
 * @param array<string> $command The `composer` command to run, e.g. `['install','--no-dev']` in array format.
 */
function tric_run_composer_command( array $command ) {
	$using = tric_target();
	echo light_cyan( "Using {$using}\n" );

	setup_id();
	$status = tric_realtime()( array_merge( [ 'run', '--rm', 'composer' ], $command ) );

	if ( 0 !== $status ) {
		// If the composer command failed there's no point in trying the same on `common`
		return;
	}

	if ( ! file_exists( tric_plugins_dir( "{$using}/common" ) ) ) {
		return;
	}

	if ( ask( "\nWould you like to run that composer command against common?", 'yes' ) ) {
		tric_switch_target( "{$using}/common" );

		echo light_cyan( "Temporarily using " . tric_target() . "\n" );

		tric_realtime()( array_merge( [ 'run', '--rm', 'composer' ], $command ) );

		tric_switch_target( $using );

		echo light_cyan( "Using " . tric_target() . " once again\n" );
	}
}

/**
 * Returns an array of arguments to correctly run a wp-cli commann in the tric stack.
 *
 * @param array<string> $command The wp-cli command to run, anything after the `wp`; e.g. `[ 'plugin', 'list' ]`.
 *
 * @return array<string> The complete command arguments, ready to be used in the `tric` or `tric_realtime` functions.
 */
function cli_command( array $command = [] ) {
	return array_merge( [ 'run', '--rm', 'cli', 'wp', '--allow-root' ], $command );
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
 * @since TBD
 *
 * @param string      $branch The name of the branch to switch to, e.g. `release/B20.03`.
 * @param string|null $plugin The slug of the plugin to switch branch for; if not specified, then the current tric
 *                            target will be used.
 */
function switch_plugin_branch( $branch, $plugin = null ) {
	$cwd = getcwd();

	if ( false === $cwd ) {
		echo magenta( "Cannot get current working directory; is it accessible?j\n" );
		exit( 1 );
	}

	$plugin     = null === $plugin ? tric_target() : $plugin;
	$plugin_dir = tric_plugins_dir( $plugin );

	echo light_cyan( "Temporarily using {$plugin}\n" );

	$changed    = chdir( $plugin_dir );

	if ( false === $changed ) {
		echo magenta( "Cannot change to directory {$plugin_dir}; is it accessible?\n" );
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
			echo magenta( "Remote branch fetch failed.\n" );
			exit( 1 );
		}
	} else {
		echo "Branch {$branch} found locally: checking it out...";
		$command = 'git checkout --recurse-submodules ' . $branch;
		if ( 0 !== process_realtime( $command ) ) {
			echo magenta( "Branch switch failed.\n" );
			exit( 1 );
		}
	}

	// Restore the current working directory to the previous value.
	echo light_cyan( 'Using ' . tric_target() . " once again\n" );
	$restored = chdir( $cwd );

	if ( false === $restored ) {
		echo magenta( "Could not restore working directory {$cwd}\n" );
		exit( 1 );
	}
}
