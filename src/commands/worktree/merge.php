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

// Require branch argument
if (!$branch) {
    echo "Usage: slic worktree merge <branch> [-y|--yes]\n";
    echo "\n";
    echo "Merges a worktree branch into its base branch and removes the worktree stack.\n";
    echo "\n";
    echo "Arguments:\n";
    echo "  <branch>    The branch name of the worktree to merge (required)\n";
    echo "\n";
    echo "Options:\n";
    echo "  -y, --yes   Skip confirmation prompts\n";
    echo "\n";
    echo "Example:\n";
    echo "  slic worktree merge fix/issue-123\n";
    echo "\n";
    echo "Note: This command must be run from the base stack directory,\n";
    echo "      not from within the worktree directory being merged.\n";
    exit(1);
}

// Get current stack state to find the target
$stack_state = slic_stacks_get($stack_id);
$target = $stack_state['target'] ?? null;

if (!$target) {
    echo "Error: No target set. Run 'slic use <plugin|theme>' first.\n";
    exit(1);
}

// Build worktree stack ID from branch name (like remove.php does)
$base_stack_id = slic_stacks_get_base_stack_id($stack_id);
$worktree_dir = slic_worktree_create_dir_name($target, $branch);

if (!$worktree_dir) {
    echo "Error: Invalid branch name: $branch\n";
    exit(1);
}

$worktree_stack_id = $base_stack_id . '@' . $worktree_dir;

// Check if stack exists (warn but continue)
$wt_state = slic_stacks_get($worktree_stack_id);
$is_registered = ($wt_state !== null);

// Read the paths from the stack state; an unregistered worktree is next to the target's repository
$target_path = $wt_state['worktree_target_path'] ?? slic_worktree_target_path($target);
$worktree_full_path = $wt_state['worktree_full_path'] ?? dirname($target_path) . '/' . $worktree_dir;

// Check if running from within the worktree directory being merged
$current_cwd = getcwd();
$resolved_worktree_path = realpath($worktree_full_path);
$resolved_cwd = realpath($current_cwd);

if ($resolved_worktree_path && $resolved_cwd) {
    // Check if cwd is the worktree directory or a subdirectory of it
    if ($resolved_cwd === $resolved_worktree_path || strpos($resolved_cwd, $resolved_worktree_path . '/') === 0) {
        echo "Error: Cannot run 'slic worktree merge $branch' from within the worktree directory for '$branch'.\n";
        echo "       The worktree directory will be removed by git during the merge process.\n";
        echo "\n";
        echo "Please change to the base stack directory and run:\n";
        echo "  cd $base_stack_id && slic worktree merge $branch\n";
        exit(1);
    }
}

// Check if branch exists in git (warn but continue)
if (is_dir($target_path)) {
    $branch_check_cmd = "git -C " . escapeshellarg($target_path) . " rev-parse --verify " . escapeshellarg($branch) . " 2>/dev/null";
    exec($branch_check_cmd, $branch_output, $branch_code);
    if ($branch_code !== 0) {
        echo "Warning: Branch '$branch' does not exist in git.\n";
    }
}

if (!$is_registered) {
    echo "Warning: Stack for branch '$branch' is not registered with slic.\n";
}

// Validate target directory exists
if (!is_dir($target_path)) {
    echo "Error: Target repository not found: $target_path\n";
    echo "The base repository may have been moved or deleted.\n";
    exit(1);
}

// Check if git worktree still exists
$existing_worktree = slic_worktree_is_existing($target_path, $worktree_full_path, $branch);
$git_worktree_exists = ($existing_worktree !== false);

slic_worktree_require_branch($branch, $wt_state, $existing_worktree);
slic_worktree_require_unlocked($target_path, $worktree_full_path);

$original_cwd = getcwd();

// If worktree doesn't exist, inform user and proceed with stack cleanup only
if (!$git_worktree_exists) {
    echo "Notice: Git worktree no longer exists at: $worktree_full_path\n";
    echo "The worktree may have been manually removed or already merged.\n\n";

    if (!$force_yes) {
        echo "This will clean up the slic stack registration:\n";
        echo "  1. Stop Docker containers (if running)\n";
        echo "  2. Unregister slic stack: $worktree_stack_id\n";
        echo "\nContinue? [y/N] ";

        $handle = fopen('php://stdin', 'r');
        $confirmation = trim(fgets($handle));
        fclose($handle);

        if (strtolower($confirmation) !== 'y') {
            echo "Cancelled.\n";
            exit(0);
        }
    }

    // The worktree can have been locked while slic waited for the answer.
    slic_worktree_require_unlocked($target_path, $worktree_full_path);

    // Bring the containers and network down (only if registered)
    if ($is_registered) {
        echo "\nStopping Docker containers...\n";
        if (command_stop($worktree_stack_id, false) !== 0) {
            echo "Error: Failed to stop the worktree stack; it is still registered.\n";
            exit(1);
        }

        // Unregister stack
        echo "Unregistering stack...\n";
        if (!slic_stacks_unregister($worktree_stack_id)) {
            echo "Warning: Failed to unregister stack from registry.\n";
        }
    }

    echo "\nWorktree stack cleaned up successfully.\n";
    echo "Note: The worktree branch '$branch' was not deleted (worktree not found).\n";
    echo "You may want to delete it manually if no longer needed:\n";
    echo "  cd $target_path && git branch -d " . escapeshellarg($branch) . "\n";
    exit(0);
}

// The base branch is the one the target repository was on when the worktree was registered
if ($is_registered) {
    $base_branch = $wt_state['base_branch'] ?? '';
} else {
    // An unregistered worktree has no record of it: use the branch the target repository is on now
    $base_branch = trim((string) shell_exec("git -C " . escapeshellarg($target_path) . " rev-parse --abbrev-ref HEAD 2>/dev/null"));
}

// `HEAD` is a detached HEAD: merging into it and deleting the branch would leave its commits on no branch
if ($base_branch === '' || $base_branch === 'HEAD') {
    echo "Error: Could not determine the branch to merge '$branch' into.\n";
    echo "The target repository was on a detached HEAD when the worktree was registered, or slic has no record of its branch.\n";
    echo "Merge it with git instead, then remove the worktree:\n";
    echo "  git -C " . escapeshellarg($target_path) . " checkout <base-branch>\n";
    echo "  git -C " . escapeshellarg($target_path) . " merge " . escapeshellarg($branch) . "\n";
    echo "  slic worktree remove $branch\n";
    exit(1);
}

// Check for uncommitted changes in the worktree
if (is_dir($worktree_full_path)) {
    chdir($worktree_full_path);
    exec('git status --porcelain 2>&1', $status_output, $status_code);
    chdir($original_cwd);

    if ($status_code === 0 && !empty($status_output)) {
        echo "Error: Worktree has uncommitted changes:\n";
        foreach ($status_output as $line) {
            echo "  $line\n";
        }
        echo "\nPlease commit or stash your changes before merging.\n";
        exit(1);
    }
}

// Confirmation prompt
if (!$force_yes) {
    echo "This will:\n";
    echo "  1. Checkout base branch '$base_branch' in target repository\n";
    echo "  2. Merge worktree branch '$branch' into '$base_branch'\n";
    echo "  3. Stop Docker containers (if running)\n";
    echo "  4. Remove git worktree directory: $worktree_full_path\n";
    echo "  5. Delete local worktree branch: $branch\n";
    echo "  6. Unregister slic stack: $worktree_stack_id\n";
    echo "\nContinue? [y/N] ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirmation) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

// The worktree can have been locked while slic waited for the answer.
slic_worktree_require_unlocked($target_path, $worktree_full_path);

// Step 1: Checkout base branch in target repo
echo "\nChecking out base branch '$base_branch'...\n";

chdir($target_path);
$cmd = "git checkout " . escapeshellarg($base_branch) . " 2>&1";
exec($cmd, $checkout_output, $checkout_code);
chdir($original_cwd);

if ($checkout_code !== 0) {
    echo "Error: Failed to checkout base branch '$base_branch'.\n";
    echo "Output: " . implode("\n", $checkout_output) . "\n";
    echo "\nPlease resolve any issues in the target repository and try again.\n";
    exit(1);
}

// Step 2: Merge worktree branch
echo "Merging branch '$branch' into '$base_branch'...\n";

chdir($target_path);
$cmd = "git merge " . escapeshellarg($branch) . " 2>&1";
exec($cmd, $merge_output, $merge_code);
chdir($original_cwd);

if ($merge_code !== 0) {
    echo "Error: Git merge failed.\n";
    echo "Output:\n";
    foreach ($merge_output as $line) {
        echo "  $line\n";
    }

    // Git can refuse a merge before starting it, as without a git identity: then there is nothing to resolve or abort.
    exec("git -C " . escapeshellarg($target_path) . " rev-parse -q --verify MERGE_HEAD 2>/dev/null", $merge_head_output, $merge_head_code);
    if ($merge_head_code !== 0) {
        echo "\nThe merge did not start. Fix the error above and try again.\n";
        exit(1);
    }

    echo "\nThe merge encountered conflicts or errors.\n";
    echo "Please resolve the merge issue manually:\n";
    echo "  cd $target_path\n";
    echo "  # Resolve conflicts\n";
    echo "  git add .\n";
    echo "  git commit\n";
    echo "\nAfter resolving, you can complete the merge with:\n";
    echo "  cd $base_stack_id && slic worktree merge $branch\n";
    echo "\nTo abort the merge instead:\n";
    echo "  git -C " . escapeshellarg($target_path) . " merge --abort\n";
    exit(1);
}

echo "Merge successful.\n";

// Step 3: Bring the containers and network down (only if registered)
if ($is_registered) {
    echo "Stopping Docker containers...\n";
    if (command_stop($worktree_stack_id, false) !== 0) {
        echo "Error: Failed to stop the worktree stack; the worktree was merged but not removed.\n";
        exit(1);
    }
}

// Step 4: Remove git worktree
echo "Removing git worktree...\n";

// Use git -C to avoid chdir issues (original_cwd may be inside the worktree being removed)
$cmd = "git -C " . escapeshellarg($target_path) . " worktree remove " . escapeshellarg($worktree_full_path) . " --force 2>&1";
exec($cmd, $wt_output, $wt_code);

if ($wt_code !== 0) {
    echo "Error: Failed to remove git worktree; the branch was merged but not deleted.\n";
    echo "Output: " . implode("\n", $wt_output) . "\n";
    exit(1);
}

echo "Git worktree removed successfully.\n";

// Step 5: Delete local worktree branch
echo "Deleting local branch '$branch'...\n";

// Use git -C to avoid chdir issues (original_cwd may no longer exist after worktree removal)
$cmd = "git -C " . escapeshellarg($target_path) . " branch -d " . escapeshellarg($branch) . " 2>&1";
exec($cmd, $branch_output, $branch_code);

if ($branch_code !== 0) {
    echo "Warning: Failed to delete branch '$branch'.\n";
    echo "Output: " . implode("\n", $branch_output) . "\n";
    echo "You may need to delete it manually:\n";
    echo "  cd $target_path && git branch -D " . escapeshellarg($branch) . "\n";
} else {
    echo "Branch deleted successfully.\n";
}

// Step 6: Unregister stack (only if registered)
if ($is_registered) {
    echo "Unregistering stack...\n";
    if (!slic_stacks_unregister($worktree_stack_id)) {
        echo "Warning: Failed to unregister stack from registry.\n";
    }
}

echo "\nWorktree merged and removed successfully!\n";
echo "  Branch '$branch' has been merged into '$base_branch'\n";
if ($is_registered) {
    echo "  Stack '$worktree_stack_id' has been removed\n";
}

