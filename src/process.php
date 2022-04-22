<?php
/**
 * Process related files and functions.
 */

namespace TEC\Tric;

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
 * Realtime processes are done without forking, have no need of prefixes, and support interactivity.
 *
 * @param string $command The command to run.
 * @param string|null $prefix The prefix to place before all output.
 *
 * @return int The process exit status, `0` means ok.
 */
function process_realtime( $command ) {
	debug( "Executing command: {$command}" );

	echo PHP_EOL;

	setup_terminal();

	$clean_command = escapeshellcmd( $command );

	passthru( $clean_command, $status );

	return (int) $status;
}

/**
 * Runs a process passively, displaying its output.
 *
 * Passive processes are ones that only need to dump their output.
 *
 * @param string $command The command to run.
 * @param string|null $prefix The prefix to place before all output.
 *
 * @return int The process exit status, `0` means ok.
 */
function process_passive( $command, $prefix = null ) {
	debug( "Executing command: {$command}" );

	echo PHP_EOL;

	setup_terminal();

	$clean_command = escapeshellcmd( $command );

	$pipes_spec = [
		[ 'pipe', 'r' ], // STDIN.
		[ 'pipe', 'w' ], // STDOUT.
		[ 'pipe', 'w' ], // STDERR.
	];
	// Inherit `cwd` and environment from the context.
	$proc_handle = proc_open( $clean_command, $pipes_spec, $pipes );

	if ( ! is_resource( $proc_handle ) ) {
		magenta( "Could not create realtime process for command '{$clean_command}'");
		exit( 1 );
	}

	$status = proc_get_status( $proc_handle );

	while ( true === $status['running'] ) {
		if ( false === $status ) {
			magenta("Could not get process status for command '{$clean_command}'");
			exit( 1 );
		}

		foreach ( [ 1, 2 ] as $pipe ) {
			$read        = [ $pipes[ $pipe ] ];
			$write       = null;
			$except      = null;
			$num_streams = stream_select( $read, $write, $except, 0, 60000 );

			if ( $num_streams > 0 ) {
				do {
					$raw_line = stream_get_line( $pipes[ $pipe ], 8092, "\n" );

					if ( $prefix ) {
						$line = preg_replace( '/^/m', "[{$prefix}] ", $raw_line );
					} else {
						$line = $raw_line;
					}

					echo $line . PHP_EOL;
				} while ( strlen( $raw_line ) > 0 );
			}
		}

		$status = proc_get_status( $proc_handle );
	}

	$closed = array_sum( [
			fclose( $pipes[0] ),
			fclose( $pipes[1] ),
			fclose( $pipes[2] ),
		]
	);

	/*
	 * We do this just for safety and to kill ancillary processes the process might have spawned.
	 * An error here might be a consequence of the process having exited already, so it's fine.
	 * The `$status` information is the correct source of truth about the process health.
	 */
	proc_close( $proc_handle );

	if ( $closed !== 3 ) {
		magenta( "Failed to close the process pipes for command '{$clean_command}'" );
		exit( 1 );
	}

	return (int) $status['exitcode'];
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

/**
 * Executes a process with forked children in parallel.
 *
 * Note: the function will internally check to see if the host OS does support the Process Control extension.
 * If not the processes will be executed serially and the first failure will stop the serial execution of the
 * processes.
 *
 * @param array $items Values with which to loop over to indicate process distinction.
 * @param \Closure $command_process The closure to execute as a distinct process.
 *
 * @return int The combined process status value of all child processes.
 */
function parallel_process( $pool ) {
	$process_children = [];
	// Start on the upper end of hte subnets to try and avoid overlapping pool issues.
	$subnet_pool      = array_rand( array_flip( range( 220, 255 ) ), count( $pool ) );
	$pool_with_subnet = array_combine( $subnet_pool, $pool );

	/*
	 * Disable parallel processing temporarily to avoid some overlapping pool issues.
	 */
	if ( function_exists( 'pcntl_fork' ) ) {
		// If we're on a OS that does support process control, then fork.
		foreach ( $pool_with_subnet as $subnet => $item ) {
			$pid = pcntl_fork();
			if ( $pid === - 1 ) {
				echo magenta( "Unable to fork processes.\n" );
				exit( 1 );
			}

			if ( 0 === $pid ) {
				$item['process']( $item['target'], $subnet );
			} else {
				$process_children[] = $pid;
			}
		}

		return get_status_of_forked_children( $process_children );
	}

	/*
	 * If Process Control functions are not available or are disabled, then we execute the commands serially.
	 * Nothing "parallel" here.
	 */
	foreach ( $pool as $item ) {
		$status = $item['process']( $item['target'] );
		if ( $status !== 0 ) {
			// At the first failure, bail.
			return $status;
		}
	}

	// All fine if we're here.
	return 0;
}

/**
 * Loops over children and returns their success/failure.
 *
 * @param array $children Array of PIDs for forked processes.
 *
 * @return int The process status value.
 */
function get_status_of_forked_children( array $children = [] ) {
	if ( ! function_exists( 'pcntl_waitpid' ) ) {
		/*
		 * If we're calling this function on a host that does not support Process Control functions
		 * then the developer made a feature detection error and that should be promptly reported.
		 */
		throw new \RuntimeException( __FUNCTION__ . ' should not be called when Process Control ' .
		                             'functions are not available.' );
	}

	$status = 0;

	// Wait of children to finish.
	foreach ( $children as $pid ) {
		$child_status = 0;

		pcntl_waitpid( $pid, $child_status );

		if ( $child_status > $status ) {
			$status = $child_status;
		}
	}

	return $status;
}

/**
 * A function that will `exit` or `return` the status depending on the current OS supprt of the Process Control
 * extension.
 *
 * The function will `exit` on OSes that do support the Process Control extension.
 * The function should be used anywhere an `exit` would be used with intention to end the execution of a child
 * process in the context of a `pcntl_fork` call.
 *
 * @param int|string $status The exit code or message.
 *
 * @return int|string The exit code or message when the host OS does not support the Process Control extension.
 */
function pcntl_exit( $status ) {
	/*
	 * Temporarily commenting this out to avoid parallelization issues.
	if ( function_exists( 'pcntl_fork' ) ) {
		exit( $status );
	}*/

	return $status;
}
