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
if (file_exists($worktree_full_path)) {
    echo "Error: Directory already exists: $worktree_full_path\n";
    echo "Remove it manually or choose a different branch name.\n";
    exit(1);
}

// Validate target directory exists
if (!is_dir($target_full_path)) {
    echo "Error: Target directory not found: $target_full_path\n";
    exit(1);
}

// Confirm action
if (!$force_yes) {
    echo "This will:\n";
    echo "  1. Create git worktree at: $worktree_full_path\n";
    echo "  2. Create new branch: $branch\n";
    echo "  3. Register slic stack: $worktree_stack_id\n";
    echo "  4. Allocate unique XDebug port\n";
    echo "\nContinue? [y/N] ";

    $handle = fopen('php://stdin', 'r');
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirmation) !== 'y') {
        echo "Cancelled.\n";
        exit(0);
    }
}

// Execute git worktree add
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

// Register stack with slic
echo "Registering slic stack...\n";

// Determine project type
$project_type = get_project_type();

// Create worktree stack state
$worktree_state = [
    'stack_id' => $worktree_stack_id,
    'is_worktree' => true,
    'base_stack_id' => $base_stack_id,
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

echo "Worktree stack registered successfully!\n";
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
