<?php
/**
 * XDebug configuration and management functions.
 */

namespace StellarWP\Slic;

require_once __DIR__ . '/stacks.php';
require_once __DIR__ . '/services.php';

/**
 * Displays the current XDebug configuration status.
 *
 * Note: This function assumes the environment has already been set up via setup_slic_env().
 * Callers should ensure setup_slic_env() is called before invoking this function to ensure
 * correct environment values are used.
 *
 * @since 3.0.0
 *
 * @param string|null $stack_id Optional stack ID to show status for.
 */
function xdebug_status( $stack_id = null ) {
	// Determine which stack to show status for
	if ( null === $stack_id ) {
		$stack_id = slic_current_stack();
	}

	$enabled = getenv( 'XDE' );

	if ( null !== $stack_id ) {
		// Use a stack-specific server name if a stack is detected
		// The XDK env var will be used as root to build an IDE key like `${XDK:-slic}_<stack_hash>`.
		$ide_key = slic_stacks_xdebug_server_name( $stack_id );
	} else {
		// Final fallback when no stack is detected
		$ide_key = 'slic';
	}

	// Get the WordPress port from the stack registry if available
	$localhost_port = null;
	$stack_has_localhost_port = false;
	if ( null !== $stack_id ) {
		slic_stacks_ensure_ports( $stack_id );  // Refresh ports from Docker
		$stack = slic_stacks_get( $stack_id );
		if ( null !== $stack && isset( $stack['ports']['wp'] ) ) {
			$localhost_port = $stack['ports']['wp'];
			$stack_has_localhost_port = true;
		}
	}

	// Fall back to WORDPRESS_HTTP_PORT env var or default
	if ( empty( $localhost_port ) ) {
		$localhost_port = getenv( 'WORDPRESS_HTTP_PORT' );
	}
	if ( empty( $localhost_port ) ) {
		$localhost_port = '8888';
	}

	// Show current stack information if multi-stack is active
	if ( function_exists( 'slic_stacks_list' ) ) {
		$all_stacks = slic_stacks_list();
		$stack_count = count( $all_stacks );

		// Helper function to display stack information
		$display_stack_info = function( $stack_id, $label_prefix = 'Stack' ) {
			if ( ! function_exists( 'slic_stacks_get' ) ) {
				echo colorize( '<red>Warning: Stack functions not available.</red>' ) . PHP_EOL;
				return;
			}
			$stack = slic_stacks_get( $stack_id );
			if ( null !== $stack ) {
				echo colorize( $label_prefix . ': <yellow>' . $stack_id . '</yellow>' ) . PHP_EOL;
				echo colorize( 'Project: <light_cyan>' . $stack['project_name'] . '</light_cyan>' ) . PHP_EOL . PHP_EOL;
			} else {
				echo colorize( '<red>Warning: Stack ID "' . $stack_id . '" not found.</red>' ) . PHP_EOL;
				echo colorize( '<yellow>Showing global XDebug settings from .env.slic files.</yellow>' ) . PHP_EOL . PHP_EOL;
			}
		};

		if ( $stack_count > 1 ) {
			// Multiple stacks exist
			if ( null !== $stack_id ) {
				// Show which stack we're displaying config for
				$display_stack_info( $stack_id, 'XDebug configuration for stack' );
			} else {
				// No active stack found
				echo colorize( '<yellow>No active stack found. Showing global XDebug settings from .env.slic files.</yellow>' ) . PHP_EOL . PHP_EOL;
			}
		} elseif ( $stack_count === 1 ) {
			// Single stack - backward compatible display
			if ( null !== $stack_id ) {
				$display_stack_info( $stack_id );
			}
		}
		// If no stacks exist, don't show stack info at all
	}

	echo 'XDebug status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
	echo 'Remote host: ' . light_cyan( getenv( 'XDH' ) ) . PHP_EOL;
	$remote_port = $stack_id ? slic_stacks_xdebug_port($stack_id) : getenv('XDP');
	echo 'Remote port: ' . light_cyan( $remote_port ) . PHP_EOL;

	echo 'IDE Key (server name): ' . light_cyan( $ide_key ) . PHP_EOL;
	echo colorize( PHP_EOL . "You can override these values in the <light_cyan>.env.slic.local" .
	               "</light_cyan> file or by using the " .
	               "<light_cyan>'xdebug (host|key|port) <value>'</light_cyan> command." ) . PHP_EOL;


	echo PHP_EOL . 'Set up, in your IDE, a server with the following parameters to debug PHP requests:' . PHP_EOL;
	echo 'IDE key, or server name: ' . light_cyan( $ide_key ) . PHP_EOL;
	if ($stack_id !== null) {
		if($stack_has_localhost_port){
			echo 'Host: ' . light_cyan( 'http://localhost' . ( $localhost_port === '80' ? '' : ':' . $localhost_port ) ) . PHP_EOL;
		} else {
			echo 'Host: ' . yellow( 'not available until containers start' ) . PHP_EOL;
		}
	} else {
		echo 'Host: ' . light_cyan( 'http://localhost' . ( $localhost_port === '80' ? '' : ':' . $localhost_port ) ) . PHP_EOL;
	}
	echo colorize( 'Path mapping (host => server): <light_cyan>'
	               . slic_plugins_dir()
	               . '</light_cyan> => <light_cyan>'
	               . ( getenv( 'SLIC_WP_CONTENT_CONTAINER_DIR' ) ?: '/var/www/html/wp-content' )
	               . '/plugins</light_cyan>' ) . PHP_EOL;
	echo colorize( 'Path mapping (host => server): <light_cyan>'
	               . slic_wp_dir()
	               . '</light_cyan> => <light_cyan>/var/www/html</light_cyan>' );

	// Add worktree-specific path mapping if this is a worktree stack
	if ( null !== $stack_id && slic_stacks_is_worktree( $stack_id ) ) {
		// Get stack data (reuse if already fetched)
		if ( ! isset( $stack ) ) {
			$stack = slic_stacks_get( $stack_id );
		}

		if ( ! empty( $stack['is_worktree'] ) ) {
			// Validate required fields - corrupted stack entries may be missing these
			$worktree_path = $stack['worktree_full_path'] ?? null;
			$base_stack_id = $stack['base_stack_id'] ?? null;

			// Only proceed if we have all required data
			if ( ! empty( $worktree_path ) && ! empty( $base_stack_id ) ) {
				// Determine type by checking if base_stack_id is under plugins or themes directory
				// base_stack_id is the absolute path to the base stack directory
				$plugins_dir = realpath( slic_plugins_dir() );
				$themes_dir = realpath( slic_themes_dir() );
				$base_path = realpath( $base_stack_id );

				$is_plugin = null;
				if ( $base_path && $plugins_dir && strpos( $base_path, $plugins_dir ) === 0 ) {
					$is_plugin = true;
				} elseif ( $base_path && $themes_dir && strpos( $base_path, $themes_dir ) === 0 ) {
					$is_plugin = false; // It's a theme
				}

				// Only display mapping if we successfully determined the type
				if ( $is_plugin !== null ) {
					// Get the worktree target (plugin or theme name)
					$target = ! empty( $stack['worktree_target'] ) ? $stack['worktree_target'] : $stack['target'];

					// Validate target is not empty
					if ( ! empty( $target ) ) {
						// Build the container path
						if ( $is_plugin ) {
							$container_path = '/var/www/html/wp-content/plugins/' . $target;
						} else {
							$container_path = '/var/www/html/wp-content/themes/' . $target;
						}

						// Display the worktree path mapping
						echo PHP_EOL . colorize( 'Path mapping (host => server): <light_cyan>'
						                         . $worktree_path
						                         . '</light_cyan> => <light_cyan>' . $container_path . '</light_cyan>' );
					}
				}
			}
		}
	}

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
		case 3:
		default:
			break;
	}
}

/**
 * Returns the run file toggles like Xdebug and PCOV persist to: the current stack's file, or the legacy global one.
 *
 * @return string Absolute path to the run settings file.
 */
function slic_toggle_run_file() {
	$stack_id = slic_current_stack();

	return null !== $stack_id ? get_stack_env_file( $stack_id ) : root( '/.env.slic.run' );
}

/**
 * Returns whether Xdebug is enabled in slic configuration or in a running PHP service.
 *
 * @return bool Whether Xdebug is enabled.
 */
function xdebug_is_enabled() {
	if ( (int) getenv( 'XDE' ) === 1 ) {
		return true;
	}

	foreach ( [ 'slic', 'wordpress' ] as $service ) {
		if ( ! service_running( $service ) ) {
			continue;
		}

		$modules = slic_process()( [ 'exec', '-T', $service, 'php', '-m' ] );

		if ( 0 === $modules( 'status' ) && preg_match( '/^xdebug$/mi', $modules( 'string_output' ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Handles the XDebug command request.
 *
 * @since 3.0.0
 *
 * @param callable $args    The closure that will produce the current XDebug request arguments.
 * @param bool     $confirm Whether to auto-confirm disabling PCOV when enabling Xdebug.
 */
function slic_handle_xdebug( callable $args, $confirm = false ) {
	$stack_id          = slic_current_stack();
	$run_settings_file = slic_toggle_run_file();
	$toggle            = $args( 'toggle', 'on' );

	if ( 'status' === $toggle ) {
		xdebug_status( $stack_id );

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

	if ( 1 === $value && pcov_is_enabled() ) {
		echo magenta( 'Xdebug cannot be enabled while PCOV is enabled.' ) . PHP_EOL;
		$disable_pcov = $confirm || ask( 'Disable PCOV and enable Xdebug?', 'yes' );

		if ( ! $disable_pcov ) {
			echo magenta( 'Xdebug was not enabled. Run `slic pcov off` first, then run `slic xdebug on`.' ) . PHP_EOL;
			exit( 1 );
		}

		slic_handle_pcov( static function ( $key, $default = null ) {
			return 'toggle' === $key ? 'off' : $default;
		} );
	}

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
 * Prints the current PCOV status to screen.
 */
function pcov_status() {
	$enabled = getenv( 'PCOV_ENABLED' ) === '1';

	echo 'PCOV status is: ' . ( $enabled ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;
}

/**
 * Returns whether PCOV is enabled in slic configuration or in a running PHP service.
 *
 * @return bool Whether PCOV is enabled.
 */
function pcov_is_enabled() {
	if ( (int) getenv( 'PCOV_ENABLED' ) === 1 ) {
		return true;
	}

	foreach ( [ 'slic', 'wordpress' ] as $service ) {
		if ( ! service_running( $service ) ) {
			continue;
		}

		$modules = slic_process()( [ 'exec', '-T', $service, 'php', '-m' ] );

		if ( 0 === $modules( 'status' ) && preg_match( '/^pcov$/mi', $modules( 'string_output' ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Handles the PCOV command request.
 *
 * @param callable $args    The closure that will produce the current PCOV request arguments.
 * @param bool     $confirm Whether to auto-confirm disabling Xdebug when enabling PCOV.
 */
function slic_handle_pcov( callable $args, $confirm = false ) {
	$run_settings_file = slic_toggle_run_file();
	$toggle            = $args( 'toggle', 'status' );

	if ( ! in_array( $toggle, [ 'on', 'off', 'status' ], true ) ) {
		echo magenta( "Invalid PCOV option: {$toggle}. Expected one of: on, off, status." ) . PHP_EOL;
		exit( 1 );
	}

	if ( 'status' === $toggle ) {
		pcov_status();

		return;
	}

	$value = 'on' === $toggle ? 1 : 0;

	if ( 1 === $value && xdebug_is_enabled() ) {
		echo magenta( 'PCOV cannot be enabled while Xdebug is enabled.' ) . PHP_EOL;
		$disable_xdebug = $confirm || ask( 'Disable Xdebug and enable PCOV?', 'yes' );

		if ( ! $disable_xdebug ) {
			echo magenta( 'PCOV was not enabled. Run `slic xdebug off` first, then run `slic pcov on`.' ) . PHP_EOL;
			exit( 1 );
		}

		slic_handle_xdebug( static function ( $key, $default = null ) {
			return 'toggle' === $key ? 'off' : $default;
		} );
	}

	echo 'PCOV status: ' . ( $value ? light_cyan( 'on' ) : magenta( 'off' ) ) . PHP_EOL;

	if ( $value !== (int) getenv( 'PCOV_ENABLED' ) ) {
		write_env_file( $run_settings_file, [ 'PCOV_ENABLED' => $value ], true );
	}

	foreach ( [ 'slic', 'wordpress' ] as $service ) {
		if ( ! service_running( $service ) ) {
			continue;
		}

		echo PHP_EOL;

		if ( $value === 1 ) {
			echo colorize( "Enabling PCOV in <light_cyan>{$service}</light_cyan>..." );
			slic_realtime()( [ 'exec', $service, 'pcov-on' ] );
		} else {
			echo colorize( "Disabling PCOV in <light_cyan>{$service}</light_cyan>..." );
			slic_realtime()( [ 'exec', $service, 'pcov-off' ] );
		}
	}
}

/**
 * Returns an array of environment variables to set up XDebug for the current stack, if any.
 *
 * @since TBD
 *
 * @param string|null $stack_id The stack ID to get the env vars for.
 *
 * @return array{XDP?: string, XDE?: string} A map of the XDebug env vars to set, if any,
 */
function xdebug_get_env_vars($stack_id = null):array{
	if ( null === $stack_id ) {
		return [];
	}

	$stack = slic_stacks_get( $stack_id );

	// Always set XDebug values from stack registry to ensure stack-specific values are used
	// These will override default values from .env.slic, but can still be overridden by
	// .env.slic.local files loaded later
	if ( null == $stack ) {
		return [];
	}

	$env_vars = [];

	if ( isset( $stack['xdebug_port'] ) ) {
		$env_vars['XDP']	 = $stack['xdebug_port'];
	}

	if ( isset( $stack['xdebug_key'] ) ) {
		$env_vars['XDK']	 = $stack['xdebug_key'];
	}

	return $env_vars;
}

/**
 * Sets up XDebug environment variables from the stack registry.
 *
 * This function loads XDebug configuration from the stack registry and sets
 * the appropriate environment variables (XDP, XDK, XDH). It should be called
 * during environment setup to ensure stack-specific XDebug values are used.
 *
 * Early return when $stack_id is null is correct behavior - without a stack ID,
 * there's no stack-specific XDebug configuration to load from the registry.
 * In this case, default XDebug values from .env.slic files will be used.
 *
 * @since 3.0.0
 *
 * @param string|null $stack_id Optional stack ID. If not provided, no stack-specific values are loaded.
 */
function xdebug_setup_env_vars( $stack_id = null ) {
	$env_vars = xdebug_get_env_vars( $stack_id );

	foreach($env_vars as $key => $value){
		putenv( $key . '=' . $value );
	}
}

/**
 * Sets up the XDebug host environment variable for Linux.
 *
 * On Linux, the host.docker.internal hostname needs to be explicitly set
 * for XDebug to work properly. This function sets the 'host' environment
 * variable to the XDH value or falls back to 'host.docker.internal'.
 *
 * This function is called during Docker Compose setup (in docker_compose() function)
 * to ensure the Linux host environment is properly configured before starting containers.
 *
 * This should only be called on Linux systems.
 *
 * @since 3.0.0
 */
function xdebug_setup_linux_host() {
	if ( PHP_OS === 'Linux' ) {
		putenv( sprintf( 'host=%s', getenv( 'XDH' ) ?: 'host.docker.internal' ) );
	}
}

/**
 * Returns the list of XDebug-related environment variables for display.
 *
 * This function is used by slic_info() to determine which XDebug environment
 * variables should be displayed to the user when they run the 'info' command.
 *
 * @since 3.0.0
 *
 * @return array Array of XDebug environment variable names.
 */
function xdebug_get_info_vars() {
	return [
		'XDK',
		'XDE',
		'XDH',
		'XDP',
	];
}
