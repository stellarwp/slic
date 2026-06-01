#!/usr/bin/env php
<?php
/**
 * Main entry point for terminal completions.
 *
 * This script is called by shell completion scripts (bash/zsh/fish) to generate
 * completion suggestions based on the current command line context.
 *
 * Usage:
 *   php complete.php --line="<line>" --point=<point> --words="<words>" --cword=<cword>
 *   php complete.php --shell=fish --words="<words>" --current="<current>"
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

// Suppress all output except completions
error_reporting( 0 );
ini_set( 'display_errors', '0' );

// Parse command line arguments
$options = getopt( '', [ 'line:', 'point:', 'words:', 'cword:', 'shell:', 'current:' ] );

if ( empty( $options ) ) {
	exit( 0 );
}

// Load required files
require_once __DIR__ . '/CompletionCache.php';
require_once __DIR__ . '/completers/CommandCompleter.php';
require_once __DIR__ . '/completers/UseCompleter.php';
require_once __DIR__ . '/completers/StackCompleter.php';
require_once __DIR__ . '/completers/ToggleCompleter.php';
require_once __DIR__ . '/completers/XdebugCompleter.php';
require_once __DIR__ . '/completers/WorktreeCompleter.php';
require_once __DIR__ . '/completers/PhpVersionCompleter.php';

// Parse the command line
$shell = $options['shell'] ?? 'bash';
$words = [];
$current_word_index = 0;
$current_word = '';

if ( $shell === 'fish' ) {
	// Fish provides words as a string (already-completed tokens) and current token being typed
	if ( isset( $options['words'] ) ) {
		$words = explode( ' ', $options['words'] );
	}
	$current_word = $options['current'] ?? '';
	// If current word is empty (trailing space), we're completing the next word
	// Otherwise, we're completing the last word in the list
	$current_word_index = empty( $current_word ) ? count( $words ) : count( $words ) - 1;
} else {
	// Bash/Zsh format
	if ( isset( $options['words'] ) ) {
		$words = explode( ' ', $options['words'] );
	}
	$current_word_index = isset( $options['cword'] ) ? (int) $options['cword'] : 0;
	$current_word = $words[ $current_word_index ] ?? '';
}

// Ensure we have at least the command name
if ( empty( $words ) || count( $words ) < 1 ) {
	exit( 0 );
}

// Remove 'slic' from the beginning if present
if ( isset( $words[0] ) && ( $words[0] === 'slic' || basename( $words[0] ) === 'slic' ) ) {
	array_shift( $words );
	$current_word_index--;
}

// Adjust for negative index (shouldn't happen, but be safe)
if ( $current_word_index < 0 ) {
	$current_word_index = 0;
}

// Update current_word after index adjustment
$current_word = $words[ $current_word_index ] ?? '';

// Initialize cache
$cache = new CompletionCache();

// Determine what to complete
$completions = [];

// Check if we're completing a --stack option value
// Handle both --stack=<path> and --stack <path> formats
$completing_stack_option = false;

// Check if current word starts with --stack= (for "--stack=<path>" format)
if ( strpos( $current_word, '--stack=' ) === 0 ) {
	$completing_stack_option = true;
}

// Check if previous word is --stack (for "--stack <path>" format)
if ( ! $completing_stack_option && $current_word_index > 0 ) {
	$prev_word = $words[ $current_word_index - 1 ] ?? '';
	if ( $prev_word === '--stack' ) {
		$completing_stack_option = true;
	}
}

if ( $completing_stack_option ) {
	// Complete with stack paths
	$completer = new StackCompleter( $cache );
	// Get stack IDs from the StackCompleter
	require_once dirname( dirname( __DIR__ ) ) . '/src/stacks.php';
	$stacks = \StellarWP\Slic\slic_stacks_list();
	if ( is_array( $stacks ) ) {
		$stack_paths = array_keys( $stacks );
		// If current word has --stack= prefix, we need to preserve it
		if ( strpos( $current_word, '--stack=' ) === 0 ) {
			$prefix = substr( $current_word, strlen( '--stack=' ) );
			$stack_paths = array_filter( $stack_paths, function( $path ) use ( $prefix ) {
				return empty( $prefix ) || strpos( $path, $prefix ) === 0;
			} );
			// Prepend --stack= to each path
			$completions = array_map( function( $path ) {
				return '--stack=' . $path;
			}, $stack_paths );
		} else {
			// For "--stack <path>" format, just return the paths
			$prefix = $current_word;
			$completions = array_filter( $stack_paths, function( $path ) use ( $prefix ) {
				return empty( $prefix ) || strpos( $path, $prefix ) === 0;
			} );
		}
	}
} elseif ( $current_word_index === 0 || empty( $words ) ) {
	// Complete top-level commands
	// CommandCompleter already filters by prefix, so no need for additional filtering
	$completer = new CommandCompleter( $cache );
	$completions = $completer->get_completions( $current_word );
} else {
	// Delegate to specific completers based on the command
	$command = $words[0] ?? '';

	// Use command-specific completers
	switch ( $command ) {
		case 'use':
			$completer = new UseCompleter( $cache );
			$completions = $completer->get_completions( $current_word );
			break;

		case 'stack':
			$completer = new StackCompleter( $cache );
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'airplane-mode':
		case 'build-prompt':
		case 'build-subdir':
		case 'cache':
		case 'debug':
		case 'interactive':
			$completer = new ToggleCompleter( $cache );
			$completions = $completer->get_completions( $current_word );
			break;

		case 'xdebug':
			$completer = new XdebugCompleter( $cache );
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'worktree':
			$completer = new WorktreeCompleter( $cache );
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'php-version':
			$completer = new PhpVersionCompleter( $cache );
			$completions = $completer->get_completions( $words, $current_word );
			break;

		default:
			// No specific completer for this command
			$completions = [];
			break;
	}
}

// Output completions
if ( ! empty( $completions ) ) {
	echo implode( ' ', $completions );
}

exit( 0 );
