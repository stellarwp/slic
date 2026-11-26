<?php
/**
 * Input validation and sanitization utilities for git worktree support.
 *
 * Security-first approach to prevent path traversal and command injection attacks.
 */

namespace StellarWP\Slic;

/**
 * Validates and sanitizes a worktree directory name.
 *
 * Uses whitelist validation to prevent path traversal attacks.
 * Only allows alphanumeric characters, dashes, and underscores.
 *
 * @param string $name The proposed directory name.
 *
 * @return string|false Sanitized name or false if invalid.
 */
function slic_worktree_sanitize_dir_name( $name ) {
	// Type and empty check first
	if ( ! is_string( $name ) || '' === $name ) {
		return false;
	}

	// Length limits BEFORE modification
	if ( strlen( $name ) > 200 ) {
		return false;
	}

	// Whitelist validation: only alphanumeric, dash, underscore
	if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $name ) ) {
		return false;
	}

	// No further sanitization needed
	return $name;
}

/**
 * Validates a branch name for git worktree creation.
 *
 * Enforces git branch naming rules to prevent command injection
 * and ensure compatibility with git worktree commands.
 *
 * Git branch naming rules:
 * - No ".." (parent directory reference)
 * - No "~", "^", ":", "?", "*", "[" (special git characters)
 * - Cannot start or end with "/"
 * - Cannot contain consecutive slashes
 * - Cannot start with "-" or contain "@{"
 * - Length: 1-250 characters
 * - Anything else `git check-ref-format --branch` rejects
 *
 * @param string $branch The branch name to validate.
 *
 * @return bool True if valid, false otherwise.
 */
function slic_worktree_validate_branch( $branch ) {
	// Check for empty or null
	if ( ! is_string( $branch ) || empty( $branch ) ) {
		return false;
	}

	// Length validation (1-250 characters)
	if ( strlen( $branch ) < 1 || strlen( $branch ) > 250 ) {
		return false;
	}

	// Invalid patterns that violate git branch naming rules
	$invalid_patterns = [
		'/\.\./',              // No parent directory references
		'/[\~\^\:\?\*\[\\\]/', // No special git chars (added backslash)
		'/^\//',               // No leading slash
		'/\/$/',               // No trailing slash
		'/\/\//',              // No consecutive slashes
		'/\.lock$/',           // No .lock suffix
		'/[\x00-\x1F\x7F]/',   // No control characters
		'/\s/',                // No spaces
	];

	foreach ( $invalid_patterns as $pattern ) {
		if ( preg_match( $pattern, $branch ) ) {
			return false;
		}
	}

	// Check for reserved git ref name
	if ( $branch === '@' ) {
		return false;
	}

	// A name starting with a dash would be read as an option by the git commands it is passed to.
	if ( $branch[0] === '-' ) {
		return false;
	}

	// `--branch` expands the `@{-N}` shorthand to a real branch, so reject the reflog syntax first.
	if ( strpos( $branch, '@{' ) !== false ) {
		return false;
	}

	// Let git apply the rest of its refname rules, such as those for each `/`-separated component.
	exec( 'git check-ref-format --branch ' . escapeshellarg( $branch ) . ' 2>/dev/null', $output, $status );

	return 0 === $status;
}

/**
 * Creates a filesystem-safe worktree directory name from target and branch.
 *
 * Combines the target plugin/theme name with the branch name to create
 * a unique, filesystem-safe directory name for the worktree.
 *
 * Example:
 * - Target: "the-events-calendar"
 * - Branch: "fix/issue-123"
 * - Result: "the-events-calendar-fix-issue-123"
 *
 * @param string $target The target plugin/theme name.
 * @param string $branch The git branch name.
 *
 * @return string|false The sanitized directory name or false if invalid.
 */
function slic_worktree_create_dir_name( $target, $branch ) {
	// Validate branch name first
	if ( ! slic_worktree_validate_branch( $branch ) ) {
		return false;
	}

	// Validate target is not empty
	if ( empty( $target ) || ! is_string( $target ) ) {
		return false;
	}

	// Sanitize target first to prevent @ or other special chars
	$sanitized_target = slic_worktree_sanitize_dir_name( $target );
	if ( $sanitized_target === false ) {
		return false;
	}

	// Convert every character the directory name whitelist rejects to a dash for filesystem safety
	// e.g., "feature/new-thing" -> "feature-new-thing", "release/3.0.0" -> "release-3-0-0"
	$branch_slug = preg_replace( '/[^a-zA-Z0-9_-]/', '-', $branch );

	// Combine target and branch slug
	$dir_name = $sanitized_target . '-' . $branch_slug;

	// Apply final sanitization to ensure the result is filesystem-safe
	return slic_worktree_sanitize_dir_name( $dir_name );
}

/**
 * Returns the worktrees git records for a repository, from `git worktree list --porcelain`.
 *
 * The main repository is the first entry; git keeps the record of a worktree whose directory is missing.
 *
 * @param string $target_path The path to the repository.
 * @param string $error       Set to git's output if git cannot read the repository.
 *
 * @return array[]|false The worktrees, each with the 'path' key and the 'head', 'branch' and 'locked' keys git reports;
 *                       'locked' is the lock reason, empty for a lock without one. False if git cannot read the
 *                       repository, as when it is missing or not the top of a git repository.
 */
function slic_worktree_list( $target_path, &$error = '' ) {
	// Git walks up from a path that is not a repository, as an empty mount point, to the repository enclosing it.
	exec( 'git -C ' . escapeshellarg( $target_path ) . ' rev-parse --show-toplevel 2>&1', $toplevel, $status );

	if ( 0 !== $status || realpath( trim( implode( "\n", $toplevel ) ) ) !== realpath( $target_path ) ) {
		$error = "$target_path is not the top of a git repository" . ( 0 !== $status ? ': ' . implode( "\n", $toplevel ) : '' );

		return false;
	}

	exec( 'git -C ' . escapeshellarg( $target_path ) . ' worktree list --porcelain 2>&1', $output, $status );

	if ( 0 !== $status ) {
		$error = implode( "\n", $output );

		return false;
	}

	// Each entry is a `worktree <path>` line, then `HEAD <commit>`, `branch refs/heads/<name>`, `locked [<reason>]` and
	// other lines, then a blank line.
	$worktrees        = [];
	$current_worktree = [];

	foreach ( $output as $line ) {
		$line = trim( $line );

		// Empty line indicates end of a worktree entry
		if ( empty( $line ) ) {
			if ( ! empty( $current_worktree ) ) {
				$worktrees[]      = $current_worktree;
				$current_worktree = [];
			}
			continue;
		}

		if ( strpos( $line, 'worktree ' ) === 0 ) {
			$current_worktree['path'] = trim( substr( $line, 9 ) );
		} elseif ( strpos( $line, 'HEAD ' ) === 0 ) {
			$current_worktree['head'] = trim( substr( $line, 5 ) );
		} elseif ( strpos( $line, 'branch refs/heads/' ) === 0 ) {
			$current_worktree['branch'] = substr( $line, 18 );
		} elseif ( $line === 'locked' || strpos( $line, 'locked ' ) === 0 ) {
			$current_worktree['locked'] = trim( substr( $line, 6 ) );
		}
	}

	// Don't forget the last worktree if the output doesn't end with a blank line
	if ( ! empty( $current_worktree ) ) {
		$worktrees[] = $current_worktree;
	}

	return $worktrees;
}

/**
 * Returns the git common directory of a repository, where git keeps the records of its worktrees.
 *
 * @param string $repo_path The path to the repository.
 *
 * @return string|false The resolved absolute path, or false if git cannot read the repository.
 */
function slic_worktree_git_common_dir( $repo_path ) {
	exec( 'git -C ' . escapeshellarg( $repo_path ) . ' rev-parse --git-common-dir 2>/dev/null', $output, $status );

	if ( 0 !== $status || empty( $output[0] ) ) {
		return false;
	}

	// Git prints the directory relative to the repository when it is inside it, as `.git`.
	return realpath( strpos( $output[0], '/' ) === 0 ? $output[0] : $repo_path . '/' . $output[0] );
}

/**
 * Returns the git common directory of the repository a registered worktree was created from.
 *
 * @param array $wt_state The registered worktree stack state.
 *
 * @return string|false The one recorded at registration or, for a stack registered before slic recorded it, the one
 *                      the worktree `.git` file points to; false if neither is known, as when that worktree is offline.
 */
function slic_worktree_owner_git_dir( array $wt_state ) {
	if ( ! empty( $wt_state['worktree_git_dir'] ) ) {
		return $wt_state['worktree_git_dir'];
	}

	$worktree_path = $wt_state['worktree_full_path'] ?? '';
	$git_file      = '' !== $worktree_path ? @file_get_contents( $worktree_path . '/.git' ) : false;

	if ( false === $git_file || ! preg_match( '/^gitdir: (.+)$/m', $git_file, $matches ) ) {
		return false;
	}

	$gitdir = trim( $matches[1] );
	$gitdir = strpos( $gitdir, '/' ) === 0 ? $gitdir : $worktree_path . '/' . $gitdir;

	// The `.git` file points to `<common dir>/worktrees/<id>`.
	return realpath( dirname( $gitdir, 2 ) );
}

/**
 * Returns the reason git has a worktree locked, read from git's records so it works while the directory is unavailable.
 *
 * @param string            $target_path   The path to the main repository.
 * @param string            $worktree_path The path to the worktree directory.
 * @param string            $error         Set to why it cannot say, if it returns false.
 * @param string|false|null $owner_git_dir The git common directory of the repository the worktree was created from,
 *                                         from `slic_worktree_owner_git_dir()`, to refuse a target that is now another
 *                                         repository; false when it is unknown, to refuse; null not to check, as for
 *                                         a worktree not registered with slic.
 *
 * @return string|null|false The lock reason, empty for a lock without one, null if the worktree is not locked, or
 *                           false if it cannot say: git cannot read the repository, or it is not the one that owns
 *                           the worktree.
 */
function slic_worktree_lock_reason( $target_path, $worktree_path, &$error = '', $owner_git_dir = null ) {
	if ( false === $owner_git_dir ) {
		$error = "Cannot confirm which repository owns $worktree_path";

		return false;
	}

	$worktrees = slic_worktree_list( $target_path, $error );

	if ( false === $worktrees ) {
		$error = "Cannot read the git worktree records of $target_path: $error";

		return false;
	}

	// A target that is a symlink can be repointed to another repository, which has no record of the worktree's lock.
	if ( null !== $owner_git_dir && slic_worktree_git_common_dir( $target_path ) !== $owner_git_dir ) {
		$error = "$target_path is no longer the repository $worktree_path was created from ($owner_git_dir)";

		return false;
	}

	// The directory of a locked worktree can be missing, as on an unmounted disk: resolve its parent instead.
	$normalize = static function ( $path ) {
		return realpath( $path ) ?: ( realpath( dirname( $path ) ) ?: dirname( $path ) ) . '/' . basename( $path );
	};
	$wanted    = $normalize( $worktree_path );

	foreach ( $worktrees as $wt ) {
		if ( isset( $wt['path'], $wt['locked'] ) && $normalize( $wt['path'] ) === $wanted ) {
			return $wt['locked'];
		}
	}

	return null;
}

/**
 * Exits with an error, before anything is stopped, removed or unregistered, if git has a worktree locked or it cannot
 * say whether it is.
 *
 * @param string            $target_path   The path to the main repository.
 * @param string            $worktree_path The path to the worktree directory.
 * @param string|false|null $owner_git_dir The repository that owns the worktree, as `slic_worktree_lock_reason()` takes it.
 * @param string            $unchanged     What the command kept, said with the error.
 *
 * @return void
 */
function slic_worktree_require_unlocked( $target_path, $worktree_path, $owner_git_dir = null, $unchanged = 'Nothing was changed.' ) {
	$reason = slic_worktree_lock_reason( $target_path, $worktree_path, $error, $owner_git_dir );

	if ( null === $reason ) {
		return;
	}

	if ( false === $reason ) {
		echo "Error: $error\n";
		echo "$unchanged\n";
		exit( 1 );
	}

	echo "Error: The git worktree is locked" . ( '' !== $reason ? ": $reason" : '' ) . "\n";
	echo "  Worktree: $worktree_path\n";
	echo "$unchanged If the worktree is no longer in use, unlock it with:\n";
	echo "  git -C " . escapeshellarg( $target_path ) . " worktree unlock " . escapeshellarg( $worktree_path ) . "\n";
	exit( 1 );
}

/**
 * Checks if a directory is already a git worktree for a specific target repository.
 *
 * Uses 'git worktree list --porcelain' to verify if the directory is already
 * registered as a worktree in git. This prevents attempting to create a worktree
 * that already exists.
 *
 * @param string $target_path The path to the main repository (e.g., plugin directory).
 * @param string $worktree_path The full path to the potential worktree directory.
 * @param string $expected_branch Optional. The expected branch name to verify.
 *
 * @return array|false Array with worktree info if exists, false otherwise.
 *                     Array keys: 'path', 'branch', 'head'
 */
function slic_worktree_is_existing( $target_path, $worktree_path, $expected_branch = null ) {
	// Validate inputs
	if ( ! is_dir( $target_path ) || ! file_exists( $worktree_path ) ) {
		return false;
	}

	$worktrees = slic_worktree_list( $target_path );
	if ( false === $worktrees ) {
		return false;
	}

	// Normalize paths for comparison (resolve symlinks, etc.)
	// realpath() can return false for paths that don't exist yet or are not accessible.
	// Fall back to the original path in such cases for comparison.
	$worktree_real = realpath( $worktree_path );
	if ( $worktree_real === false ) {
		$worktree_real = $worktree_path;
	}

	// Search for matching worktree
	foreach ( $worktrees as $wt ) {
		if ( empty( $wt['path'] ) ) {
			continue;
		}

		$wt_real = realpath( $wt['path'] );
		if ( $wt_real === false ) {
			$wt_real = $wt['path'];
		}

		// Check if paths match
		if ( $wt_real === $worktree_real ) {
			// If expected branch is specified, verify it matches; a detached worktree has no branch and never matches
			if ( $expected_branch !== null ) {
				if ( ( $wt['branch'] ?? null ) !== $expected_branch ) {
					// Worktree exists but for wrong branch
					return [
						'path'   => $wt['path'],
						'branch' => $wt['branch'] ?? null,
						'head'   => $wt['head'] ?? null,
						'error'  => 'branch_mismatch',
					];
				}
			}

			// Worktree exists and branch matches (or not checked)
			return [
				'path'   => $wt['path'],
				'branch' => $wt['branch'] ?? null,
				'head'   => $wt['head'] ?? null,
			];
		}
	}

	return false;
}

/**
 * Returns the path of a target's repository, in the plugins or themes directory of the stack slic runs in.
 *
 * @param string $target The target plugin or theme name.
 *
 * @return string The absolute path to the target's repository.
 */
function slic_worktree_target_path( $target ) {
	return get_target_content_type( $target ) === 'theme' ? slic_themes_dir( $target ) : slic_plugins_dir( $target );
}

/**
 * Exits with an error unless a worktree directory is the requested branch's, as slic registered it and as git
 * has it checked out: branch names such as `feat/c` and `feat-c` map to the same directory.
 *
 * @param string      $branch   The requested branch.
 * @param array|null  $wt_state The registered worktree stack state, or null if not registered.
 * @param array|false $existing The result of `slic_worktree_is_existing()` for the requested branch.
 *
 * @return void
 */
function slic_worktree_require_branch( $branch, $wt_state, $existing ) {
	$registered  = $wt_state['worktree_branch'] ?? $branch;
	$checked_out = $existing === false ? $branch : ( $existing['branch'] ?? '(detached HEAD)' );

	if ( $registered === $branch && $checked_out === $branch ) {
		return;
	}

	echo "Error: The worktree directory for branch '$branch' belongs to another branch.\n";
	echo "  Registered branch: $registered\n";
	echo "  Checked out branch: $checked_out\n";
	exit( 1 );
}

/**
 * Registers a worktree stack and writes its state file.
 *
 * @param string $base_stack_id      The base stack the worktree belongs to.
 * @param string $target             The target plugin or theme name.
 * @param string $target_path        The path to the target's repository.
 * @param string $worktree_dir       The worktree directory name.
 * @param string $branch             The branch checked out in the worktree.
 * @param string $worktree_full_path The path to the worktree directory.
 *
 * @return string|false The worktree stack ID, or false if registration failed.
 */
function slic_worktree_register( $base_stack_id, $target, $target_path, $worktree_dir, $branch, $worktree_full_path ) {
	$worktree_stack_id = $base_stack_id . '@' . $worktree_dir;

	$worktree_state = [
		'stack_id'             => $worktree_stack_id,
		'is_worktree'          => true,
		'base_stack_id'        => $base_stack_id,
		// The branch merge targets: the one the target repository is on now, `HEAD` when it is detached.
		'base_branch'          => trim( (string) shell_exec( 'git -C ' . escapeshellarg( $target_path ) . ' rev-parse --abbrev-ref HEAD 2>/dev/null' ) ),
		'worktree_target'      => $target,
		'worktree_target_path' => $target_path,
		// Where git keeps the worktree's records and lock, should the target path later lead to another repository.
		'worktree_git_dir'     => slic_worktree_git_common_dir( $target_path ),
		'worktree_dir'         => $worktree_dir,
		'worktree_branch'      => $branch,
		'worktree_full_path'   => $worktree_full_path,
		'project_name'         => slic_stacks_get_project_name( $worktree_stack_id ),
		'state_file'           => slic_stacks_get_state_file( $worktree_stack_id ),
		// XDebug port and key are allocated inside slic_stacks_register() while it holds the registry lock.
		'target'               => $target,
		'status'               => 'created',
	];

	// Write the stack state file before registering, so the registry never points at a missing state file.
	$state_file = slic_stacks_write_worktree_state_file( $worktree_stack_id, $base_stack_id, $target );

	if ( ! slic_stacks_register( $worktree_stack_id, $worktree_state ) ) {
		unlink( $state_file );

		return false;
	}

	return $worktree_stack_id;
}
