<?php
namespace StellarWP\Slic;

// Get current stack context
$stack_id = slic_current_stack();
if (!$stack_id) {
    echo "Error: No stack found. Run 'slic here' first.\n";
    exit(1);
}

$stack_state = slic_stacks_get($stack_id);
$target = $stack_state['target'] ?? null;

if (!$target) {
    echo "Error: No target set. Run 'slic use <plugin|theme>' first.\n";
    exit(1);
}

// Get base stack
$base_stack_id = slic_stacks_get_base_stack_id($stack_id);
$base_state = slic_stacks_get($base_stack_id);

echo "Worktree Status for Target: $target\n";
echo str_repeat('=', 60) . "\n\n";

echo "Base Stack: $base_stack_id\n";
echo "  Target: $target\n";
echo "  Status: {$base_state['status']}\n";
echo "  XDebug Port: {$base_state['xdebug_port']}\n";
echo "\n";

// Show git worktrees
$target_path = $base_stack_id . '/' . $target;
if (is_dir($target_path . '/.git')) {
    echo "Git Worktrees (from git worktree list):\n";
    echo str_repeat('-', 60) . "\n";
    $original_cwd = getcwd();
    chdir($target_path);
    passthru('git worktree list');
    chdir($original_cwd);
    echo "\n";
}

// Show slic worktree stacks
$worktrees = slic_stacks_get_worktrees($base_stack_id);

if (empty($worktrees)) {
    echo "No slic worktree stacks registered.\n";
    echo "\nTo create a worktree:\n";
    echo "  slic worktree add <branch-name>\n";
    exit(0);
}

echo "Slic Worktree Stacks:\n";
echo str_repeat('-', 60) . "\n";

foreach ($worktrees as $wt_stack_id => $wt_state) {
    echo "  {$wt_state['worktree_dir']}/\n";
    echo "    Branch: {$wt_state['worktree_branch']}\n";
    echo "    Path: {$wt_state['worktree_full_path']}\n";
    echo "    Status: {$wt_state['status']}\n";
    echo "    XDebug Port: {$wt_state['xdebug_port']}\n";
    echo "    Project: {$wt_state['project_name']}\n";
    echo "\n";
}

echo "Total worktrees: " . count($worktrees) . "\n";
