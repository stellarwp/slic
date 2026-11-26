#!/usr/bin/env php
<?php
/**
 * Main entry point for terminal completions.
 *
 * This script is called by shell completion scripts (bash/zsh/fish) to generate
 * completion suggestions based on the current command line context.
 *
 * Usage:
 *   php complete.php --line="<line>" --point=<point> --words="<one word per line>" --cword=<cword>
 *
 * Completions are printed one per line.
 *
 * @package StellarWP\Slic\Completions
 */

namespace StellarWP\Slic\Completions;

// Suppress all output except completions
error_reporting( 0 );
ini_set( 'display_errors', '0' );

// Parse command line arguments
$options = getopt( '', [ 'line:', 'point:', 'words:', 'cword:' ] );

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

// Parse the command line: one word per line, so a word can contain spaces
$words = [];
if ( isset( $options['words'] ) ) {
	$words = explode( "\n", $options['words'] );
}
$current_word_index = isset( $options['cword'] ) ? (int) $options['cword'] : 0;

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
	// Bash splits --stack=<path> at the =, so the words are --stack, = and the path.
	if ( $prev_word === '--stack' || ( $prev_word === '=' && ( $words[ $current_word_index - 2 ] ?? '' ) === '--stack' ) ) {
		$completing_stack_option = true;
	}
}

// Skip the global flags before the command, as slic.php does: -q, --stack <path> and --stack=<path>.
$command_index = 0;
$SLIC_STACK_OVERRIDE = null;
while ( $command_index < $current_word_index ) {
	$word = $words[ $command_index ];
	if ( $word === '-q' ) {
		$command_index++;
	} elseif ( strpos( $word, '--stack=' ) === 0 ) {
		$SLIC_STACK_OVERRIDE = substr( $word, strlen( '--stack=' ) );
		$command_index++;
	} elseif ( $word === '--stack' ) {
		$path_index = ( $words[ $command_index + 1 ] ?? '' ) === '=' ? $command_index + 2 : $command_index + 1;
		$SLIC_STACK_OVERRIDE = $words[ $path_index ] ?? null;
		$command_index = $path_index + 1;
	} else {
		break;
	}
}

if ( $completing_stack_option ) {
	// Complete with stack paths
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
} elseif ( $current_word_index === $command_index ) {
	// Complete top-level commands
	// CommandCompleter already filters by prefix, so no need for additional filtering
	$completer = new CommandCompleter();
	$completions = $completer->get_completions( $current_word );
} else {
	// Delegate to specific completers based on the command; slic_current_stack() reads $SLIC_STACK_OVERRIDE.
	$words = array_slice( $words, $command_index );
	$command = $words[0] ?? '';

	// Use command-specific completers
	switch ( $command ) {
		case 'use':
			$completer = new UseCompleter( new CompletionCache() );
			$completions = $completer->get_completions( $current_word );
			break;

		case 'stack':
			$completer = new StackCompleter();
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'airplane-mode':
		case 'build-prompt':
		case 'build-subdir':
		case 'cache':
		case 'debug':
		case 'interactive':
			$completer = new ToggleCompleter();
			$completions = $completer->get_completions( $current_word );
			break;

		case 'xdebug':
			$completer = new XdebugCompleter();
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'worktree':
			$completer = new WorktreeCompleter();
			$completions = $completer->get_completions( $words, $current_word );
			break;

		case 'php-version':
			$completer = new PhpVersionCompleter();
			$completions = $completer->get_completions( $words, $current_word );
			break;

		default:
			// No specific completer for this command
			$completions = [];
			break;
	}
}

// Output completions, one per line, so a completion can contain spaces
if ( ! empty( $completions ) ) {
	echo implode( "\n", $completions );
}

exit( 0 );
