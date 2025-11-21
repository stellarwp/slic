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
$branch = $_args[0] ?? null;
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

// Build paths
$worktree_stack_id = $base_stack_id . '@' . $worktree_dir;
$worktree_full_path = $base_stack_id . '/' . $worktree_dir;
$target_full_path = $base_stack_id . '/' . $target;

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

// Check if worktree stack already exists
$existing_state = slic_stacks_get($worktree_stack_id);
if ($existing_state) {
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
        echo "  Actual branch: {$existing_worktree['branch']}\n";
        echo "  Directory: $worktree_full_path\n";
        echo "\nOptions:\n";
        echo "  1. Use the existing branch with: slic worktree add {$existing_worktree['branch']}\n";
        echo "  2. Remove the worktree with: cd $target_full_path && git worktree remove " . escapeshellarg(basename($worktree_full_path)) . "\n";
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

// Get the base branch before creating the worktree
$base_branch = trim(shell_exec("git -C " . escapeshellarg($target_full_path) . " rev-parse --abbrev-ref HEAD 2>/dev/null"));

// Execute git worktree add (only if needed)
if ($should_create_worktree) {
    echo "\nCreating git worktree...\n";

    $original_cwd = getcwd();
    chdir($base_stack_id); // Change to parent plugins/themes directory

    $escaped_dir = escapeshellarg($worktree_dir);
    $escaped_branch = escapeshellarg($branch);
    $cmd = "git -C " . escapeshellarg($target) . " worktree add " . escapeshellarg("../$worktree_dir") . " -b $escaped_branch 2>&1";

    exec($cmd, $output, $return_code);
    chdir($original_cwd);

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

// Determine project type
$project_type = get_project_type();

// Create worktree stack state
$worktree_state = [
    'stack_id' => $worktree_stack_id,
    'is_worktree' => true,
    'base_stack_id' => $base_stack_id,
    'base_branch' => $base_branch,
    'worktree_target' => $target,
    'worktree_dir' => $worktree_dir,
    'worktree_branch' => $branch,
    'worktree_full_path' => $worktree_full_path,
    'project_name' => slic_stacks_get_project_name($worktree_stack_id),
    'state_file' => slic_stacks_get_state_file($worktree_stack_id),
    'xdebug_port' => slic_stacks_xdebug_port($worktree_stack_id),
    'xdebug_key' => slic_stacks_xdebug_server_name($worktree_stack_id),
    'target' => $target, // Inherit target from base
    'status' => 'created',
];

if (!slic_stacks_register($worktree_stack_id, $worktree_state)) {
    echo "Error: Failed to register stack.\n";
    echo "The git worktree was created but slic stack registration failed.\n";
    echo "You may need to manually remove the worktree:\n";
    echo "  cd $target_full_path\n";
    echo "  git worktree remove ../$worktree_dir\n";
    exit(1);
}

// Write stack state file
$state_env_vars = [
    'SLIC_HERE_DIR' => $base_stack_id,
    'SLIC_WP_DIR' => getenv('SLIC_WP_DIR'),
    'SLIC_PLUGINS_DIR' => getenv('SLIC_PLUGINS_DIR'),
    'SLIC_THEMES_DIR' => getenv('SLIC_THEMES_DIR'),
    'SLIC_CURRENT_PROJECT' => $target,
];

write_env_file($worktree_state['state_file'], $state_env_vars);

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
echo "  XDebug Port: {$worktree_state['xdebug_port']}\n";
echo "  Project Name: {$worktree_state['project_name']}\n";
echo "\n";
echo "To start working:\n";
echo "  cd $worktree_full_path\n";
echo "  slic start\n";
