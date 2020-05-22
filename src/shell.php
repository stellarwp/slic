<?php
/**
 * Interactive shell dedicated wrappers.
 */

namespace Tribe\Test;

/**
 * Executes a wp-cli command in the stack, echoes and returns its output.
 *
 * @param array $command The command to execute, e.g. `['plugin', 'list', '--status=active']`.
 * @param bool  $quiet   Whether to echo the command output or not.
 *
 * @return string The command output.
 */
function wp_cli( array $command = [ 'version' ], $quiet = false ) {
	$output = cli()( $command )( 'string_output' );
	if ( ! $quiet ) {
		echo $output;
	}

	return $output;
}

/**
 * Returns the current terminal window/tab lines and columns.
 *
 * The check is OS-aware and will use, on Windows, the correct command.
 *
 * @return array<int> The number of, respectively, lines and columns.
 */
function get_terminal_lines_cols() {
	$lines   = null;
	$columns = null;

	if ( 'Windows' === os() ) {
		exec( 'mode', $output, $status );

		if ( 0 !== $status ) {
			// We cannot fetch information, bail.
			return [ $lines, $columns ];
		}

		/*
		 * Output looks like this, it's localized in the OS language.
		 *
		 * Status for device CON:
		 * ----------------------
		 *     Lines:          9001
		 *     Columns:        120
		 *     Keyboard rate:  31
		 *     Keyboard delay: 1
		 *     Code page:      437
		 */

		foreach ( $output as $line ) {
			// Since the output might be localized to the OS language, we need to parse the format, not the words.
			if ( ! preg_match( '/\\s{4}\\w+:\\s+(?<val>\\d+)/', $line, $m ) ) {
				continue;
			}

			$lines   = $lines ? $lines : $m['val'];
			$columns = $m['val'];
		}
	} else {
		exec( 'resize', $output, $status );

		if ( 0 !== $status ) {
			// We cannot fetch information, bail.
			return [ $lines, $columns ];
		}

		foreach ( $output as $line ) {
			if ( ! preg_match( '/(?<key>(COLUMNS|LINES))=(?<val>[0-9]+)/', $line, $m ) ) {
				continue;
			}
			$key    = strtolower( $m['key'] );
			${$key} = (int) $m['val'];
		}
	}

	return [ $lines, $columns ];
}

/**
 * Set up the terminal, by means of environment vars, to improve the output and behavior of tric processes.
 *
 * @param bool $force Whether to force the re-fetch and re-set of the terminal or to skip if already done.
 */
function setup_terminal( $force = false ) {
	static $setup;

	if ( ! $force && $setup ) {
		return;
	}

	$setup = true;

	// Setup the LINES and COLUMNS env vars to make sure commands will not wrap on themselves during execution.
	list( $lines, $columns ) = get_terminal_lines_cols();
	if ( ! empty( $lines ) ) {
		putenv( "LINES={$lines}" );
	}
	if ( ! empty( $columns ) ) {
		putenv( "COLUMNS={$columns}" );
	}
}
