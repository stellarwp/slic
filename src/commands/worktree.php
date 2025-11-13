<?php
namespace StellarWP\Slic;

$worktree_subcommands = [
	'add' => 'Create a new git worktree with dedicated stack',
	'list' => 'List worktrees and their stacks',
	'remove' => 'Remove a worktree and its stack',
	'sync' => 'Synchronize git worktrees with slic registry',
];

// Parse subcommand from args
$sub_args = args( [ 'subcommand' ], $args( '...' ), 0 );
$subcommand = $sub_args( 'subcommand', false );

if (empty($subcommand)) {
	echo "Git Worktree Support for slic\n\n";
	echo "Available commands:\n";
	foreach ($worktree_subcommands as $cmd => $desc) {
		echo "  slic worktree $cmd - $desc\n";
	}
	echo "\nNote: slic provides minimal git worktree integration.\n";
	echo "Use 'git worktree' directly for advanced operations.\n";
	exit(0);
}

$subcommand_file = __DIR__ . '/worktree/' . $subcommand . '.php';

if (!file_exists($subcommand_file)) {
	echo "Unknown worktree command: $subcommand\n";
	echo "Run 'slic worktree' to see available commands.\n";
	exit(1);
}

// Pass remaining args to subcommand (after 'worktree' and subcommand)
$_args = array_slice($args('...'), 1);

include $subcommand_file;
