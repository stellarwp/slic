<?php
/**
 * Shell-free process execution with literal argument boundaries.
 */

namespace StellarWP\Slic;

/**
 * Run raw arguments and capture stdout while leaving stderr visible.
 *
 * @param string[] $arguments Executable followed by literal, unquoted arguments.
 * @param array<string,string> $environment Overrides for the inherited child environment.
 *
 * @return array{status: int, stdout: string} The exit status and unmodified stdout.
 */
function process_argv( array $arguments, array $environment = [] ): array {
	// A file cannot fill a pipe buffer while the child is running, including on Windows.
	$output = tmpfile();

	if ( $output === false ) {
		throw new \RuntimeException( 'Could not create a process output buffer.' );
	}

	try {
		// Captured commands get EOF on stdin and leave errors visible on stderr.
		// Rewind after the child exits to read stdout from the beginning of the file.
		$status = run_process_argv( $arguments, [ [ 'file', process_null_device(), 'r' ], $output, STDERR ], $environment );
		rewind( $output );
		$text = stream_get_contents( $output );
	} finally {
		fclose( $output );
	}

	return [ 'status' => $status, 'stdout' => $text ];
}

/**
 * Run raw arguments with live output and interruptible child supervision.
 *
 * @param string[] $arguments Executable followed by literal, unquoted arguments.
 * @param array<string,string> $environment Overrides for the inherited child environment.
 * @param bool $stdin_null Whether to close input instead of inheriting the terminal.
 *
 * @return int The child exit status.
 */
function process_argv_realtime( array $arguments, array $environment = [], bool $stdin_null = false ): int {
	setup_terminal();
	echo PHP_EOL;
	$input = $stdin_null ? [ 'file', process_null_device(), 'r' ] : STDIN;

	return run_process_argv( $arguments, [ $input, STDOUT, STDERR ], $environment );
}

/**
 * Return the platform's null device for descriptor-based input redirection.
 *
 * @return string The null device path.
 */
function process_null_device(): string {
	return DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
}

/**
 * Execute an argument vector directly, without parsing or escaping a shell command.
 *
 * Polling allows PHP signal handlers to run while the child is active. Shutdown
 * terminates the local child; remote processes started by Docker have their own lifecycle.
 *
 * @param string[] $arguments Executable followed by literal, unquoted arguments.
 * @param array $descriptors The proc_open descriptors for stdin, stdout and stderr.
 * @param array<string,string> $environment Overrides for the inherited child environment.
 *
 * @return int The exit status, or 128 plus the terminating signal.
 */
function run_process_argv( array $arguments, array $descriptors, array $environment = [] ): int {
	if ( ! $arguments || ! isset( $arguments[0] ) || $arguments[0] === '' ) {
		throw new \InvalidArgumentException( 'A process requires an executable.' );
	}

	foreach ( $arguments as $argument ) {
		if ( ! is_string( $argument ) || strpos( $argument, "\0" ) !== false ) {
			throw new \InvalidArgumentException( 'Process arguments must be strings without null bytes.' );
		}
	}

	debug( 'Executing arguments: ' . json_encode( $arguments ) . PHP_EOL );
	// An explicit environment replaces inheritance in proc_open, so merge overrides
	// with the current environment to retain PATH and other caller settings.
	// Passing an argument array preserves boundaries without a shell command string.
	$env   = $environment ? array_replace( getenv(), $environment ) : null;
	$child = proc_open( array_values( $arguments ), $descriptors, $pipes, null, $env );

	if ( ! is_resource( $child ) ) {
		return 1;
	}

	// exit() in a signal handler bypasses finally. Keep a shutdown fallback, with a
	// reference so normal completion can clear the handle and make this a no-op.
	register_shutdown_function( static function () use ( &$child ) {
		if ( is_resource( $child ) ) {
			proc_terminate( $child );
		}
	} );

	try {
		// Poll instead of blocking in proc_close so PHP can dispatch signal handlers
		// promptly. The short sleep avoids busy-waiting while the child is running.
		do {
			$state = proc_get_status( $child );

			if ( $state === false ) {
				throw new \RuntimeException( 'Could not read the child process status.' );
			}

			if ( $state['running'] ) {
				usleep( 20000 );
			}
		} while ( $state['running'] );

		$closed = proc_close( $child );
		$child  = null;

		// Older PHP versions may return -1 from proc_close after proc_get_status has
		// collected the exit code. Prefer that saved code, then fall back to the close
		// result or the conventional status for a process terminated by a signal.
		return $state['exitcode'] >= 0 ? $state['exitcode'] : ( $closed >= 0 ? $closed : 128 + $state['termsig'] );
	} finally {
		if ( is_resource( $child ) ) {
			proc_terminate( $child );
			proc_close( $child );
			$child = null;
		}
	}
}
