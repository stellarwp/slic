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
