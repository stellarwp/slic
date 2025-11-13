<?php
/**
 * Handles the `stack` command and its subcommands.
 */

namespace StellarWP\Slic;

require_once __DIR__ . '/../stacks.php';

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Manage multiple slic stacks.

	USAGE:

		<yellow>{$cli_name} {$subcommand} <subcommand> [<stack>]</yellow>

	SUBCOMMANDS:

		<light_cyan>list</light_cyan>
		Show all registered stacks with their directories, targets, ports, and status.

		<light_cyan>stop [<stack>]</light_cyan>
		Stop a specific stack. If no stack is provided, prompts to choose one.

		<light_cyan>stop all</light_cyan>
		Stop all registered stacks with confirmation.

		<light_cyan>info [<stack>]</light_cyan>
		Show detailed information about a stack. If no stack is provided, uses current stack.

	EXAMPLES:

		<light_cyan>{$cli_name} stack list</light_cyan>
		Show all registered stacks.

		<light_cyan>{$cli_name} stack stop /Users/Alice/project/wp-content/plugins</light_cyan>
		Stop the specified stack.

		<light_cyan>{$cli_name} stack stop all</light_cyan>
		Stop all registered stacks.

		<light_cyan>{$cli_name} stack info</light_cyan>
		Show information about the current stack.
	HELP;

	echo colorize( $help );
	return;
}

// Parse subcommand and arguments
$sub_args    = args( [ 'subcommand', 'stack_path' ], $args( '...' ), 0 );
$subcommand  = $sub_args( 'subcommand', 'list' );
$stack_path  = $sub_args( 'stack_path', null );

// Resolve stack path if provided (unless it's 'all' for stop command)
$target_stack_id = null;
if ( null !== $stack_path && ! ( $subcommand === 'stop' && $stack_path === 'all' ) ) {
	$target_stack_id = slic_stacks_resolve_from_path( $stack_path );
	if ( null === $target_stack_id ) {
		echo magenta( "Stack not found: {$stack_path}" . PHP_EOL );
		exit( 1 );
	}
}

switch ( $subcommand ) {
	case 'list':
		command_stack_list();
		break;

	case 'stop':
		if ( $stack_path === 'all' ) {
			command_stack_stop_all();
		} else {
			command_stack_stop( $target_stack_id );
		}
		break;

	case 'info':
		command_stack_info( $target_stack_id );
		break;

	default:
		echo magenta( "Unknown subcommand: {$subcommand}" . PHP_EOL );
		echo "Run '{$cli_name} stack help' for usage information." . PHP_EOL;
		exit( 1 );
}

/**
 * Lists all registered stacks.
 */
function command_stack_list() {
	slic_display_stacks_nested();
}

/**
 * Displays stacks in a nested, hierarchical view showing base stacks and their worktrees.
 */
function slic_display_stacks_nested() {
	$current_stack = slic_current_stack();
	$stacks = slic_stacks_list();

	// Separate base stacks and worktrees
	$base_stacks = [];
	$worktree_map = [];

	foreach ( $stacks as $stack_id => $state ) {
		if ( ! empty( $state['is_worktree'] ) ) {
			$base_id = $state['base_stack_id'];
			if ( ! isset( $worktree_map[ $base_id ] ) ) {
				$worktree_map[ $base_id ] = [];
			}
			$worktree_map[ $base_id ][ $stack_id ] = $state;
		} else {
			$base_stacks[ $stack_id ] = $state;
		}
	}

	// Display
	if ( empty( $base_stacks ) ) {
		echo "No stacks registered.\n";
		return;
	}

	foreach ( $base_stacks as $stack_id => $state ) {
		$status_icon = ( $state['status'] === 'running' ) ? '●' : '○';

		echo "$status_icon $stack_id" . ($stack_id === $current_stack ? ' <-current' : '') . "\n";
		echo "    Status: {$state['status']}\n";
		echo "    Target: " . ( $state['target'] ?? 'none' ) . "\n";
		echo "    XDebug: {$state['xdebug_port']}\n";

		// Show worktrees if any
		if ( isset( $worktree_map[ $stack_id ] ) ) {
			echo "    Worktrees:\n";

			foreach ( $worktree_map[ $stack_id ] as $wt_id => $wt_state ) {
				$wt_status_icon = ( $wt_state['status'] === 'running' ) ? '●' : '○';
				echo "      $wt_status_icon {$wt_state['worktree_dir']} ({$wt_state['worktree_branch']})" . ($wt_id === $current_stack ? ' <-current' : '') . "\n";
				echo "         XDebug: {$wt_state['xdebug_port']}\n";
			}
		}

		echo "\n";
	}
}

/**
 * Stops a specific stack.
 *
 * @param string|null $stack_id The stack to stop, or null to prompt.
 */
function command_stack_stop( $stack_id = null ) {
	$stacks = slic_stacks_list();

	if ( empty( $stacks ) ) {
		echo magenta( "No stacks registered." . PHP_EOL );
		return;
	}

	// If no stack specified and multiple exist, prompt user
	if ( null === $stack_id && count( $stacks ) > 1 ) {
		echo colorize( "<yellow>Multiple stacks exist. Please specify which stack to stop:</yellow>" . PHP_EOL . PHP_EOL );
		command_stack_list();
		echo colorize( "Usage: <light_cyan>slic stack stop <stack-path></light_cyan>" . PHP_EOL );
		return;
	}

	// If no stack specified and only one exists, use it
	if ( null === $stack_id ) {
		$stack_id = slic_stacks_get_single_id();
	}

	if ( null === $stack_id ) {
		echo magenta( "No stack to stop." . PHP_EOL );
		return;
	}

	// Verify stack exists
	if ( ! isset( $stacks[ $stack_id ] ) ) {
		echo magenta( "Stack not found: {$stack_id}" . PHP_EOL );
		return;
	}

	echo colorize( "Stopping stack: <yellow>{$stack_id}</yellow>" . PHP_EOL );

	// Call the command_stop function with the specific stack
	$status = command_stop( $stack_id );

	exit( $status );
}

/**
 * Stops all registered stacks with confirmation.
 *
 * This command prompts the user for confirmation before stopping all stacks.
 * It calls command_stop() for each stack without immediately unregistering them,
 * then unregisters only the successfully stopped stacks at the end to avoid
 * modifying the registry during iteration.
 *
 * @return void Exits with 0 on success, 1 on failure.
 */
function command_stack_stop_all() {
	// Validate stdin is interactive
	if ( ! defined( 'STDIN' ) || ! stream_isatty( STDIN ) ) {
		echo colorize( "<red>Error: This command requires interactive input. Cannot run in non-interactive mode.</red>" . PHP_EOL );
		exit( 1 );
	}

	$stacks = slic_stacks_list();

	if ( empty( $stacks ) ) {
		echo colorize( "<yellow>No stacks registered.</yellow>" . PHP_EOL );
		return;
	}

	$count = count( $stacks );
	$stack_word = $count === 1 ? 'stack' : 'stacks';

	echo colorize( PHP_EOL . "<yellow>The following {$count} {$stack_word} will be stopped:</yellow>" . PHP_EOL . PHP_EOL );

	foreach ( $stacks as $stack_id => $stack ) {
		echo colorize( "  - <light_cyan>{$stack_id}</light_cyan> ({$stack['project_name']})" . PHP_EOL );
	}

	echo PHP_EOL;
	echo colorize( "<yellow>Are you sure you want to stop all stacks? (y/N):</yellow> " );

	// Read user input
	$handle = fopen( "php://stdin", "r" );
	$response = trim( fgets( $handle ) );
	fclose( $handle );

	if ( ! in_array( strtolower( $response ), [ 'y', 'yes' ], true ) ) {
		echo colorize( "<yellow>Operation cancelled.</yellow>" . PHP_EOL );
		exit( 0 );
	}

	echo PHP_EOL;

	$results = [
		'success' => [],
		'failed'  => [],
	];

	foreach ( $stacks as $stack_id => $stack ) {
		echo colorize( "Stopping stack: <yellow>{$stack_id}</yellow>" . PHP_EOL );

		// Stop without unregistering to avoid modifying registry during iteration
		$status = command_stop( $stack_id, false );

		if ( $status === 0 ) {
			$results['success'][] = $stack_id;
		} else {
			$results['failed'][] = $stack_id;
		}

		echo PHP_EOL;
	}

	// Unregister all successfully stopped stacks
	foreach ( $results['success'] as $stack_id ) {
		if ( slic_stacks_unregister( $stack_id ) ) {
			echo colorize( "Stack unregistered: <yellow>{$stack_id}</yellow>" . PHP_EOL );
		}
	}

	// Display summary
	$success_count = count( $results['success'] );
	$failed_count = count( $results['failed'] );

	echo colorize( PHP_EOL . "<light_cyan>Summary:</light_cyan>" . PHP_EOL );

	if ( $success_count > 0 ) {
		$success_word = $success_count === 1 ? 'stack' : 'stacks';
		echo colorize( "  <green>Successfully stopped {$success_count} {$success_word}</green>" . PHP_EOL );
	}

	if ( $failed_count > 0 ) {
		$failed_word = $failed_count === 1 ? 'stack' : 'stacks';
		echo colorize( "  <red>Failed to stop {$failed_count} {$failed_word}:</red>" . PHP_EOL );
		foreach ( $results['failed'] as $failed_stack ) {
			echo colorize( "    - <yellow>{$failed_stack}</yellow>" . PHP_EOL );
		}
		exit( 1 );
	}

	exit( 0 );
}

/**
 * Shows detailed information about a stack.
 *
 * @param string|null $stack_id The stack to show info for, or null for current stack.
 */
function command_stack_info( $stack_id = null ) {
	// If no stack specified, use current
	if ( null === $stack_id ) {
		$stack_id = slic_current_stack();
	}

	if ( null === $stack_id ) {
		echo magenta( "No stack specified and no current stack found." . PHP_EOL );
		echo colorize( "Run <light_cyan>slic here</light_cyan> to create a stack or specify a stack path." . PHP_EOL );
		return;
	}

	$stack = slic_stacks_get( $stack_id );

	if ( null === $stack ) {
		echo magenta( "Stack not found: {$stack_id}" . PHP_EOL );
		return;
	}

	echo colorize( PHP_EOL . "<light_cyan>Stack Information:</light_cyan>" . PHP_EOL . PHP_EOL );
	echo colorize( "<yellow>Stack ID:</yellow> {$stack_id}" . PHP_EOL );
	echo colorize( "<yellow>Project Name:</yellow> {$stack['project_name']}" . PHP_EOL );
	echo colorize( "<yellow>State File:</yellow> {$stack['state_file']}" . PHP_EOL );

	// Show current target if set
	$stack_env_file = slic_stacks_get_state_file( $stack_id );
	if ( file_exists( $stack_env_file ) ) {
		echo colorize( "<yellow>Environment File:</yellow> {$stack_env_file}" . PHP_EOL );

		$env_data = read_env_file( $stack_env_file );
		if ( isset( $env_data['SLIC_CURRENT_PROJECT'] ) && ! empty( $env_data['SLIC_CURRENT_PROJECT'] ) ) {
			$target = $env_data['SLIC_CURRENT_PROJECT'];
			if ( isset( $env_data['SLIC_CURRENT_PROJECT_SUBDIR'] ) && ! empty( $env_data['SLIC_CURRENT_PROJECT_SUBDIR'] ) ) {
				$target .= '/' . $env_data['SLIC_CURRENT_PROJECT_SUBDIR'];
			}
			echo colorize( "<yellow>Current Target:</yellow> {$target}" . PHP_EOL );
		} else {
			echo colorize( "<yellow>Current Target:</yellow> <light_cyan>not set</light_cyan>" . PHP_EOL );
		}

		// Show directories
		if ( isset( $env_data['SLIC_WP_DIR'] ) ) {
			echo colorize( "<yellow>WordPress Dir:</yellow> {$env_data['SLIC_WP_DIR']}" . PHP_EOL );
		}
		if ( isset( $env_data['SLIC_PLUGINS_DIR'] ) ) {
			echo colorize( "<yellow>Plugins Dir:</yellow> {$env_data['SLIC_PLUGINS_DIR']}" . PHP_EOL );
		}
		if ( isset( $env_data['SLIC_THEMES_DIR'] ) ) {
			echo colorize( "<yellow>Themes Dir:</yellow> {$env_data['SLIC_THEMES_DIR']}" . PHP_EOL );
		}
	}

	// Show ports - ensure they're up-to-date from Docker
	echo colorize( PHP_EOL . "<yellow>Ports:</yellow>" . PHP_EOL );
	if ( slic_stacks_ensure_ports( $stack_id ) ) {
		$updated_stack = slic_stacks_get( $stack_id );
		echo colorize( "  WordPress: <light_cyan>http://localhost:{$updated_stack['ports']['wp']}</light_cyan>" . PHP_EOL );
		echo colorize( "  MySQL: <light_cyan>{$updated_stack['ports']['mysql']}</light_cyan>" . PHP_EOL );
		if ( isset( $updated_stack['ports']['redis'] ) ) {
			echo colorize( "  Redis: <light_cyan>{$updated_stack['ports']['redis']}</light_cyan>" . PHP_EOL );
		}
		// Show XDebug configuration if available
		if ( isset( $updated_stack['xdebug_port'] ) && isset( $updated_stack['xdebug_key'] ) ) {
			echo colorize( "  XDebug Port: <light_cyan>{$updated_stack['xdebug_port']}</light_cyan>" . PHP_EOL );
			echo colorize( "  XDebug Server: <light_cyan>{$updated_stack['xdebug_key']}</light_cyan>" . PHP_EOL );
		} else {
			echo colorize( "  <yellow>XDebug: Run 'slic here' to configure</yellow>" . PHP_EOL );
		}
	} else {
		echo colorize( "  <yellow>Ports will be available after containers start</yellow>" . PHP_EOL );
	}

	// Show status
	if ( isset( $stack['status'] ) ) {
		$status_color = $stack['status'] === 'running' ? 'green' : 'yellow';
		echo colorize( PHP_EOL . "<yellow>Status:</yellow> <{$status_color}>{$stack['status']}</{$status_color}>" . PHP_EOL );
	}

	// Show created time
	if ( isset( $stack['created_at'] ) ) {
		$created = date( 'Y-m-d H:i:s', strtotime( $stack['created_at'] ) );
		echo colorize( "<yellow>Created:</yellow> {$created}" . PHP_EOL );
	}

	echo PHP_EOL;
}
