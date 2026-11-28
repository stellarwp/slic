<?php
/**
 * Handles the `reset` command to reset slic to its initial state.
 *
 * @var bool $is_help Whether we're handling an `help` request on this command or not.
 * @var string $cli_name The current name of slic CLI binary.
 * @var \Closure $args The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Resets the tool to its initial state configured by the env files.

		Additionally remove the default WP directory if <light_cyan>wp</light_cyan> is provided.

	USAGE:

		<yellow>{$cli_name} reset [wp]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} reset</light_cyan>
		Resets {$cli_name} back to its initial state.

		<light_cyan>{$cli_name} reset wp</light_cyan>
		Resets {$cli_name} back to its initial state and removes the default WP directory.
	HELP;

	echo colorize( $help );
	return;
}

$targets = $args( '...' );

quietly_tear_down_stack();

$run_settings_file = root( '/.env.slic.run' );
echo "Removing {$run_settings_file} ... ";
echo ( ! file_exists( $run_settings_file ) || unlink( $run_settings_file ) ) ?
	light_cyan( 'done' )
	: magenta( 'fail, remove it manually' );
echo PHP_EOL;

$stack_id = slic_current_stack();
if ( null !== $stack_id ) {
	$stack_state_file = slic_stacks_get_state_file( $stack_id );
	$stack            = slic_stacks_get( $stack_id );

	if ( slic_stacks_is_worktree( $stack_id ) && isset( $stack['base_stack_id'], $stack['worktree_target'] ) ) {
		// A worktree's settings hold its base stack directories and target: put them back as `worktree add` wrote them.
		echo "Resetting {$stack_state_file} ... ";
		slic_stacks_write_worktree_state_file( $stack_id, $stack['base_stack_id'], $stack['worktree_target'] );
		echo light_cyan( 'done' ) . PHP_EOL;
	} else {
		echo "Removing {$stack_state_file} ... ";
		echo ( ! file_exists( $stack_state_file ) || unlink( $stack_state_file ) ) ?
			light_cyan( 'done' )
			: magenta( 'fail, remove it manually' );
		echo PHP_EOL;
	}
}

$cache_dir = cache( '/', false );
echo "Removing cache directory $cache_dir ... ";
echo ( ! is_dir( $cache_dir ) || rrmdir( $cache_dir ) ) ?
	light_cyan( 'done' )
	: magenta( 'fail, remove it manually' );
echo PHP_EOL;

$wp_dir = root( '_wordpress' );
if ( in_array( 'wp', $targets, true ) && is_dir( $wp_dir ) ) {
	/*
	 * The stack will lock bound files if running, tear it down to unlock them.
	 */
	echo 'Tearing down the stack ... ';
	echo light_cyan('done'), PHP_EOL;

	echo "Removing the WordPress directory $wp_dir ... ";
	echo ! is_dir( $wp_dir ) || rrmdir( $wp_dir ) ?
		light_cyan( 'done' )
		: magenta( 'fail, remove it manually' );
	echo PHP_EOL;
}
