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
 * @param string|null $stack_id The stack to load environment for. If null, uses current stack or legacy file.
 */
function setup_slic_env( $root_dir, $reset = false, $stack_id = null ) {
	static $set;

	if ( ! $reset && $set === true ) {
		return;
	}

	$set = true;

	// Attempt legacy migration on first run
	if ( ! $reset && file_exists( __DIR__ . '/stacks.php' ) ) {
		require_once __DIR__ . '/stacks.php';
		if ( slic_stacks_migrate_legacy() ) {
			echo colorize( PHP_EOL . "<yellow>✓ Migrated existing slic configuration to multi-stack format.</yellow>" . PHP_EOL );
			echo colorize( "Your previous configuration has been backed up to .env.slic.run.backup" . PHP_EOL . PHP_EOL );
		}
	}

	// Let's declare we're performing slics.
	putenv( 'STELLAR_SLIC=1' );
	// Backwards compat
	putenv( 'TRIBE_TRIC=1' );

	putenv( 'SLIC_VERSION=' . CLI_VERSION );

	setup_architecture_env();

	backup_env_var( 'COMPOSER_CACHE_DIR' );

	// If a SLIC_PHP_VERSION env var is already set, back it up.
	if ( getenv( 'SLIC_PHP_VERSION' ) ) {
		backup_env_var( 'SLIC_PHP_VERSION' );
	}

	// Load the distribution version configuration file, the version-controlled one.
	load_env_file( $root_dir . '/.env.slic' );

	// Load the local overrides, this file is not version controlled.
	if ( file_exists( $root_dir . '/.env.slic.local' ) ) {
		load_env_file( $root_dir . '/.env.slic.local' );
	}

	// Load the current session configuration file.
	// If a stack_id is provided, load that stack's state file.
	// Otherwise, try to determine the current stack or fall back to legacy .env.slic.run.
	$run_file = null;

	if ( null !== $stack_id ) {
		$run_file = get_stack_env_file( $stack_id );
	} else {
		// Try to determine current stack
		if ( function_exists( 'slic_current_stack' ) || file_exists( __DIR__ . '/stacks.php' ) ) {
			if ( ! function_exists( 'slic_current_stack' ) ) {
				require_once __DIR__ . '/stacks.php';
			}
			$current_stack = slic_current_stack();
			if ( null !== $current_stack ) {
				$run_file = get_stack_env_file( $current_stack );
			}
		}

		// Fall back to legacy .env.slic.run file if it exists and no stack was found
		if ( null === $run_file && file_exists( $root_dir . '/.env.slic.run' ) ) {
			$run_file = $root_dir . '/.env.slic.run';
		}
	}

	if ( null !== $run_file && file_exists( $run_file ) ) {
		load_env_file( $run_file );
	}

	// Set stack-specific XDebug configuration
	// This needs to happen after loading the run file but before loading target overrides
	// so that .env.slic.local in the target can still override if needed
	$effective_stack_id = $stack_id;
	if ( null === $effective_stack_id && function_exists( 'slic_current_stack' ) ) {
		$effective_stack_id = slic_current_stack();
	}

	if ( null !== $effective_stack_id ) {
		if ( ! function_exists( 'slic_stacks_get' ) ) {
			require_once __DIR__ . '/stacks.php';
		}
		$stack = slic_stacks_get( $effective_stack_id );

		// Always set XDebug values from stack registry to ensure stack-specific values are used
		// These will override default values from .env.slic, but can still be overridden by
		// .env.slic.local files loaded later
		if ( null !== $stack ) {
			if ( isset( $stack['xdebug_port'] ) ) {
				$xdebug_port = $stack['xdebug_port'];
				putenv( "XDP={$xdebug_port}" );
			}

			if ( isset( $stack['xdebug_key'] ) ) {
				$xdebug_key = $stack['xdebug_key'];
				putenv( "XDK={$xdebug_key}" );
			}
		}
	}

	$target_path = get_project_local_path();
	if( ! empty( $target_path ) ) {
		// Load the local overrides from the target.
		if ( file_exists( $target_path . '/.env.slic.local' ) ) {
			load_env_file( $target_path . '/.env.slic.local' );
		}
	}

	// SLIC_PHP_VERSION was passed via the command line.
	$target_version = env_var_backup( 'SLIC_PHP_VERSION' );

	if ( $target_version ) {
		putenv( "SLIC_PHP_VERSION=$target_version" );
		putenv( "SLIC_PHP_CLI_VERSION=$target_version" );
	}

	// All the possible env files have been loaded, time to set the db image depending on the PHP version.
	setup_db_env();

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

	// WORKTREE SUPPORT: Set worktree-specific environment variables
	// These variables are used by slic-stack.worktree.yml to mount the worktree directory
	// into the correct container path, shadowing the base stack's version.
	$current_stack = null;
	if ( null !== $stack_id ) {
		$current_stack = $stack_id;
	} elseif ( function_exists( 'slic_current_stack' ) ) {
		$current_stack = slic_current_stack();
	}

	if ( null !== $current_stack && slic_stacks_is_worktree( $current_stack ) ) {
		$parsed = slic_stacks_parse_worktree_id( $current_stack );

		if ( null !== $parsed ) {
			// Calculate full worktree path
			$worktree_full_path = $parsed['base_path'] . '/' . $parsed['worktree_dir'];

			// Set the full path to the worktree directory
			putenv( "SLIC_WORKTREE_FULL_PATH={$worktree_full_path}" );
			$_ENV['SLIC_WORKTREE_FULL_PATH'] = $worktree_full_path;

			// Determine container path based on target type (plugin or theme)
			$stack = slic_stacks_get( $current_stack );
			if ( null !== $stack && isset( $stack['target'] ) ) {
				$target = $stack['target'];

				// Check if target is a plugin or theme
				// Plugins are in SLIC_PLUGINS_DIR, themes are in SLIC_THEMES_DIR
				$plugins_dir = getenv( 'SLIC_PLUGINS_DIR' );
				$themes_dir  = getenv( 'SLIC_THEMES_DIR' );

				$container_path = null;

				// Check if target exists in plugins directory
				if ( ! empty( $plugins_dir ) && file_exists( "{$plugins_dir}/{$target}" ) ) {
					$container_path = "plugins/{$target}";
				} elseif ( ! empty( $themes_dir ) && file_exists( "{$themes_dir}/{$target}" ) ) {
					$container_path = "themes/{$target}";
				}

				// Log error if container path could not be determined
				if ( null === $container_path ) {
					error_log( "slic: Could not determine container path for worktree target '{$target}'" );
				}

				// Set container path if determined
				if ( null !== $container_path ) {
					putenv( "SLIC_WORKTREE_CONTAINER_PATH={$container_path}" );
					$_ENV['SLIC_WORKTREE_CONTAINER_PATH'] = $container_path;
				}
			}

			// Set flag to indicate this is a worktree context
			putenv( "SLIC_IS_WORKTREE=1" );
			$_ENV['SLIC_IS_WORKTREE'] = '1';
		}
	} else {
		// Not a worktree stack - clear any stale worktree variables
		putenv( 'SLIC_IS_WORKTREE=' );
		putenv( 'SLIC_WORKTREE_FULL_PATH=' );
		putenv( 'SLIC_WORKTREE_CONTAINER_PATH=' );

		unset( $_ENV['SLIC_IS_WORKTREE'] );
		unset( $_ENV['SLIC_WORKTREE_FULL_PATH'] );
		unset( $_ENV['SLIC_WORKTREE_CONTAINER_PATH'] );
	}

	// Most commands are nested shells that should not run with a time limit.
	remove_time_limit();
}

/**
 * Sets the PHP version for the current environment.
 *
 * @param string $version The PHP version to set.
 * @param bool $require_confirm Whether to require confirmation before restarting the stack.
 * @param bool $skip_rebuild Whether to skip rebuilding the stack.
 */
function slic_set_php_version( $version, $require_confirm = false, $skip_rebuild = false ) {
	$message        = "<yellow>✓</yellow> PHP version set: <yellow>$version</yellow>";
	$staged_message = "<yellow>✓</yellow> PHP version staged for one time use: <yellow>$version</yellow>. " .
	                  "The next <light_green>slic use <project></light_green> will use this version";

	$data = [
		'SLIC_PHP_VERSION' => $version,
	];

	// Store a temporary staged variable for the next `slic use` command.
	if ( $skip_rebuild ) {
		$data = array_merge( $data, [
			'SLIC_PHP_VERSION_STAGED' => 1,
		] );

		$message = $staged_message;
	}

	$run_settings_file = root( '/.env.slic.run' );
	write_env_file( $run_settings_file, $data, true );

	echo colorize( $message . PHP_EOL );

	$confirm = true;

	if ( ! $skip_rebuild && $require_confirm ) {
		$confirm = ask("Do you want to restart the stack now? ", 'yes');
	}

	if ( ! $confirm ) {
		// If the user didn't confirm, stage the change for the next `slic use`.
		write_env_file( $run_settings_file, [ 'SLIC_PHP_VERSION_STAGED' => 1 ], true );

		echo colorize( $staged_message . PHP_EOL );

		return;
	}

	if ( $skip_rebuild ) {
		return;
	}

	rebuild_stack();
	update_stack_images();
	load_env_file( root() . '/.env.slic.run' );
	restart_php_services( true );
}

/**
 * Clears the SLIC_PHP_VERSION_STAGED flag from .env.slic.run to signal to no longer switch
 * PHP versions on the next `slic use <project>`.
 *
 * @return void
 */
function slic_clear_staged_php_flag() {
	$run_settings_file = root( '/.env.slic.run' );

	write_env_file( $run_settings_file, [ 'SLIC_PHP_VERSION_STAGED' => false ], true );
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
 * Determines the current stack to use.
 *
 * Priority order:
 * 1. Global $SLIC_STACK_OVERRIDE (set by --stack flag)
 * 2. SLIC_STACK environment variable
 * 3. Stack matching current working directory
 * 4. Single stack if only one exists
 * 5. null if no stack can be determined
 *
 * @return string|null The stack ID or null if not found.
 */
function slic_current_stack() {
	// Load stacks.php functions if not already loaded
	if ( ! function_exists( 'slic_stacks_list' ) ) {
		require_once __DIR__ . '/stacks.php';
	}

	// 1. Check global override (set by --stack flag)
	global $SLIC_STACK_OVERRIDE;
	if ( ! empty( $SLIC_STACK_OVERRIDE ) ) {
		return slic_stacks_resolve_from_path( $SLIC_STACK_OVERRIDE );
	}

	// 2. Check environment variable
	$env_stack = getenv( 'SLIC_STACK' );
	if ( ! empty( $env_stack ) ) {
		return slic_stacks_resolve_from_path( $env_stack );
	}

	// 3. Try to resolve from current working directory
	$cwd_stack = slic_stacks_resolve_from_cwd();
	if ( null !== $cwd_stack ) {
		return $cwd_stack;
	}

	// 4. If only one stack exists, use it (backward compatibility)
	$single_stack_id = slic_stacks_get_single_id();
	if ( null !== $single_stack_id ) {
		return $single_stack_id;
	}

	// 5. Auto-detect and offer to register unregistered worktrees
	$cwd = getcwd();
	$detected = slic_stacks_detect_worktree( $cwd );

	if ( $detected ) {
		echo "Detected unregistered git worktree!\n";
		echo "  Target: {$detected['target']}\n";
		echo "  Directory: {$detected['dir_name']}\n";
		echo "  Branch: {$detected['branch']}\n";
		echo "\nRegister this as a slic worktree stack? [y/N] ";

		$handle = fopen( 'php://stdin', 'r' );
		$confirmation = trim( fgets( $handle ) );
		fclose( $handle );

		if ( strtolower( $confirmation ) === 'y' ) {
			// Auto-register
			$worktree_stack_id = $detected['base_stack_id'] . '@' . $detected['dir_name'];

			$worktree_state = [
				'stack_id' => $worktree_stack_id,
				'is_worktree' => true,
				'base_stack_id' => $detected['base_stack_id'],
				'worktree_target' => $detected['target'],
				'worktree_dir' => $detected['dir_name'],
				'worktree_branch' => $detected['branch'],
				'worktree_full_path' => $detected['full_path'],
				'project_name' => slic_stacks_get_project_name( $worktree_stack_id ),
				'state_file' => slic_stacks_get_state_file( $worktree_stack_id ),
				'xdebug_port' => slic_stacks_xdebug_port( $worktree_stack_id ),
				'xdebug_key' => slic_stacks_xdebug_server_name( $worktree_stack_id ),
				'target' => $detected['target'],
				'status' => 'created',
			];

			if ( slic_stacks_register( $worktree_stack_id, $worktree_state ) ) {
				echo "Registered successfully!\n";
				return $worktree_stack_id;
			} else {
				echo "Failed to register stack.\n";
			}
		}
	}

	return null;
}

/**
 * Gets the current stack or exits with an error if not found.
 *
 * @param string|null $reason Optional reason to display if stack not found.
 * @return string The stack ID.
 */
function slic_current_stack_or_fail( $reason = null ) {
	$stack_id = slic_current_stack();

	if ( null === $stack_id ) {
		$message = "No slic stack found.";

		if ( ! empty( $reason ) ) {
			$message .= " " . $reason;
		}

		// Load stacks.php functions if not already loaded
		if ( ! function_exists( 'slic_stacks_count' ) ) {
			require_once __DIR__ . '/stacks.php';
		}

		$stack_count = slic_stacks_count();

		if ( $stack_count === 0 ) {
			$message .= PHP_EOL . "Run 'slic here' in a plugin directory to create a stack.";
		} elseif ( $stack_count > 1 ) {
			$message .= PHP_EOL . "Multiple stacks exist. Use --stack=<path> or cd to the stack directory.";
			$message .= PHP_EOL . "Run 'slic stack list' to see all available stacks.";
		}

		echo magenta( $message . PHP_EOL );
		exit( 1 );
	}

	return $stack_id;
}

/**
 * Switches the current `use` target.
 *
 * @param string $target Target to switch to.
 * @param string|null $stack_id The stack to switch target for. If null, uses current stack.
 */
function slic_switch_target( $target, $stack_id = null ) {
	$root                 = root();
	$target_relative_path = '';
	$subdir               = '';

	// Determine which stack to use
	if ( null === $stack_id ) {
		$stack_id = slic_current_stack_or_fail( "Cannot switch target without an active stack." );
	}

	// Get the stack-specific state file
	$run_settings_file = get_stack_env_file( $stack_id );

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

	// Update the stack registry with the target so worktree commands can access it
	require_once __DIR__ . '/stacks.php';
	if ( ! slic_stacks_update( $stack_id, [ 'target' => $target ] ) ) {
		echo magenta( "Warning: Could not update stack registry with target." . PHP_EOL );
		echo magenta( "Worktree commands may not work correctly." . PHP_EOL );
	}

	setup_slic_env( $root, false, $stack_id );
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
function restart_php_services( bool $hard = false ): void {
	restart_services( php_services(), $hard );
}

/**
 * Concurrently restart multiple services at once.
 *
 * @param  array<string, string>|string[]  $services  The list of services to restart, e.g. [ 'wordpress', 'slic' ],
 *                                                    or keyed by service => pretty_name, e.g. [ 'wordpress' => 'WordPress' ]
 * @param  bool                            $hard      Whether to restart the service using the `docker compose restart`
 *                                                    command or to use full tear-down and up again cycle.
 */
function restart_services( array $services, bool $hard = false ): void {
	echo colorize( sprintf( PHP_EOL . 'Restarting services %s...' . PHP_EOL, implode( ', ', $services ) ) );

	if ( isset( $services[0] ) ) {
		$service_ids = $services;
	} else {
		$service_ids = array_keys( $services );
	}

	if ( $hard ) {
		slic_realtime()( array_merge( [ 'rm', '--stop', '--force' ], $service_ids ) );
		slic_realtime()( array_merge( [ 'up', '-d' ], $service_ids ) );
	} else {
		slic_realtime()( array_merge( [ 'restart' ], $service_ids ) );
	}

	echo colorize( PHP_EOL .
	               sprintf(
					   "✅ <light_cyan>%s service%s restarted.</light_cyan>",
					   implode( ', ', $services ),
					   count( $services ) > 1 ? 's' : ''
	               ) . PHP_EOL );
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
 * Returns the absolute path to the current mu-plugins directory slic is using.
 *
 * @param string $path An optional path to append to the current slic mu-plugins directory.
 *
 * @return string The absolute path to the current mu-plugins directory slic is using.
 */
function slic_mu_plugins_dir( $path = '' ) {
	return slic_content_type_dir( 'mu-plugins', $path );
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
	} elseif ( 'mu-plugins' === $content_type ) {
		$default_path = '/_wordpress/wp-content/mu-plugins';
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
function slic_passive( $stack_id = null ) {
	return docker_compose_passive( slic_stack_array( false, $stack_id ), $stack_id );
}

/**
 * Runs a process in slic stack and returns the exit status.
 *
 * @param string|null $stack_id The stack to run the command for. If null, uses current stack.
 * @return \Closure The process closure to start a real-time process using slic stack.
 */
function slic_realtime( $stack_id = null ) {
	return docker_compose_realtime( slic_stack_array( false, $stack_id ), $stack_id );
}

/**
 * Returns the process Closure to start a real-time process using slic stack.
 *
 * @param string|null $stack_id The stack to run the command for. If null, uses current stack.
 * @return \Closure The process closure to start a real-time process using slic stack.
 */
function slic_process( $stack_id = null ) {
	return docker_compose( slic_stack_array( false, $stack_id ), $stack_id );
}

/**
 * Tears down slic stack.
 *
 * @param bool $passive Whether to run the command passively or in realtime.
 * @param string|null $stack_id The stack to tear down. If null, uses current stack.
 */
function teardown_stack( $passive = false, $stack_id = null ) {
	if ( $passive ) {
		return slic_passive( $stack_id )( [ 'down', '--volumes', '--remove-orphans' ] );
	}

	return slic_realtime( $stack_id )( [ 'down', '--volumes', '--remove-orphans' ] );
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
		'SLIC_PHP_VERSION_STAGED',
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

	// Read .env.slic.run directly to show runtime state.
	$run_env_file = root( '/.env.slic.run' );
	$run_env      = [];
	if ( file_exists( $run_env_file ) ) {
		$run_env = read_env_file( $run_env_file );
	}

	echo colorize( "<yellow>Configuration read from the following files:</yellow>" . PHP_EOL );
	$slic_root   = root();
	$target_path = get_project_local_path();
	echo implode( PHP_EOL, array_filter( [
		file_exists( $slic_root . '/.env.slic' ) ? "  - " . $slic_root . '/.env.slic' : null,
		file_exists( $slic_root . '/.env.slic.local' ) ? "  - " . $slic_root . '/.env.slic.local' : null,
		file_exists( $target_path . '/.env.slic.local' ) ? "  - " . $target_path . '/.env.slic.local' : null,
		file_exists( $slic_root . '/.env.slic.run' ) ? "  - " . $slic_root . '/.env.slic.run' : null,
	] ) ) . PHP_EOL . PHP_EOL;

	echo colorize( "<yellow>Current configuration:</yellow>" . PHP_EOL );
	foreach ( $config_vars as $key ) {
		$effective_value = getenv( $key );
		$runtime_value   = $run_env[ $key ] ?? null;

		// Show runtime value if it exists, otherwise effective value
		$value = $runtime_value ? print_r( $runtime_value, true ) : print_r( $effective_value, true );

		if ( $key === 'SLIC_PLUGINS_DIR' && $value !== slic_plugins_dir() ) {
			// If the configuration is using a relative path, then expose the absolute path.
			$value .= ' => ' . slic_plugins_dir();
		}

		// Show if there's a mismatch between effective and runtime (something is overriding).
		if ( $runtime_value && $runtime_value !== $effective_value ) {
			$value .= colorize( " [runtime] <yellow>⚠ {$effective_value} [configured]</yellow>" );
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
 *
 * @param string|null $stack_id The stack to show XDebug status for. If null, uses current stack.
 */
function xdebug_status( $stack_id = null ) {
	// Determine which stack to show status for
	if ( null === $stack_id && function_exists( 'slic_current_stack' ) ) {
		$stack_id = slic_current_stack();
	}

	// Explicitly reload environment for the target stack to ensure correct values
	setup_slic_env( root(), true, $stack_id );

	$enabled = getenv( 'XDE' );
	$ide_key = getenv( 'XDK' );
	if ( empty( $ide_key ) ) {
		$ide_key = 'slic';
	}

	// Get the WordPress port from the stack registry if available
	$localhost_port = null;
	if ( null !== $stack_id ) {
		if ( ! function_exists( 'slic_stacks_ensure_ports' ) ) {
			require_once __DIR__ . '/stacks.php';
		}
		slic_stacks_ensure_ports( $stack_id );  // Refresh ports from Docker
		$stack = slic_stacks_get( $stack_id );
		if ( null !== $stack && isset( $stack['ports']['wp'] ) ) {
			$localhost_port = $stack['ports']['wp'];
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
	if ( ! function_exists( 'slic_stacks_list' ) ) {
		require_once __DIR__ . '/stacks.php';
	}
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
	echo 'Remote port: ' . light_cyan( getenv( 'XDP' ) ) . PHP_EOL;

	echo 'IDE Key (server name): ' . light_cyan( $ide_key ) . PHP_EOL;
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
	// Get the current stack's run file
	$stack_id = slic_current_stack();
	if ( null !== $stack_id ) {
		$run_settings_file = get_stack_env_file( $stack_id );
	} else {
		// Fall back to legacy file if no stack
		$run_settings_file = root( '/.env.slic.run' );
	}
	$toggle = $args( 'toggle', 'on' );

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
 * @return bool Whether the current system is ARM-based, or not.
 */
function is_arm64() {
    $arm64_architecture_file = __DIR__ . '/../.architecture_arm64';
    $x86_architecture_file   = __DIR__ . '/../.architecture_x86';

	if ( is_file($arm64_architecture_file) ) {
	    return true;
	}

	if ( is_file($x86_architecture_file) ) {
	    return false;
	}

    $is_64bit = PHP_INT_SIZE === 8;

    $machine_type = strtolower(php_uname('m'));

	// Non 64bit machines are not supported.
    $is_arm64 = $is_64bit
                && (strpos($machine_type, 'aarch64') !== false || strpos($machine_type, 'arm64') !== false);

    if ($is_arm64) {
        touch($arm64_architecture_file);
        return true;
    }

    touch($x86_architecture_file);

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
		putenv( 'SLIC_CHROME_IMAGE=seleniarm/standalone-chromium:4.20.0-20240427' );
	} else {
		putenv( 'SLIC_ARCHITECTURE=x86' );
		putenv( 'SLIC_CHROME_IMAGE=selenium/standalone-chrome:3.141.59' );
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

/**
 * Sets up the environment variables related to the database.
 *
 * Specifically, the function will use MySQL 5.5.62 image when the PHP version used by `slic` is 7.4
 * unless the env var `SLIC_DB_NO_MIN` is set to a non-empty value or the `SLIC_DB_IMAGE` env var
 * is set to point to a specific database image.
 *
 * @since TBD
 *
 * @return void
 */
function setup_db_env() {
	$php_version = getenv( 'SLIC_PHP_VERSION' ) ?: '7.4';

	if ( $php_version !== '7.4' ) {
		// MySQL 5.5 is only used for PHP 7.4.
		return;
	}

	// Was a db image explicitly set?
	$mysql_image = getenv( 'SLIC_DB_IMAGE' ) ?: null;

	if ( $mysql_image ) {
		// The db image was explicitly set, we're not overriding it.
		return;
	}

	$db_no_min = getenv( 'SLIC_DB_NO_MIN' ) ?: false;

	if ( $db_no_min ) {
		// The env var not to use the minimum db image was set, we're not overriding it.
		return;
	}

	/*
	 * Use the latest version of the minimum database version supported by WordPress.
	 * The image only comes in linux/amd64, so we need to use the x86 image.
	 * arm64 machines that cannot run it will fail at the docker level with a message.
	 * The function is not trying to guess what combination of architecture and OS will
	 * support it, and it should not.
	 */
	putenv( 'SLIC_DB_IMAGE=mysql:5.5.62' );
	putenv( 'SLIC_DB_PLATFORM=linux/amd64' );
}
