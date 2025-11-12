<?php
/**
 * Sets the directory target operations directory.
 * The directory could be a WordPress installation directory, the one containing the `wp-config.php` file,
 * a themes directory or a plugins directory.
 *
 * @var string $run_settings_file The absolute path to the file that should be used to load runtime settings.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets the current plugins directory to be the one used by slic.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [reset]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} here</light_cyan>
		Sets the current directory to be used within the {$cli_name} stack for selecting targets.

		<light_cyan>{$cli_name} here reset</light_cyan>
		Unsets the directory that {$cli_name} uses to select targets.
	HELP;

	echo colorize( $help );
	return;
}

$sub_args    = args( [ 'reset' ], $args( '...' ), 0 );
$reset       = $sub_args( 'reset', false );

$wp_dir      = SLIC_ROOT_DIR . '/_wordpress';
$plugins_dir = SLIC_ROOT_DIR . '/_plugins';
$themes_dir  = SLIC_ROOT_DIR . '/_wordpress/wp-content/themes';

if ( empty( $reset ) ) {
	$here_dir = getcwd();
	if ( false === $here_dir ) {
		echo magenta( "Cannot get the current working directory with 'getcwd'; please make sure it's accessible." );
		exit( 1 );
	}
} else {
	$here_dir = $plugins_dir;
}

// Normalize to absolute path
$here_dir = realpath( $here_dir );
if ( false === $here_dir ) {
	echo magenta( "Cannot resolve the directory path." );
	exit( 1 );
}

$has_wp_config = dir_has_wp_config( $here_dir );
$env_values    = [];

if ( $has_wp_config ) {
	if ( file_exists( "{$here_dir}/wp-content" ) ) {
		$wp_content_dir = "{$here_dir}/wp-content";
	} elseif ( file_exists( "{$here_dir}/content" ) ) {
		$wp_content_dir = "{$here_dir}/content";
	} else {
		echo magenta( "Cannot locate the wp-content directory. If you have a custom wp-content location, you will need to set the SLIC_WP_DIR, SLIC_PLUGINS_DIR, and SLIC_THEMES_DIR manually in slic's .env.slic.run file." );
		exit( 1 );
	}

	$env_values['SLIC_HERE_DIR'] = $here_dir;

	// Support WP skeleton.
	if ( file_exists( "{$here_dir}/wp" ) ) {
		$here_dir .= '/wp';
	}

	$env_values['SLIC_WP_DIR']       = $here_dir;
	$env_values['SLIC_PLUGINS_DIR']  = "{$wp_content_dir}/plugins";
	$env_values['SLIC_THEMES_DIR']   = "{$wp_content_dir}/themes";
} else {
	$env_values['SLIC_HERE_DIR']     = $here_dir;
	$env_values['SLIC_WP_DIR']       = $wp_dir;
	$env_values['SLIC_PLUGINS_DIR']  = $here_dir;
	$env_values['SLIC_THEMES_DIR']   = $themes_dir;
}

// When changing the here target, clear the currently selected project.
$env_values['SLIC_CURRENT_PROJECT']               = '';
$env_values['SLIC_CURRENT_PROJECT_RELATIVE_PATH'] = '';

// Load stacks.php functions
require_once __DIR__ . '/../stacks.php';

// Use the plugins directory as the stack identifier
$stack_id = $env_values['SLIC_PLUGINS_DIR'];

// Check if stack already exists
$existing_stack = slic_stacks_get( $stack_id );

if ( null !== $existing_stack ) {
	// Stack exists, update its state
	echo colorize( PHP_EOL . "<light_cyan>Stack already exists for this directory, updating configuration...</light_cyan>" . PHP_EOL );

	// Ensure XDebug configuration exists for this stack (may not exist for older stacks)
	$needs_xdebug_update = false;
	if ( ! isset( $existing_stack['xdebug_port'] ) || ! isset( $existing_stack['xdebug_key'] ) ) {
		$needs_xdebug_update = true;
		slic_stacks_update( $stack_id, [
			'xdebug_port' => slic_stacks_xdebug_port( $stack_id ),
			'xdebug_key'  => slic_stacks_xdebug_server_name( $stack_id ),
		] );
	}

	// Get the stack-specific state file
	$stack_run_file = slic_stacks_get_state_file( $stack_id );

	// Update the state file
	write_env_file( $stack_run_file, $env_values, true );

	// Reload environment
	setup_slic_env( root(), true, $stack_id );

	echo colorize( PHP_EOL . "<light_cyan>Stack configuration updated.</light_cyan>" . PHP_EOL );
	echo colorize( "Stack ID: <yellow>{$stack_id}</yellow>" . PHP_EOL );

	// Ensure ports are up-to-date from Docker if containers are running
	if ( slic_stacks_ensure_ports( $stack_id ) ) {
		$updated_stack = slic_stacks_get( $stack_id );
		echo colorize( "WordPress URL: <yellow>http://localhost:{$updated_stack['ports']['wp']}</yellow>" . PHP_EOL );
		echo colorize( "MySQL Port: <yellow>{$updated_stack['ports']['mysql']}</yellow>" . PHP_EOL );
		if ( isset( $updated_stack['ports']['redis'] ) ) {
			echo colorize( "Redis Port: <yellow>{$updated_stack['ports']['redis']}</yellow>" . PHP_EOL );
		}
	} else {
		echo colorize( "<yellow>Ports will be available after containers start.</yellow>" . PHP_EOL );
	}
	echo PHP_EOL;
} else {
	// New stack, register without ports (Docker will auto-assign when containers start)
	echo colorize( PHP_EOL . "<light_cyan>Creating new stack...</light_cyan>" . PHP_EOL );

	// Create stack state without ports - they'll be read from Docker after container start
	// Generate stack-specific XDebug configuration
	$stack_state = [
		'stack_id'     => $stack_id,
		'project_name' => slic_stacks_get_project_name( $stack_id ),
		'state_file'   => basename( slic_stacks_get_state_file( $stack_id ) ),
		'xdebug_port'  => slic_stacks_xdebug_port( $stack_id ),
		'xdebug_key'   => slic_stacks_xdebug_server_name( $stack_id ),
		'ports'        => null,
		'created_at'   => date( 'c' ),
		'status'       => 'created',
	];

	// Register the stack
	if ( ! slic_stacks_register( $stack_id, $stack_state ) ) {
		echo magenta( "Failed to register stack." );
		exit( 1 );
	}

	// Get the stack-specific state file
	$stack_run_file = slic_stacks_get_state_file( $stack_id );

	// Write the state file
	write_env_file( $stack_run_file, $env_values, true );

	// Reload environment
	setup_slic_env( root(), true, $stack_id );

	echo colorize( PHP_EOL . "<light_cyan>Stack created successfully!</light_cyan>" . PHP_EOL );
	echo colorize( "Stack ID: <yellow>{$stack_id}</yellow>" . PHP_EOL . PHP_EOL );
	echo colorize( "<yellow>Note:</yellow> Port assignments will be available after containers start." . PHP_EOL );
	echo colorize( "Run <light_cyan>slic using</light_cyan> or <light_cyan>slic stack info</light_cyan> to see port assignments after starting." . PHP_EOL . PHP_EOL );
	echo colorize( "To start using this stack, run: <light_cyan>slic use <plugin></light_cyan>" . PHP_EOL );
}

echo colorize( PHP_EOL . "If this is the first time setting this plugin path, be sure to <light_cyan>slic init <plugin></light_cyan>." );
