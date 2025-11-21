<?php
/**
 * Handles the `worktree` command and its subcommands.
 */

namespace StellarWP\Slic;

// Load worktree utility functions
require_once __DIR__ . '/../worktree-utils.php';

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Manage git worktrees with dedicated slic stacks.

	USAGE:

		<yellow>{$cli_name} worktree <subcommand> [<args>]</yellow>

	SUBCOMMANDS:

		<light_cyan>add <branch> [-y|--yes]</light_cyan>
		Create a new git worktree for the specified branch with a dedicated slic stack.

		<light_cyan>list</light_cyan>
		List all worktrees and their associated slic stacks.

		<light_cyan>merge <branch> [-y|--yes]</light_cyan>
		Merge worktree branch into base branch and remove the worktree stack.
		Must be run from the base stack directory, not from within the worktree.

		<light_cyan>remove <branch> [-y|--yes]</light_cyan>
		Remove a worktree and its associated slic stack.

		<light_cyan>sync</light_cyan>
		Synchronize git worktrees with slic registry, removing stale entries.

	OPTIONS:

		<light_cyan>-y, --yes</light_cyan>
		Skip confirmation prompt and proceed immediately. Useful for non-interactive
		environments like CI pipelines and automation scripts.

	EXAMPLES:

		<light_cyan>{$cli_name} worktree add fix/issue-123</light_cyan>
		Create a worktree for branch 'fix/issue-123' with a dedicated stack.

		<light_cyan>{$cli_name} worktree add feature/new-feature -y</light_cyan>
		Create a worktree without confirmation prompt.

		<light_cyan>{$cli_name} worktree list</light_cyan>
		Show all worktrees and their stacks.

		<light_cyan>{$cli_name} worktree remove fix/issue-123</light_cyan>
		Remove the worktree for branch 'fix/issue-123' and its stack.

		<light_cyan>{$cli_name} worktree merge fix/issue-123</light_cyan>
		Merge branch 'fix/issue-123' into base branch and clean up (from base stack directory).

		<light_cyan>{$cli_name} worktree merge fix/issue-123 -y</light_cyan>
		Merge without confirmation prompt.

		<light_cyan>{$cli_name} worktree sync</light_cyan>
		Synchronize worktrees with slic registry.

	NOTE:

		slic provides minimal git worktree integration. Use 'git worktree' directly
		for advanced operations.
	HELP;

	echo colorize( $help );
	return;
}

$worktree_subcommands = [
	'add' => 'Create a new git worktree with dedicated stack',
	'list' => 'List worktrees and their stacks',
	'merge' => 'Merge worktree branch and remove stack',
	'remove' => 'Remove a worktree and its stack',
	'sync' => 'Synchronize git worktrees with slic registry',
];

// Parse subcommand from args
$sub_args = args( [ 'subcommand' ], $args( '...' ), 0 );
$wt_subcommand = $sub_args( 'subcommand', false );

if (empty($wt_subcommand)) {
	echo "Git Worktree Support for slic\n\n";
	echo "Available commands:\n";
	foreach ($worktree_subcommands as $cmd => $desc) {
		echo "  slic worktree $cmd - $desc\n";
	}
	echo "\nNote: slic provides minimal git worktree integration.\n";
	echo "Use 'git worktree' directly for advanced operations.\n";
	echo "\nRun 'slic worktree help' for more information.\n";
	exit(0);
}

$subcommand_file = __DIR__ . '/worktree/' . $wt_subcommand . '.php';

if (!file_exists($subcommand_file)) {
	echo "Unknown worktree command: $wt_subcommand\n";
	echo "Run 'slic worktree' to see available commands.\n";
	exit(1);
}

// Pass remaining args to subcommand (after 'worktree' and subcommand)
$_args = array_slice($args('...'), 1);

include $subcommand_file;
