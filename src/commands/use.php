<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets the plugin to use in the tests.

	USAGE:

		<yellow>{$cli_name} {$subcommand} <target>[/<subdir>]</yellow>

	EXAMPLES:

	<light_cyan>{$cli_name} {$subcommand} the-events-calendar</light_cyan>
	Set the use target to the-events-calendar.

	<light_cyan>{$cli_name} {$subcommand} event-tickets/common</light_cyan>
	Set the use target to the common/ directory within event-tickets.
	HELP;

	echo colorize( $help );
	return;
}

$sub_args = args( [ 'target' ], $args( '...' ), 0 );
$target   = $sub_args( 'target', false );

// Determine which stack to use
$stack_id = slic_current_stack_or_fail( "Cannot switch target without an active stack." );

// Check if current stack is a worktree - switching targets is not allowed
if ( slic_stacks_is_worktree( $stack_id ) ) {
	$parsed = slic_stacks_parse_worktree_id( $stack_id );

	if ( null === $parsed ) {
		echo magenta( "Cannot switch target: invalid worktree stack format." . PHP_EOL );
		exit( 1 );
	}

	$worktree_dir = $parsed['worktree_dir'];
	$stack_state = slic_stacks_get( $stack_id );

	if ( null === $stack_state ) {
		echo magenta( "Cannot switch target: worktree stack state not found." . PHP_EOL );
		exit( 1 );
	}

	$current_target = $stack_state['target'] ?? 'unknown';
	$base_stack_id = $stack_state['base_stack_id'] ?? null;
	$requested_target = $target ?: '(current directory)';

	echo magenta( "Cannot switch target in a worktree stack." . PHP_EOL );
	echo magenta( "Worktree stacks are tied to a specific target directory." . PHP_EOL );
	echo magenta( "Requested target: {$requested_target}" . PHP_EOL );
	echo magenta( "Current worktree: {$worktree_dir} (target: {$current_target})" . PHP_EOL );
	if ( null !== $base_stack_id ) {
		echo PHP_EOL;
		echo colorize( "To switch targets, use the base stack: <light_cyan>slic stack switch {$base_stack_id}</light_cyan>" . PHP_EOL );
	}
	exit( 1 );
}

// Resolve the target (may auto-detect from current directory)
$target = (string) ensure_valid_target( $target );

if ( ! empty( $target ) ) {
	slic_switch_target( $target, $stack_id );
}

// Show which stack is being used
$stack = slic_stacks_get( $stack_id );
echo light_cyan( "Using {$target} in stack: {$stack_id}" . PHP_EOL );
if ( null !== $stack && isset( $stack['ports']['wp'] ) ) {
	echo colorize( "WordPress URL: <yellow>http://localhost:{$stack['ports']['wp']}</yellow>" . PHP_EOL );
}

project_apply_config( get_target_relative_path( $target ) );