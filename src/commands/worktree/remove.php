<?php
namespace StellarWP\Slic;

// Get current stack
$stack_id = slic_current_stack();
if (!$stack_id) {
    echo "Error: No stack found. Run 'slic here' first.\n";
    exit(1);
}

// Parse arguments ($_args is passed from worktree.php)
// Filter out flags to get the branch argument
$branch = null;
foreach ($_args as $arg) {
    if ($arg !== '-y' && $arg !== '--yes') {
        $branch = $arg;
        break;
    }
}
$force_yes = in_array('-y', $_args) || in_array('--yes', $_args);

if (!$branch) {
    echo "Usage: slic worktree remove <branch> [-y|--yes]\n";
    echo "\n";
    echo "Removes a git worktree and its slic stack.\n";
    echo "\n";
    echo "WARNING: This will delete the worktree directory.\n";
    echo "         Ensure all changes are committed and pushed.\n";
    echo "\n";
    exit(1);
}

$stack_state = slic_stacks_get($stack_id);
$target = $stack_state['target'] ?? null;

if (!$target) {
    echo "Error: No target set. Run 'slic use <plugin|theme>' first.\n";
    exit(1);
}

// Build worktree stack ID
$base_stack_id = slic_stacks_get_base_stack_id($stack_id);
$worktree_dir = slic_worktree_create_dir_name($target, $branch);

if (!$worktree_dir) {
    echo "Error: Invalid branch name: $branch\n";
    exit(1);
}

$worktree_stack_id = $base_stack_id . '@' . $worktree_dir;

// Check if stack exists
$wt_state = slic_stacks_get($worktree_stack_id);
$is_registered = ($wt_state !== null);

// Read the paths from the stack state; an unregistered worktree is next to the target's repository
$target_path = $wt_state['worktree_target_path'] ?? slic_worktree_target_path($target);
$worktree_full_path = $wt_state['worktree_full_path'] ?? dirname($target_path) . '/' . $worktree_dir;

// Check if git worktree exists
$existing_worktree = slic_worktree_is_existing($target_path, $worktree_full_path, $branch);
$git_worktree_exists = ($existing_worktree !== false);

// Handle different scenarios
if (!$is_registered && !$git_worktree_exists) {
    echo "Error: Worktree not found.\n";
    echo "  - Not registered with slic: $worktree_stack_id\n";
    echo "  - Not found as git worktree: $worktree_full_path\n";
    echo "\nRun 'slic worktree list' to see available worktrees.\n";
    exit(1);
}

slic_worktree_require_branch($branch, $wt_state, $existing_worktree);
slic_worktree_require_unlocked($target_path, $worktree_full_path);

if (!$is_registered && $git_worktree_exists) {
    echo "WARNING: This worktree is NOT registered with slic.\n";
    echo "  - Git worktree exists: $worktree_full_path\n";
    echo "  - But no slic stack found: $worktree_stack_id\n";
    echo "\n";
    echo "This can happen if you ran 'slic stop' on the worktree.\n";
    echo "Only the git worktree will be removed.\n";
    echo "\n";
}

// Pre-removal validation: Check for uncommitted changes
if (is_dir($worktree_full_path)) {
    $original_cwd = getcwd();
    chdir($worktree_full_path);

    exec('git status --porcelain 2>&1', $status_output, $status_code);
    chdir($original_cwd);

    if ($status_code === 0 && !empty($status_output)) {
        echo "WARNING: Worktree has uncommitted changes:\n";
        foreach ($status_output as $line) {
            echo "  $line\n";
        }
        echo "\n";

        if (!$force_yes) {
            echo "These changes will be LOST. Continue? [y/N] ";
            $handle = fopen('php://stdin', 'r');
            $confirmation = trim(fgets($handle));
            fclose($handle);

            if (strtolower($confirmation) !== 'y') {
                echo "Cancelled. Commit or stash your changes first.\n";
                exit(0);
            }
        }
    }
}

// Final confirmation
if (!$force_yes) {
    echo "This will:\n";
    if ($is_registered) {
        echo "  1. Stop Docker containers (if running)\n";
        echo "  2. Remove git worktree directory: $worktree_full_path\n";
        echo "  3. Unregister slic stack: $worktree_stack_id\n";
        echo "  4. Delete stack state file\n";
    } else {
        echo "  1. Remove git worktree directory: $worktree_full_path\n";
    }
    echo "\nThis operation cannot be undone. Continue? [y/N] ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirmation) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

// Step 1: Bring the containers and network down (only if registered)
if ($is_registered) {
    echo "\nStopping Docker containers...\n";
    if (command_stop($worktree_stack_id, false) !== 0) {
        echo "Error: Failed to stop the worktree stack; nothing was removed.\n";
        exit(1);
    }
}

// Step 2: Remove git worktree
if ($git_worktree_exists) {
    echo "Removing git worktree...\n";

    $cmd = "git -C " . escapeshellarg($target_path) . " worktree remove " . escapeshellarg($worktree_full_path) . " --force 2>&1";
    exec($cmd, $output, $return_code);

    if ($return_code !== 0) {
        echo "Error: Failed to remove git worktree.\n";
        echo "Output: " . implode("\n", $output) . "\n";
        exit(1);
    }

    echo "Git worktree removed successfully.\n";
} else {
    echo "Git worktree not found, skipping git worktree removal.\n";
}

// Step 3: Unregister stack (only if registered)
// Note: State file cleanup is handled by slic_stacks_unregister()
if ($is_registered) {
    echo "Unregistering stack...\n";
    if (!slic_stacks_unregister($worktree_stack_id)) {
        echo "Warning: Failed to unregister stack from registry.\n";
    }
}

echo "\nWorktree removed successfully.\n";
