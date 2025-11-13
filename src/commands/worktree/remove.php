<?php
namespace StellarWP\Slic;

// Get current stack
$stack_id = slic_current_stack();
if (!$stack_id) {
    echo "Error: No stack found. Run 'slic here' first.\n";
    exit(1);
}

// Parse arguments ($_args is passed from worktree.php)
$branch = $_args[0] ?? null;
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
$worktree_full_path = $base_stack_id . '/' . $worktree_dir;

// Check if stack exists
$wt_state = slic_stacks_get($worktree_stack_id);
if (!$wt_state) {
    echo "Error: Worktree stack not found: $worktree_stack_id\n";
    exit(1);
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
    echo "  1. Stop Docker containers (if running)\n";
    echo "  2. Remove git worktree directory: $worktree_full_path\n";
    echo "  3. Unregister slic stack: $worktree_stack_id\n";
    echo "  4. Delete stack state file\n";
    echo "\nThis operation cannot be undone. Continue? [y/N] ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirmation) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

// Step 1: Stop containers
echo "\nStopping Docker containers...\n";
$docker_compose = docker_compose(['stop'], $worktree_stack_id);
$docker_compose();

// Step 2: Remove git worktree
echo "Removing git worktree...\n";

$original_cwd = getcwd();
$target_path = $base_stack_id . '/' . $target;

if (is_dir($target_path)) {
    chdir($target_path);

    $escaped_dir = escapeshellarg('../' . $worktree_dir);
    $cmd = "git worktree remove $escaped_dir --force 2>&1";
    exec($cmd, $output, $return_code);

    chdir($original_cwd);

    if ($return_code !== 0) {
        echo "Warning: Failed to remove git worktree via git command.\n";
        echo "Output: " . implode("\n", $output) . "\n";

        // Try manual directory removal as fallback
        if (is_dir($worktree_full_path)) {
            echo "Attempting manual directory removal...\n";
            exec("rm -rf " . escapeshellarg($worktree_full_path), $rm_output, $rm_code);

            if ($rm_code !== 0) {
                echo "Error: Could not remove directory.\n";
                echo "You may need to manually clean up:\n";
                echo "  cd $target_path\n";
                echo "  git worktree prune\n";
                echo "  rm -rf $worktree_full_path\n";
            }
        }
    } else {
        echo "Git worktree removed successfully.\n";
    }
} else {
    echo "Warning: Target directory not found, skipping git worktree removal.\n";
}

// Step 3: Unregister stack
echo "Unregistering stack...\n";
if (!slic_stacks_unregister($worktree_stack_id)) {
    echo "Warning: Failed to unregister stack from registry.\n";
}

// Step 4: Remove state file
$state_file = $wt_state['state_file'];
if (file_exists($state_file)) {
    if (!unlink($state_file)) {
        echo "Warning: Failed to remove state file: $state_file\n";
    }
}

echo "\nWorktree removed successfully.\n";
