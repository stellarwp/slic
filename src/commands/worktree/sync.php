<?php
/**
 * Synchronize git worktrees with slic registry.
 *
 * This command detects and reports on orphaned worktrees in both directions:
 * 1. Orphaned slic stacks: Registered in slic but git worktree doesn't exist
 * 2. Orphaned git worktrees: Exist in filesystem but not registered in slic
 *
 * Provides dry-run mode and offers to clean up orphans automatically.
 */

namespace StellarWP\Slic;

// Require a stack context
$stack_id = slic_current_stack();
if (!$stack_id) {
	echo "Error: No stack found. Run 'slic here' first.\n";
	exit(1);
}

// Parse arguments ($_args is passed from worktree.php)
$dry_run = in_array('--dry-run', $_args) || in_array('-n', $_args);
$auto_clean = in_array('--clean', $_args) || in_array('-c', $_args);
$force_yes = in_array('-y', $_args) || in_array('--yes', $_args);

// Show help if requested
if (in_array('--help', $_args) || in_array('-h', $_args)) {
	echo "Usage: slic worktree sync [OPTIONS]\n";
	echo "\n";
	echo "Synchronizes git worktrees with slic registry.\n";
	echo "\n";
	echo "This command detects:\n";
	echo "  - Orphaned slic stacks (registered but directory doesn't exist)\n";
	echo "  - Orphaned git worktrees (exist but not registered in slic)\n";
	echo "\n";
	echo "Options:\n";
	echo "  -n, --dry-run    Show what would be cleaned up without making changes\n";
	echo "  -c, --clean      Automatically clean up orphaned entries\n";
	echo "  -y, --yes        Skip confirmation prompts (use with --clean)\n";
	echo "  -h, --help       Show this help message\n";
	echo "\n";
	echo "Examples:\n";
	echo "  slic worktree sync              # Check for orphans (report only)\n";
	echo "  slic worktree sync --dry-run    # Show what would be cleaned\n";
	echo "  slic worktree sync --clean      # Clean up orphans (with prompts)\n";
	echo "  slic worktree sync --clean -y   # Clean up orphans (no prompts)\n";
	echo "\n";
	exit(0);
}

echo "Synchronizing git worktrees with slic registry...\n\n";

$stack_state = slic_stacks_get($stack_id);
$target = $stack_state['target'] ?? null;

// Determine base stack ID
$base_stack_id = slic_stacks_get_base_stack_id($stack_id);
$base_state = slic_stacks_get($base_stack_id);

if (!$base_state) {
	echo "Error: Base stack not found: $base_stack_id\n";
	exit(1);
}

$stacks = slic_stacks_list();
$orphaned_slic_stacks = [];
$orphaned_git_worktrees = [];
$issues_found = false;

// ============================================================================
// Phase 1: Find orphaned slic stacks (registered but worktree doesn't exist)
// ============================================================================

echo "Phase 1: Checking for orphaned slic stacks...\n";
echo str_repeat('-', 60) . "\n";

foreach ($stacks as $check_stack_id => $state) {
	// Only check worktree stacks
	if (empty($state['is_worktree'])) {
		continue;
	}

	$worktree_path = $state['worktree_full_path'] ?? null;

	if (!$worktree_path) {
		// Missing worktree_full_path - corrupted entry
		$orphaned_slic_stacks[] = [
			'stack_id' => $check_stack_id,
			'reason' => 'Missing worktree_full_path field',
			'state' => $state,
		];
		$issues_found = true;
		continue;
	}

	// Check if directory exists
	if (!is_dir($worktree_path)) {
		$orphaned_slic_stacks[] = [
			'stack_id' => $check_stack_id,
			'path' => $worktree_path,
			'reason' => 'Directory not found',
			'state' => $state,
		];
		$issues_found = true;
		continue;
	}

	// Check if it's actually a git worktree (has .git file, not directory)
	$git_file = $worktree_path . '/.git';
	if (!is_file($git_file)) {
		$orphaned_slic_stacks[] = [
			'stack_id' => $check_stack_id,
			'path' => $worktree_path,
			'reason' => 'Directory exists but not a git worktree (.git is not a file)',
			'state' => $state,
		];
		$issues_found = true;
		continue;
	}

	// Verify .git file contains gitdir reference
	$git_content = @file_get_contents($git_file);
	if ($git_content === false || strpos($git_content, 'gitdir:') !== 0) {
		$orphaned_slic_stacks[] = [
			'stack_id' => $check_stack_id,
			'path' => $worktree_path,
			'reason' => 'Invalid .git file (not a worktree reference)',
			'state' => $state,
		];
		$issues_found = true;
		continue;
	}
}

if (!empty($orphaned_slic_stacks)) {
	echo "Found " . count($orphaned_slic_stacks) . " orphaned slic stack(s):\n\n";
	foreach ($orphaned_slic_stacks as $idx => $orphan) {
		echo "  [" . ($idx + 1) . "] Stack ID: {$orphan['stack_id']}\n";
		if (isset($orphan['path'])) {
			echo "      Expected path: {$orphan['path']}\n";
		}
		echo "      Reason: {$orphan['reason']}\n";
		if (!empty($orphan['state']['worktree_branch'])) {
			echo "      Branch: {$orphan['state']['worktree_branch']}\n";
		}
		echo "\n";
	}
} else {
	echo "No orphaned slic stacks found.\n\n";
}

// ============================================================================
// Phase 2: Find orphaned git worktrees (exist but not registered in slic)
// ============================================================================

echo "Phase 2: Checking for unregistered git worktrees...\n";
echo str_repeat('-', 60) . "\n";

// Use `git worktree list --porcelain` to get accurate worktree information
if ($target) {
	$target_path = $base_stack_id . '/' . $target;

	if (is_dir($target_path . '/.git')) {
		// Execute git worktree list --porcelain
		$original_cwd = getcwd();
		chdir($target_path);

		exec('git worktree list --porcelain 2>&1', $worktree_output, $worktree_return);
		chdir($original_cwd);

		if ($worktree_return === 0) {
			// Parse porcelain output
			$git_worktrees = [];
			$current_worktree = [];

			foreach ($worktree_output as $line) {
				if (empty($line)) {
					// Empty line marks end of entry
					if (!empty($current_worktree)) {
						$git_worktrees[] = $current_worktree;
						$current_worktree = [];
					}
					continue;
				}

				// Parse key-value pairs
				if (strpos($line, 'worktree ') === 0) {
					$current_worktree['path'] = trim(substr($line, 9));
				} elseif (strpos($line, 'HEAD ') === 0) {
					$current_worktree['head'] = trim(substr($line, 5));
				} elseif (strpos($line, 'branch ') === 0) {
					$current_worktree['branch'] = trim(substr($line, 7));
				} elseif (strpos($line, 'bare') === 0) {
					$current_worktree['bare'] = true;
				} elseif (strpos($line, 'detached') === 0) {
					$current_worktree['detached'] = true;
				} elseif (strpos($line, 'locked ') === 0) {
					$current_worktree['locked'] = trim(substr($line, 7));
				} elseif (strpos($line, 'prunable ') === 0) {
					$current_worktree['prunable'] = trim(substr($line, 9));
				}
			}

			// Add last entry if exists
			if (!empty($current_worktree)) {
				$git_worktrees[] = $current_worktree;
			}

			// Check each git worktree against registered slic stacks
			foreach ($git_worktrees as $git_wt) {
				$wt_path = $git_wt['path'] ?? null;

				if (!$wt_path) {
					continue;
				}

				// Skip the main repository (not a worktree)
				if (realpath($wt_path) === realpath($target_path)) {
					continue;
				}

				// Check if this worktree is in the base stack directory
				$parent_dir = dirname($wt_path);
				if (realpath($parent_dir) !== realpath($base_stack_id)) {
					// Worktree is not in the expected location, skip it
					continue;
				}

				$dir_name = basename($wt_path);

				// Build expected stack ID
				$expected_stack_id = $base_stack_id . '@' . $dir_name;

				// Check if registered in slic
				if (!isset($stacks[$expected_stack_id])) {
					// Check if directory name matches target prefix pattern
					if ($target && strpos($dir_name, $target . '-') === 0) {
						$orphaned_git_worktrees[] = [
							'path' => $wt_path,
							'dir_name' => $dir_name,
							'branch' => $git_wt['branch'] ?? 'unknown',
							'expected_stack_id' => $expected_stack_id,
							'head' => $git_wt['head'] ?? null,
							'detached' => !empty($git_wt['detached']),
							'locked' => $git_wt['locked'] ?? null,
							'prunable' => $git_wt['prunable'] ?? null,
						];
						$issues_found = true;
					}
				}
			}

			if (!empty($orphaned_git_worktrees)) {
				echo "Found " . count($orphaned_git_worktrees) . " unregistered git worktree(s):\n\n";
				foreach ($orphaned_git_worktrees as $idx => $orphan) {
					echo "  [" . ($idx + 1) . "] Directory: {$orphan['path']}\n";
					echo "      Branch: {$orphan['branch']}\n";
					if ($orphan['detached']) {
						echo "      Status: Detached HEAD\n";
					}
					if ($orphan['locked']) {
						echo "      Locked: {$orphan['locked']}\n";
					}
					if ($orphan['prunable']) {
						echo "      Prunable: {$orphan['prunable']}\n";
					}
					echo "      Suggested stack ID: {$orphan['expected_stack_id']}\n";
					echo "\n";
				}
			} else {
				echo "No unregistered git worktrees found.\n\n";
			}
		} else {
			echo "Warning: Could not list git worktrees.\n";
			echo "Command output: " . implode("\n", $worktree_output) . "\n\n";
		}
	} else {
		echo "Target directory is not a git repository: $target_path\n\n";
	}
}

// ============================================================================
// Phase 3: Summary and cleanup options
// ============================================================================

echo str_repeat('=', 60) . "\n";
echo "Summary:\n";
echo str_repeat('=', 60) . "\n";
echo "Orphaned slic stacks: " . count($orphaned_slic_stacks) . "\n";
echo "Unregistered git worktrees: " . count($orphaned_git_worktrees) . "\n";

if (!$issues_found) {
	echo "\nAll worktrees are in sync. No issues found.\n";
	exit(0);
}

// ============================================================================
// Phase 4: Cleanup (if requested)
// ============================================================================

if (!$auto_clean && !$dry_run) {
	echo "\nTo clean up orphaned entries, run:\n";
	echo "  slic worktree sync --clean\n";
	echo "\nTo see what would be cleaned without making changes, run:\n";
	echo "  slic worktree sync --dry-run\n";
	exit(0);
}

echo "\n";

if ($dry_run) {
	echo "DRY RUN MODE - No changes will be made\n";
	echo str_repeat('=', 60) . "\n\n";
}

// Handle orphaned slic stacks cleanup
if (!empty($orphaned_slic_stacks)) {
	echo "Cleanup plan for orphaned slic stacks:\n";
	echo str_repeat('-', 60) . "\n";

	foreach ($orphaned_slic_stacks as $idx => $orphan) {
		echo "[" . ($idx + 1) . "] {$orphan['stack_id']}\n";
		echo "    Action: Unregister from slic registry\n";
		if (!empty($orphan['state']['state_file'])) {
			echo "    Also remove state file: {$orphan['state']['state_file']}\n";
		}
		echo "\n";
	}

	if ($dry_run) {
		echo "Dry-run: Would unregister " . count($orphaned_slic_stacks) . " stack(s)\n\n";
	} else {
		// Confirm cleanup
		if (!$force_yes) {
			echo "Proceed with cleaning up " . count($orphaned_slic_stacks) . " orphaned stack(s)? [y/N] ";
			$handle = fopen('php://stdin', 'r');
			$confirmation = trim(fgets($handle));
			fclose($handle);

			if (strtolower($confirmation) !== 'y') {
				echo "Skipped cleaning orphaned slic stacks.\n\n";
				goto handle_git_orphans;
			}
		}

		// Perform cleanup
		$cleaned_count = 0;
		$failed_count = 0;

		foreach ($orphaned_slic_stacks as $orphan) {
			echo "Cleaning {$orphan['stack_id']}... ";

			// Unregister from registry
			if (slic_stacks_unregister($orphan['stack_id'])) {
				// Remove state file if it exists
				$state_file = $orphan['state']['state_file'] ?? null;
				if ($state_file && file_exists($state_file)) {
					@unlink($state_file);
				}

				echo "OK\n";
				$cleaned_count++;
			} else {
				echo "FAILED\n";
				$failed_count++;
			}
		}

		echo "\nCleaned $cleaned_count stack(s)";
		if ($failed_count > 0) {
			echo ", $failed_count failed";
		}
		echo ".\n\n";
	}
}

// Handle unregistered git worktrees
handle_git_orphans:

if (!empty($orphaned_git_worktrees)) {
	echo "Suggestions for unregistered git worktrees:\n";
	echo str_repeat('-', 60) . "\n";

	foreach ($orphaned_git_worktrees as $idx => $orphan) {
		echo "[" . ($idx + 1) . "] {$orphan['path']}\n";

		if ($orphan['prunable']) {
			echo "    Status: Prunable (worktree directory is missing)\n";
			echo "    Suggested action: Run 'git worktree prune' to clean up\n";
		} else {
			echo "    Suggested action: Register manually by running commands in this directory:\n";
			echo "      cd {$orphan['path']}\n";
			echo "      slic here  # This will detect and offer to register the worktree\n";
		}
		echo "\n";
	}

	echo "Note: Unregistered git worktrees are not automatically cleaned up.\n";
	echo "Use 'git worktree remove <path>' or 'git worktree prune' as needed.\n\n";
}

if ($dry_run) {
	echo "\nDRY RUN COMPLETE - No changes were made.\n";
	echo "Run without --dry-run to apply changes.\n";
}

exit(0);
