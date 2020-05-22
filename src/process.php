<?php
/**
 * Process related files and functions.
 */

namespace Tribe\Test;

/**
 * Runs a process and returns a closure that allows getting the status or output from it.
 *
 * @param string $command The command to run, in string format.
 *
 * @return \Closure A closure that will take will return the process status, output as an array or output as a
 *                 string using the keys 'status', 'output', 'string_output' respectively.
 */
function process( $command ) {
	debug( "Executing command: {$command}\n" );

	exec( escapeshellcmd( $command ), $output, $status );

	return static function ( $what = null ) use ( $output, $status ) {
		if ( null === $what || 'status' === $what ) {
			return (int) $status;
		}

		if ( 'string_output' === $what ) {
			return trim( implode( PHP_EOL, $output ) );
		}

		return $output;
	};
}

/**
 * Runs a process in realtime, displaying its output.
 *
 * @param string $command The command to run.
 *
 * @return int The process exit status, `0` means ok.
 */
function process_realtime( $command ) {
	debug( "Executing command: {$command}" );

	echo PHP_EOL;

	setup_terminal();

	passthru( escapeshellcmd( $command ), $status );

	return (int) $status;
}

/**
 * Unsets the PHP max execution time allowing the PHP thread to run without time limit.
 */
function remove_time_limit() {
	change_time_limit( 0 );
}

/**
 * Sets the time limit for the current PHP thread.
 *
 * This function acts as a wrapper for the `max_execution_time` setting of PHP.
 *
 * @param int $time_limit The time limit to set, setting the value to `0` will remove the execution time limit.
 */
function change_time_limit( $time_limit = 0 ) {
	ini_set( 'max_execution_time', $time_limit );
}

/**
 * Checks the status of a process, or `exit`s.
 *
 * @param callable   $process The process to check.
 * @param mixed|null $message An optional message to print after the output, if the message is not a string, then
 *                            the message data will be encoded and printed using JSON.
 *
 * @return \Closure The process handling closure.
 */
function check_status_or_exit( callable $process, $message = null ) {
	if ( 0 !== (int) $process( 'status' ) ) {
		echo "\nProcess status is not 0, output: \n\n" . implode( "\n", $process( 'output' ) );
		if ( null !== $message ) {
			echo "\nDebug:\n" .
			     ( is_string( $message ) ? $message : json_encode( $message, JSON_PRETTY_PRINT ) ) .
			     "\n";
		}
		exit ( 1 );
	}

	return $process;
}

/**
 * Checks the status of a process on a timeout, or `exit`s.
 *
 * @param callable $process The process to check.
 * @param int      $timeout The timeout, in seconds.
 *
 * @return \Closure The process handling closure.
 */
function check_status_or_wait( callable $process, $timeout = 10 ) {
	$end = time() + (int) $timeout;
	while ( time() <= $end ) {
		if ( 0 !== (int) $process( 'status' ) ) {
			echo "\nProcess status is not 0, waiting...";
			sleep( 2 );
		} else {
			return $process;
		}
	}

	return check_status_or_exit( $process );
}

/**
 * Checks the status of a process and does something if not successful.
 *
 * @param callable $process The process closure.
 * @param callable $else    The callback to call if the process status is not `0` (ok); the callback will receive the
 *                          failed process closure itself as argument.
 *
 * @return callable The process closure.
 */
function check_status_or( callable $process, callable $else = null ) {
	if ( 0 !== (int) $process( 'status' ) ) {
		$else( $process );
	}

	return $process;
}
