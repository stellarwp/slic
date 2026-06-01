<?php
/**
 * Cross-platform Terminal User Interface (TUI) functions.
 *
 * Provides interactive selection lists with fuzzy search capabilities.
 * Supports Windows, macOS, and Linux with pure PHP implementation.
 */

namespace StellarWP\Slic;

/**
 * Detect the current platform
 *
 * @return string 'windows', 'unix', or 'unknown'
 */
function tui_platform() {
	static $platform = null;

	if ( $platform === null ) {
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$platform = 'windows';
		} elseif ( PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'BSD' ) {
			$platform = 'unix';
		} else {
			$platform = 'unknown';
		}
	}

	return $platform;
}

/**
 * Set terminal to raw mode on Unix systems
 *
 * @return string|false Original stty settings for restoration, or false on failure
 */
function tui_unix_raw_mode() {
	// Save current settings
	$stty_settings = shell_exec( 'stty -g 2>/dev/null' );

	if ( $stty_settings === null ) {
		return false;
	}

	// Set raw mode: disable echo, canonical mode, and signals
	shell_exec( 'stty -icanon -echo -isig 2>/dev/null' );

	return trim( $stty_settings );
}

/**
 * Restore terminal to normal mode on Unix systems
 *
 * @param string $settings Original stty settings
 */
function tui_unix_restore( $settings ) {
	if ( $settings ) {
		shell_exec( "stty " . escapeshellarg( $settings ) . " 2>/dev/null" );
	}
}

/**
 * Check if running in a TTY on Unix
 *
 * @return bool
 */
function tui_unix_is_tty() {
	return function_exists( 'posix_isatty' )
		&& posix_isatty( STDIN )
		&& posix_isatty( STDOUT );
}

/**
 * Set terminal to raw mode on Windows
 *
 * @return bool Success
 */
function tui_windows_raw_mode() {
	// Enable VT100 support for ANSI escape codes (Windows 10+)
	if ( function_exists( 'sapi_windows_vt100_support' ) ) {
		sapi_windows_vt100_support( STDOUT, true );
		sapi_windows_vt100_support( STDERR, true );
	}

	// Set stream to non-blocking mode for character-by-character reading
	stream_set_blocking( STDIN, false );

	return true;
}

/**
 * Restore terminal on Windows
 */
function tui_windows_restore() {
	stream_set_blocking( STDIN, true );
}

/**
 * Check if running in a TTY on Windows
 *
 * @return bool
 */
function tui_windows_is_tty() {
	// Check if STDIN/STDOUT are connected to console
	// On Windows, we can check if stream_isatty exists (PHP 7.2+)
	if ( function_exists( 'stream_isatty' ) ) {
		return stream_isatty( STDIN ) && stream_isatty( STDOUT );
	}

	// Fallback: check if we can get console mode
	// If not redirected, this should work
	return ! getenv( 'CI' ) && PHP_SAPI !== 'cgi-fcgi';
}

/**
 * Set terminal to raw mode (cross-platform)
 *
 * @return mixed Platform-specific settings for restoration
 */
function tui_terminal_raw_mode() {
	$platform = tui_platform();

	switch ( $platform ) {
		case 'unix':
			return [ 'platform' => 'unix', 'settings' => tui_unix_raw_mode() ];

		case 'windows':
			tui_windows_raw_mode();
			return [ 'platform' => 'windows', 'settings' => true ];

		default:
			return false;
	}
}

/**
 * Restore terminal to normal mode (cross-platform)
 *
 * @param mixed $state State returned from tui_terminal_raw_mode()
 */
function tui_terminal_restore( $state ) {
	if ( ! $state || ! is_array( $state ) || ! isset( $state['platform'] ) ) {
		return;
	}

	switch ( $state['platform'] ) {
		case 'unix':
			tui_unix_restore( $state['settings'] );
			break;

		case 'windows':
			tui_windows_restore();
			break;
	}
}

/**
 * Check if running in a TTY (cross-platform)
 *
 * @return bool
 */
function tui_is_tty() {
	$platform = tui_platform();

	switch ( $platform ) {
		case 'unix':
			return tui_unix_is_tty();

		case 'windows':
			return tui_windows_is_tty();

		default:
			return false;
	}
}

/**
 * Read a single character from STDIN on Unix
 *
 * @return string|false
 */
function tui_unix_read_char() {
	return fread( STDIN, 1 );
}

/**
 * Read a single character from STDIN on Windows (non-blocking)
 *
 * @return string|false
 */
function tui_windows_read_char() {
	// Non-blocking read
	$char = fread( STDIN, 1 );

	// If nothing available, sleep briefly and try again
	if ( $char === false || $char === '' ) {
		usleep( 10000 ); // 10ms
		return false;
	}

	return $char;
}

/**
 * Read a single key press (cross-platform)
 * Handles multi-byte sequences for arrow keys, ESC, etc.
 *
 * @return array ['type' => 'char|arrow|enter|escape|ctrl_c|backspace|none', 'value' => ...]
 */
function tui_read_key() {
	$platform = tui_platform();

	// Read first character
	if ( $platform === 'windows' ) {
		// Windows: use non-blocking read with retry loop
		$c        = false;
		$attempts = 0;
		while ( $c === false && $attempts < 100 ) {
			$c = tui_windows_read_char();
			$attempts++;
			if ( $c === false ) {
				usleep( 10000 ); // 10ms between attempts
			}
		}
		if ( $c === false ) {
			return [ 'type' => 'none', 'value' => null ];
		}
	} else {
		// Unix: blocking read
		$c = tui_unix_read_char();
		if ( $c === false ) {
			return [ 'type' => 'none', 'value' => null ];
		}
	}

	// Check for escape sequences (arrow keys, etc.)
	if ( $c === "\033" || $c === "\x1b" ) {
		// Try to read next characters for escape sequence
		// Set a short timeout for reading the rest of the sequence
		$next = fread( STDIN, 1 );

		if ( $next === '[' || $next === 'O' ) {
			// ANSI escape sequence
			$arrow = fread( STDIN, 1 );
			if ( $arrow === false || $arrow === '' ) {
				// Failed to read arrow key, treat as ESC
				return [ 'type' => 'escape', 'value' => null ];
			}
			switch ( $arrow ) {
				case 'A':
					return [ 'type' => 'arrow', 'value' => 'up' ];
				case 'B':
					return [ 'type' => 'arrow', 'value' => 'down' ];
				case 'C':
					return [ 'type' => 'arrow', 'value' => 'right' ];
				case 'D':
					return [ 'type' => 'arrow', 'value' => 'left' ];
			}
		} elseif ( $next === false || $next === '' ) {
			// Just ESC key pressed, no sequence following
			return [ 'type' => 'escape', 'value' => null ];
		}

		// Unknown escape sequence, treat as ESC
		return [ 'type' => 'escape', 'value' => null ];
	}

	// Check for Ctrl+C
	if ( $c === "\003" || $c === "\x03" ) {
		return [ 'type' => 'ctrl_c', 'value' => null ];
	}

	// Check for Enter (handle both Unix \n and Windows \r\n)
	if ( $c === "\n" || $c === "\r" ) {
		// On Windows, might need to consume following \n
		if ( $c === "\r" && $platform === 'windows' ) {
			// Consume the following \n character if present
			fread( STDIN, 1 );
		}
		return [ 'type' => 'enter', 'value' => null ];
	}

	// Check for Backspace (multiple possible codes)
	if ( $c === "\177" || $c === "\x7f" || $c === "\010" || $c === "\x08" ) {
		return [ 'type' => 'backspace', 'value' => null ];
	}

	// Handle Windows arrow keys (if not using ANSI codes)
	// Windows getch() returns 0x00 or 0xE0 followed by scan code
	if ( ( $c === "\x00" || $c === "\xe0" ) && $platform === 'windows' ) {
		$scan = fread( STDIN, 1 );
		if ( $scan === false || $scan === '' ) {
			// Failed to read scan code, return as regular char
			return [ 'type' => 'char', 'value' => $c ];
		}
		switch ( $scan ) {
			case 'H':
				return [ 'type' => 'arrow', 'value' => 'up' ];
			case 'P':
				return [ 'type' => 'arrow', 'value' => 'down' ];
			case 'M':
				return [ 'type' => 'arrow', 'value' => 'right' ];
			case 'K':
				return [ 'type' => 'arrow', 'value' => 'left' ];
		}
	}

	// Regular character
	return [ 'type' => 'char', 'value' => $c ];
}

/**
 * Clear the screen
 */
function tui_clear_screen() {
	echo "\033[2J";
}

/**
 * Move cursor to specific position (1-indexed)
 *
 * @param int $row Row position
 * @param int $col Column position
 */
function tui_move_cursor( $row, $col ) {
	echo "\033[{$row};{$col}H";
}

/**
 * Clear from cursor to end of screen
 */
function tui_clear_to_end() {
	echo "\033[J";
}

/**
 * Hide cursor
 */
function tui_hide_cursor() {
	echo "\033[?25l";
}

/**
 * Show cursor
 */
function tui_show_cursor() {
	echo "\033[?25h";
}

/**
 * Move cursor to beginning of line
 */
function tui_cursor_home() {
	echo "\r";
}

/**
 * Move cursor up by N lines
 *
 * @param int $n Number of lines to move up
 */
function tui_move_cursor_up( $n ) {
	if ( $n > 0 ) {
		echo "\033[{$n}A";
	}
}

/**
 * Filter items by search term (substring match, case-insensitive)
 *
 * @param array  $items  All items
 * @param string $search Search term
 * @return array Filtered items
 */
function tui_filter_items( array $items, $search ) {
	if ( empty( $search ) ) {
		return $items;
	}

	$search_lower = strtolower( $search );

	return array_values(
		array_filter(
			$items,
			function ( $item ) use ( $search_lower ) {
				return strpos( strtolower( $item ), $search_lower ) !== false;
			}
		)
	);
}

/**
 * Render the TUI display
 *
 * @param array       $visible_items  Items to display (after filtering)
 * @param int         $selected_index Currently highlighted index
 * @param string      $search         Current search term
 * @param string|null $current        Current/active item (marked with ✓)
 * @param string      $prompt         Prompt text
 * @param int         $scroll_offset  Scroll position
 * @param int         $max_visible    Maximum items to show
 * @return int Number of lines rendered
 */
function tui_render( $visible_items, $selected_index, $search, $current, $prompt, $scroll_offset, $max_visible ) {
	// Clear from cursor to end (preserves terminal history)
	tui_cursor_home();
	tui_clear_to_end();

	// Track lines rendered so we can move cursor back during re-renders and cleanup.
	// We count each echo statement that produces a visible line, including the search
	// line which now has a trailing newline so cursor positioning is correct.
	$lines_rendered = 0;

	// Display prompt
	echo colorize( "<light_cyan>$prompt</light_cyan>\n" );
	$lines_rendered++;

	// Calculate visible window
	$total_items  = count( $visible_items );
	$window_items = array_slice( $visible_items, $scroll_offset, $max_visible );

	// Display items
	foreach ( $window_items as $idx => $item ) {
		$absolute_idx = $scroll_offset + $idx;
		$is_selected  = ( $absolute_idx === $selected_index );
		$is_current   = ( $item === $current );

		// Prefix
		$prefix = $is_selected ? '> ' : '  ';

		// Suffix for current item
		$suffix = $is_current ? ' ✓' : '';

		// Color
		if ( $is_selected ) {
			echo colorize( "<magenta>{$prefix}{$item}{$suffix}</magenta>\n" );
		} else {
			echo "{$prefix}{$item}{$suffix}\n";
		}
		$lines_rendered++;
	}

	// Scroll indicators
	if ( $scroll_offset > 0 ) {
		echo colorize( "<yellow>  ↑ " . $scroll_offset . " more above...</yellow>\n" );
		$lines_rendered++;
	}
	if ( $scroll_offset + $max_visible < $total_items ) {
		$remaining = $total_items - ( $scroll_offset + $max_visible );
		echo colorize( "<yellow>  ↓ $remaining more below...</yellow>\n" );
		$lines_rendered++;
	}

	// Empty line if no results
	if ( empty( $visible_items ) ) {
		echo colorize( "<yellow>  No matches found</yellow>\n" );
		$lines_rendered++;
	}

	// Blank line before search
	echo "\n";
	$lines_rendered++;

	// Search box
	echo colorize( "<light_cyan>Search:</light_cyan> " ) . $search . "\n";
	$lines_rendered++;

	// Show cursor
	tui_show_cursor();

	return $lines_rendered;
}

/**
 * Display an interactive selection list with fuzzy search
 *
 * @param array       $items   Array of items to select from
 * @param string|null $current Currently selected item (will be marked)
 * @param string      $prompt  Prompt text to display
 * @return string|null Selected item or null if cancelled
 */
function tui_select( array $items, $current = null, $prompt = 'Select:' ) {
	if ( empty( $items ) ) {
		return null;
	}

	// Check if we're in a TTY
	if ( ! tui_is_tty() ) {
		echo colorize( "<yellow>Interactive mode not available in this environment.</yellow>\n" );
		echo colorize( "<light_cyan>Available targets:</light_cyan>\n" );
		foreach ( $items as $item ) {
			$marker = ( $item === $current ) ? ' ✓' : '';
			echo "  - {$item}{$marker}\n";
		}
		echo "\nUsage: slic use <target>\n";
		return null;
	}

	// Save terminal state
	$terminal_state = tui_terminal_raw_mode();

	if ( $terminal_state === false ) {
		echo colorize( "<red>Failed to initialize terminal.</red>\n" );
		return null;
	}

	// Setup cleanup handlers
	$cleanup_done = false;
	$lines_rendered = 0;
	$cleanup = function ( $selected_item = null ) use ( $terminal_state, &$cleanup_done, &$lines_rendered ) {
		if ( $cleanup_done ) {
			return;
		}
		$cleanup_done = true;

		// Move cursor back to TUI start position
		if ( $lines_rendered > 0 ) {
			tui_move_cursor_up( $lines_rendered );
			tui_cursor_home();
		}

		// Clear only TUI content (from cursor to end)
		tui_clear_to_end();

		// Restore terminal
		tui_show_cursor();
		tui_terminal_restore( $terminal_state );

		// Show confirmation message if item was selected
		if ( $selected_item !== null ) {
			echo colorize( "<green>Selected: $selected_item</green>\n" );
		}
	};

	// Register shutdown handler
	register_shutdown_function( $cleanup );

	// Hide cursor initially
	tui_hide_cursor();

	// State
	$search         = '';
	$selected_index = 0;
	$scroll_offset  = 0;
	$max_visible    = 12;

	// Filter items
	$filtered = $items;

	// Initial render - capture lines rendered
	$lines_rendered = tui_render( $filtered, $selected_index, $search, $current, $prompt, $scroll_offset, $max_visible );

	// Main loop
	try {
		while ( true ) {
			$key = tui_read_key();

			if ( $key['type'] === 'none' ) {
				continue;
			}

			switch ( $key['type'] ) {
				case 'escape':
				case 'ctrl_c':
					// Cancel - restore and return null
					$cleanup( null );
					return null;

				case 'enter':
					// Select current item
					$selected_item = null;
					if ( ! empty( $filtered ) && isset( $filtered[ $selected_index ] ) ) {
						$selected_item = $filtered[ $selected_index ];
					}
					$cleanup( $selected_item );
					return $selected_item;

				case 'arrow':
					if ( $key['value'] === 'up' && $selected_index > 0 ) {
						$selected_index--;

						// Adjust scroll if needed
						if ( $selected_index < $scroll_offset ) {
							$scroll_offset = $selected_index;
						}
					} elseif ( $key['value'] === 'down' && $selected_index < count( $filtered ) - 1 ) {
						$selected_index++;

						// Adjust scroll if needed
						if ( $selected_index >= $scroll_offset + $max_visible ) {
							$scroll_offset = $selected_index - $max_visible + 1;
						}
					}
					break;

				case 'backspace':
					if ( strlen( $search ) > 0 ) {
						$search         = substr( $search, 0, -1 );
						$filtered       = tui_filter_items( $items, $search );
						$selected_index = 0;
						$scroll_offset  = 0;
					}
					break;

				case 'char':
					// Add to search (only printable characters)
					if ( ord( $key['value'] ) >= 32 && ord( $key['value'] ) < 127 ) {
						$search        .= $key['value'];
						$filtered       = tui_filter_items( $items, $search );
						$selected_index = 0;
						$scroll_offset  = 0;
					}
					break;
			}

			// Move cursor back to start of TUI before re-rendering
			if ( $lines_rendered > 0 ) {
				tui_move_cursor_up( $lines_rendered );
				tui_cursor_home();
			}

			// Re-render and capture new line count
			$lines_rendered = tui_render( $filtered, $selected_index, $search, $current, $prompt, $scroll_offset, $max_visible );
		}
	} finally {
		// Always restore terminal
		$cleanup();
	}
}
