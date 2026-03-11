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
 * - Length: 1-250 characters
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

	return true;
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

	// Convert slashes in branch name to dashes for filesystem safety
	// e.g., "feature/new-thing" -> "feature-new-thing"
	$branch_slug = str_replace( '/', '-', $branch );

	// Combine target and branch slug
	$dir_name = $sanitized_target . '-' . $branch_slug;

	// Apply final sanitization to ensure the result is filesystem-safe
	return slic_worktree_sanitize_dir_name( $dir_name );
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

	// Get worktree list in porcelain format for parsing
	$escaped_target = escapeshellarg( $target_path );
	$cmd = "git -C " . $escaped_target . " worktree list --porcelain 2>/dev/null";
	$output = shell_exec( $cmd );

	if ( empty( $output ) ) {
		return false;
	}

	// Parse porcelain output
	// Format:
	// worktree /path/to/worktree
	// HEAD <commit-hash>
	// branch refs/heads/<branch-name>
	// <blank line>
	//
	// Note: The main repository appears as the first entry in git worktree list.
	// This is normal git behavior and not an error.
	$worktrees = [];
	$current_worktree = [];

	$lines = explode( "\n", trim( $output ) );
	foreach ( $lines as $line ) {
		$line = trim( $line );

		// Empty line indicates end of a worktree entry
		if ( empty( $line ) ) {
			if ( ! empty( $current_worktree ) ) {
				$worktrees[] = $current_worktree;
				$current_worktree = [];
			}
			continue;
		}

		// Parse line
		if ( strpos( $line, 'worktree ' ) === 0 ) {
			$current_worktree['path'] = trim( substr( $line, 9 ) );
		} elseif ( strpos( $line, 'HEAD ' ) === 0 ) {
			$current_worktree['head'] = trim( substr( $line, 5 ) );
		} elseif ( strpos( $line, 'branch ' ) === 0 ) {
			$branch_ref = trim( substr( $line, 7 ) );
			// Extract branch name from refs/heads/branch-name
			if ( strpos( $branch_ref, 'refs/heads/' ) === 0 ) {
				$current_worktree['branch'] = substr( $branch_ref, 11 );
			}
		}
	}

	// Don't forget the last worktree if file doesn't end with blank line
	if ( ! empty( $current_worktree ) ) {
		$worktrees[] = $current_worktree;
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
			// If expected branch is specified, verify it matches
			if ( $expected_branch !== null && ! empty( $wt['branch'] ) ) {
				if ( $wt['branch'] !== $expected_branch ) {
					// Worktree exists but for wrong branch
					return [
						'path'   => $wt['path'],
						'branch' => $wt['branch'],
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
