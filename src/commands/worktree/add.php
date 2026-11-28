<?php
namespace StellarWP\Slic;

// Require a stack context
$stack_id = slic_current_stack();
if (!$stack_id) {
    echo "Error: No stack found. Run 'slic here' first.\n";
    exit(1);
}

// Require a target to be set
$stack_state = slic_stacks_get($stack_id);
$target = $stack_state['target'] ?? null;

if (!$target) {
    echo "Error: No target set. Run 'slic use <plugin|theme>' first.\n";
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
    echo "Usage: slic worktree add <branch> [-y|--yes]\n";
    echo "\n";
    echo "Creates a git worktree for the specified branch and registers a slic stack.\n";
    echo "\n";
    echo "Example:\n";
    echo "  slic worktree add fix/issue-123\n";
    echo "\n";
    exit(1);
}

// Validate branch name
if (!slic_worktree_validate_branch($branch)) {
    echo "Error: Invalid branch name: $branch\n";
    echo "Branch names cannot contain: .. ~ ^ : ? * [ ]\n";
    exit(1);
}

// Create worktree directory name
$worktree_dir = slic_worktree_create_dir_name($target, $branch);
if (!$worktree_dir) {
    echo "Error: Cannot create valid directory name from branch: $branch\n";
    exit(1);
}

// Determine base stack ID
$base_stack_id = slic_stacks_get_base_stack_id($stack_id);
$base_state = slic_stacks_get($base_stack_id);

if (!$base_state) {
    echo "Error: Base stack not found: $base_stack_id\n";
    exit(1);
}

// Build paths: the worktree goes next to the target's repository
$worktree_stack_id = $base_stack_id . '@' . $worktree_dir;
$target_full_path = slic_worktree_target_path($target);
$worktree_full_path = dirname($target_full_path) . '/' . $worktree_dir;

// Validate target directory exists (fail-fast validation)
if (!is_dir($target_full_path)) {
    echo "Error: Target directory not found: $target_full_path\n";
    exit(1);
}

// Check if trying to register main repository as a worktree
// This prevents confusion where the main repo could be detected as a worktree
// since git worktree list includes the main repo as its first entry
$target_real = realpath($target_full_path);
$worktree_real = realpath($worktree_full_path);
if ($worktree_real !== false && $target_real === $worktree_real) {
    echo "Error: Cannot register main repository as a worktree.\n";
    echo "The target and worktree paths are the same: $target_full_path\n";
    echo "Please specify a different branch name.\n";
    exit(1);
}

// A target without its own repository would branch the repository enclosing it, one `worktree remove` refuses.
if (slic_worktree_list($target_full_path, $git_error) === false) {
    echo "Error: $git_error\n";
    echo "Nothing was changed.\n";
    exit(1);
}

// Check if worktree stack already exists
$existing_state = slic_stacks_get($worktree_stack_id);
if ($existing_state) {
    $worktree_full_path = $existing_state['worktree_full_path'] ?? $worktree_full_path;

    $existing_worktree = slic_worktree_is_existing($target_full_path, $worktree_full_path, $branch);

    // Git keeps the record of a deleted worktree, so an empty directory at its path is listed: check its `.git` file too, as sync does.
    if ($existing_worktree === false || !is_file($worktree_full_path . '/.git')) {
        echo "Error: The worktree stack is registered, but its directory is gone or is not a git worktree: $worktree_full_path\n";
        echo "To unregister stacks whose worktree is gone, run:\n";
        echo "  slic worktree sync --clean\n";
        exit(1);
    }

    slic_worktree_require_branch($branch, $existing_state, $existing_worktree);

    echo "Worktree stack already exists!\n";
    echo "  Stack ID: $worktree_stack_id\n";
    echo "  Directory: $worktree_full_path\n";
    echo "  Branch: {$existing_state['worktree_branch']}\n";
    echo "  Status: {$existing_state['status']}\n";
    echo "\nTo work on this worktree:\n";
    echo "  cd $worktree_full_path\n";
    echo "  slic start\n";
    exit(0);
}

// Check if directory already exists
$should_create_worktree = true;
if (file_exists($worktree_full_path)) {
    // Directory exists - check if it's already a git worktree
    $existing_worktree = slic_worktree_is_existing($target_full_path, $worktree_full_path, $branch);

    if ($existing_worktree === false) {
        // Directory exists but is NOT a git worktree
        echo "Error: Directory already exists but is not a git worktree: $worktree_full_path\n";
        echo "Please remove it manually or choose a different branch name.\n";
        echo "\nTo remove the directory:\n";
        echo "  rm -rf " . escapeshellarg($worktree_full_path) . "\n";
        exit(1);
    }

    // Check for branch mismatch
    if (!empty($existing_worktree['error']) && $existing_worktree['error'] === 'branch_mismatch') {
        echo "Error: Directory exists as a git worktree but for a different branch.\n";
        echo "  Expected branch: $branch\n";
        echo "  Actual branch: " . ($existing_worktree['branch'] ?? '(detached HEAD)') . "\n";
        echo "  Directory: $worktree_full_path\n";
        echo "\nOptions:\n";
        if ($existing_worktree['branch'] !== null) {
            echo "  - Use the existing branch with: slic worktree add {$existing_worktree['branch']}\n";
        }
        echo "  - Remove the worktree with: git -C " . escapeshellarg($target_full_path) . " worktree remove " . escapeshellarg($worktree_full_path) . "\n";
        exit(1);
    }

    // Worktree exists with correct branch - skip creation
    echo "Git worktree already exists for branch '$branch' at $worktree_full_path\n";
    echo "Skipping git worktree creation, will register slic stack...\n\n";
    $should_create_worktree = false;
}

// Confirm action
if (!$force_yes) {
    echo "This will:\n";
    if ($should_create_worktree) {
        echo "  1. Create git worktree at: $worktree_full_path\n";
        echo "  2. Create new branch: $branch\n";
        echo "  3. Register slic stack: $worktree_stack_id\n";
        echo "  4. Allocate unique XDebug port\n";
    } else {
        echo "  1. Register existing worktree with slic\n";
        echo "  2. Stack ID: $worktree_stack_id\n";
        echo "  3. Allocate unique XDebug port\n";
    }
    echo "\nContinue? [y/N] ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirmation) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

// Execute git worktree add (only if needed)
if ($should_create_worktree) {
    echo "\nCreating git worktree...\n";

    $cmd = "git -C " . escapeshellarg($target_full_path) . " worktree add " . escapeshellarg($worktree_full_path) . " -b " . escapeshellarg($branch) . " 2>&1";

    exec($cmd, $output, $return_code);

    if ($return_code !== 0) {
        echo "Error: Failed to create git worktree.\n";
        echo "Command: $cmd\n";
        echo "Output:\n";
        echo implode("\n", $output) . "\n";
        echo "\nCommon causes:\n";
        echo "  - Branch '$branch' already exists\n";
        echo "  - Uncommitted changes in target repository\n";
        echo "  - Invalid git repository\n";
        exit(1);
    }

    echo "Git worktree created successfully.\n";
} else {
    echo "\nUsing existing git worktree...\n";
}

// Register stack with slic
echo "Registering slic stack...\n";

if (!slic_worktree_register($base_stack_id, $target, $target_full_path, $worktree_dir, $branch, $worktree_full_path)) {
    echo "Error: Failed to register stack.\n";
    echo "The git worktree was created but slic stack registration failed.\n";
    echo "You may need to manually remove the worktree:\n";
    echo "  git -C " . escapeshellarg($target_full_path) . " worktree remove " . escapeshellarg($worktree_full_path) . "\n";
    exit(1);
}

if ($should_create_worktree) {
    echo "Worktree stack registered successfully!\n";
} else {
    echo "Existing worktree registered successfully!\n";
}
echo "\n";
echo "Stack Details:\n";
echo "  Stack ID: $worktree_stack_id\n";
echo "  Directory: $worktree_full_path\n";
echo "  Branch: $branch\n";
echo "  XDebug Port: " . slic_stacks_get($worktree_stack_id)['xdebug_port'] . "\n";
echo "  Project Name: " . slic_stacks_get($worktree_stack_id)['project_name'] . "\n";
echo "\n";
echo "To start working:\n";
echo "  cd $worktree_full_path\n";
echo "  slic start\n";
