<?php
/**
 * Docker Compose adapters for literal argument arrays.
 */

namespace StellarWP\Slic;

/**
 * Build stack options from filenames without adding shell quotes.
 *
 * @return string[] Raw Compose file options.
 */
function slic_stack_argv(): array {
	$options = [];

	foreach ( slic_stack_array( true ) as $filename ) {
		$options[] = '-f';
		$options[] = $filename;
	}

	return $options;
}

/**
 * Capture a Compose command supplied as raw arguments.
 *
 * @param string[] $arguments Raw command arguments.
 * @param string[] $options Raw Compose options, including filenames without quotes.
 *
 * @return array{status: int, stdout: string} The exit status and captured stdout.
 */
function docker_compose_argv( array $arguments, array $options = [] ): array {
	return run_docker_compose_argv( $arguments, $options, false );
}

/**
 * Stream a Compose command supplied as raw arguments.
 *
 * @param string[] $arguments Raw command arguments.
 * @param string[] $options Raw Compose options, including filenames without quotes.
 *
 * @return int The exit status.
 */
function docker_compose_argv_realtime( array $arguments, array $options = [] ): int {
	return run_docker_compose_argv( $arguments, $options, true );
}

/**
 * Run Compose with its environment and output mode without changing argument contents.
 *
 * @param string[] $arguments Raw command arguments.
 * @param string[] $options Raw Compose options.
 * @param bool $realtime Whether to inherit terminal output instead of capturing stdout.
 *
 * @return array{status: int, stdout: string}|int Captured result or streaming exit status.
 */
function run_docker_compose_argv( array $arguments, array $options, bool $realtime ) {
	setup_id();
	$environment = [];
	$is_ci       = is_ci();

	// Match the legacy Compose helpers' local Linux networking and Xdebug settings.
	if ( ! $is_ci && os() === 'Linux' ) {
		$override = stack( '-linux-override' );

		if ( file_exists( $override ) ) {
			$options = array_merge( [ '-f', $override ], $options );
		}

		$host_ip = host_ip( 'Linux' );

		if ( $host_ip ) {
			$environment['XDH'] = (string) getenv( 'XDH' ) ?: (string) $host_ip;
		}
	}

	if ( $is_ci ) {
		// Retain the legacy helpers' CI behavior: both modes disable Xdebug through
		// XDE, and captured commands also pass XDEBUG_DISABLE.
		$environment['XDE'] = '0';

		if ( ! $realtime ) {
			$environment['XDEBUG_DISABLE'] = '1';
		}
	}

	$prefix     = docker_compose_binary_argv();
	$subcommand = $arguments[0] ?? '';

	if ( ! $realtime || $is_ci || ! is_interactive() ) {
		// These are Compose subcommand options, so insert them before the service
		// name. Captured and noninteractive commands must not allocate a terminal.
		$flags = [ 'exec' => [ '-T' ], 'run' => [ '-T' ], 'logs' => [ '--no-color' ] ];
		array_splice( $arguments, 1, 0, $flags[ $subcommand ] ?? [] );
	}

	$command = array_merge( $prefix, $options, $arguments );

	if ( ! $realtime ) {
		return process_argv( $command, $environment );
	}

	// Preserve the existing Compose exec terminal workaround through stdin's descriptor.
	$stdin_null = $subcommand === 'exec' && is_tty_supported();

	return process_argv_realtime( $command, $environment, $stdin_null );
}

/**
 * Read the configured Compose executable and optional prefix arguments.
 *
 * The legacy setting is a string. Split quoted words once at this boundary, without
 * shell expansion. Executable paths, docker-compose and "docker --context ... compose"
 * are supported. Shell operators require an executable wrapper script instead.
 *
 * @return string[] The executable and its prefix arguments.
 */
function docker_compose_binary_argv(): array {
	$command = trim( docker_compose_bin() );

	// An existing executable path may contain spaces without being shell-quoted.
	// Only parse words when the setting is not itself a file path.
	if ( is_file( $command ) ) {
		return [ $command ];
	}

	return docker_compose_prefix_argv( $command, DIRECTORY_SEPARATOR === '\\' );
}

/**
 * Split a configured command prefix, preserving platform-specific path separators.
 *
 * @param string $command The executable and optional quoted prefix arguments.
 * @param bool $windows Whether backslashes are Windows path separators.
 *
 * @return string[] Literal prefix arguments, with quoting removed.
 */
function docker_compose_prefix_argv( string $command, bool $windows ): array {
	// Track whether a word has started separately from its contents: quoted empty
	// arguments must survive, while whitespace between words adds no arguments.
	$arguments = [];
	$word      = '';
	$quote     = null;
	$started   = false;
	$length    = strlen( $command );

	for ( $i = 0; $i < $length; $i++ ) {
		$character = $command[ $i ];
		$next      = $command[ $i + 1 ] ?? '';
		$escapable = $quote === '"' ? '\\"$`' : "\\\"' \t";

		// Preserve Windows path separators, including UNC prefixes. On POSIX, only
		// consume the supported escapes; single-quoted contents stay literal below.
		if ( $windows ) {
			$escapable = '"';
		}

		if ( $character === '\\' && $quote !== "'" && $next !== '' && strpos( $escapable, $next ) !== false ) {
			$word .= $next;
			$started = true;
			$i++;

			continue;
		}

		if ( $quote !== null ) {
			if ( $character === $quote ) {
				$quote = null;
			} else {
				$word .= $character;
			}

			continue;
		}

		if ( $character === '"' || $character === "'" ) {
			$quote   = $character;
			$started = true;

			continue;
		}

		if ( strpos( " \t\r\n", $character ) !== false ) {
			if ( $started ) {
				$arguments[] = $word;
				$word        = '';
				$started     = false;
			}

			continue;
		}

		if ( strpos( '|&;<>()`', $character ) !== false ) {
			// No shell evaluates this prefix. Reject unquoted operators instead of
			// silently passing a pipeline or redirection to Docker as literal arguments.
			throw new \InvalidArgumentException( 'SLIC_DOCKER_COMPOSE_BIN cannot contain shell operators with the argv runner. Use an executable wrapper script.' );
		}

		$word .= $character;
		$started = true;
	}

	if ( $quote !== null ) {
		throw new \InvalidArgumentException( 'SLIC_DOCKER_COMPOSE_BIN contains an unmatched quote.' );
	}

	if ( $started ) {
		$arguments[] = $word;
	}

	if ( ! $arguments || $arguments[0] === '' ) {
		throw new \InvalidArgumentException( 'SLIC_DOCKER_COMPOSE_BIN requires an executable.' );
	}

	return $arguments;
}
