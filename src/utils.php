<?php
/**
 * Utility functions for the build PHP scripts.
 */

namespace StellarWP\Slic;

require_once __DIR__ . '/process.php';
require_once __DIR__ . '/colors.php';

/**
 * Curried argument fetcher to avoid global spamming.
 *
 * @param array<string>     $map    The list of arguments to fetch from `$argv`.
 * @param array<mixed>|null $source The arguments source array, if not specified, then the global `$argv` array will
 *                                  be used.
 * @param int               $offset Start reading arguments from this position, usually `1` for the main args and `0`
 *                                  when reading an array of sub-arguments.
 *
 * @return \Closure The arg fetching closure.
 */
function args( array $map = [], array $source = null, $offset = 1 ) {
	if ( null === $source ) {
		// If the source is not specified, then read the arguments from the global CLI arguments array.
		global $argv;
		$source = $argv;
	}

	$full_map        = [];
	$parsed_variadic = false;
	foreach ( $map as $position => $key ) {
		if ( $key === '...' && $parsed_variadic ) {
			throw new \InvalidArgumentException( 'The ... key must be the last in the arguments map!' );
		}

		if ( '...' === $key ) {
			$full_map[ $key ] = array_slice( $source, $position + $offset );
			$parsed_variadic  = true;
			continue;
		}

		$full_map[ $key ] = isset( $source[ $position + $offset ] ) ? $source[ $position + $offset ] : null;
	}

	return static function ( $key, $default = null ) use ( $full_map ) {
		return null !== $full_map[ $key ] ? $full_map[ $key ] : $default;
	};
}

/**
 * Parses a provided license file and puts into the env, if any.
 *
 * @param string|null $licenses_file The path to the licenses file to parse or `null` to read licenses from the
 *                                   environment variables.
 */
function parse_license_file( $licenses_file = null ) {
	if ( null !== $licenses_file ) {
		load_env_file( $licenses_file );
	} else {
		echo PHP_EOL . "Licenses file not specified, licenses will be read from environment.";
	}
}

/**
 * Loads the contents of an env file in the environment.
 *
 * @param string $env_file The env file to read the contents of.
 */
function load_env_file( $env_file ) {
	$env_lines = read_env_file( $env_file );

	foreach ( $env_lines as $key => $value ) {
		putenv( "${key}={$value}" );
	}
}

/**
 * Reads the content of an environment file into an array.
 *
 * @param string $env_file The environment file to parse.
 *
 * @return array<string,string> A map of keys and values parsed from the env file.
 */
function read_env_file( $env_file ) {
	if ( ! file_exists( $env_file ) ) {
		echo PHP_EOL . "env file ${env_file} does not exist.";
		exit( 1 );
	}

	$lines     = array_filter( explode( "\n", file_get_contents( $env_file ) ) );
	$env_lines = [];
	foreach ( $lines as $env_line ) {
		if ( ! preg_match( '/^(?<key>[^=]+)=(?<value>.*)$/', $env_line, $m ) ) {
			continue;
		}
		$env_lines[ $m['key'] ] = $m['value'];
	}

	return $env_lines;
}

/**
 * Parses a string list into an array.
 *
 * @param array|string $list The list to parse.
 * @param string       $sep  The separator to use.
 *
 * @return array The parsed list.
 */
function parse_list( $list, $sep = ',' ) {
	if ( is_string( $list ) ) {
		$list = array_filter( preg_split( '/\\s*' . preg_quote( $sep ) . '\\s*/', $list ) );
	}

	return $list;
}

/**
 * Like `array_rand`, but returns the actual array key, not the index.
 *
 * @param array $array   The array to get the random keys for.
 * @param int   $num_req The required number of keys.
 *
 * @return array A set of random keys from the array.
 */
function array_rand_keys( array $array, $num_req = 1 ) {
	$picks = array_rand( $array, $num_req );

	return array_keys( array_intersect( array_flip( $array ), $picks ) );
}

/**
 * Returns the relative path of a file, from a root.
 *
 * @param string $root The root file to build the relative path from.
 * @param string $file The file, or directory, to return the relative path for.
 *
 * @return string The file path relative to the root directory.
 */
function relative_path( $root, $file ) {
	$root          = rtrim( $root, '\\/' );
	$relative_path = str_replace( $root, '', $file );

	return ltrim( $relative_path, '\\/' );
}

/**
 * Returns the user UID reading it from the environment, or from the output of a command if not set.
 *
 * @return string The current user ID.
 */
function uid() {
	$cache_file = cache( '/uid.txt' );

	if ( is_readable( $cache_file ) ) {
		return file_get_contents( cache( '/uid.txt' ) );
	}

	$uid = getenv( 'UID' );

	if ( false === $uid && in_array( os(), [ 'Linux', 'macOS' ] ) ) {
		$uid = check_status_or_exit( process( 'id -u' ) )( 'string_output' );
	}

	@file_put_contents( $cache_file, $uid ?: 0 );

	return false !== $uid ? $uid : 0;
}

/**
 * Returns the user GID reading it from the environment, or from the output of a command if not set.
 *
 * @return string The current user group ID.
 */
function gid() {
	$cache_file = cache( '/gid.txt' );

	if ( is_readable( $cache_file ) ) {
		return file_get_contents( cache( '/gid.txt' ) );
	}

	$gid = getenv( 'GID' );

	if ( false === $gid && in_array( os(), [ 'Linux', 'macOS' ] ) ) {
		$gid = check_status_or_exit( process( 'id -g' ) )( 'string_output' );
	}

	if ( false === $gid ) {
		$gid = 0;
		putenv( 'GID=0' );
	}

	@file_put_contents( $cache_file, $gid ?: 0 );

	return false !== $gid ? $gid : 0;
}

/**
 * Sets up the user id and group in the environment.
 *
 * On OSes that will handle user ID and group ID mapping at the Docker daemon level, macOS and Windows, the
 * `SLIC_UID` and `SLIC_GID` env variables will be set to empty strings.
 * This, in turn, will fill the `user` parameter of the stack services to `user: ":"` that will prompt docker-compose
 * to not set the user at all, the wanted behavior on such OSes.
 *
 * @param bool $reset Whether to re-fetch and reset the user id and group or not.
 */
function setup_id( $reset = false ) {
	if (
		false === $reset
		&& false !== getenv( 'SLIC_UID' )
		&& false !== getenv( 'SLIC_GID' )
	) {
		return;
	}

	putenv( 'DOCKER_RUN_UNAME=' . get_current_user() );
	putenv( 'SLIC_UID=' . uid() );
	putenv( 'SLIC_GID=' . gid() );

	putenv( 'DOCKER_RUN_SSH_AUTH_SOCK=' . ssh_auth_sock() );
}

/**
 * Returns the host machine IP address as reachable from the containers.
 *
 * The way the host machine IP address is fetched will vary depending on the Operating System the function runs on.
 * If the `SLIC_HOST` environment variable is set, then that will be used without any further check.
 *
 * @param string $os The operating system to get the host machine IP address for.
 *
 * @return string The host machine IP address or host name (e.g. `host.docker.internal` on macOS or Windows), or
 *                an empty string to indicate the host machine IP address could not be obtained.
 */
function host_ip( $os = 'Linux' ) {
	if ( $env_set_host = getenv( 'SLIC_HOST' ) ) {
		return $env_set_host;
	}

	if ( $os === 'Linux' ) {
		// Depending on the distribution being used either one, or both, these commands might yield a result.
		$commands = [
			"ip route | grep docker0 | cut -f9 -d' '",
			"/sbin/ip route|awk '/default/ { print  \$3}'",
		];
		$host_ip  = false;

		foreach ( $commands as $command ) {
			exec( $command, $output, $status );
			$host_ip = $status === 0 && isset( $output[0] ) ? trim( $output[0] ) : false;
		}

		if ( false === $host_ip ) {
			echo magenta( "Cannot get the host machine IP address." . PHP_EOL );
			exit( 1 );
		}
	} else {
		$host_ip = 'host.docker.internal';
	}

	return $host_ip;
}

/**
 * Returns whether the current running context is a Continuous Integration one or not.
 *
 * @return bool Whether the current running context is a Continuous Integration one or not.
 */
function is_ci() {
	$env_vars = [
		'CI',
		'TRAVIS_CI',
		'CONTINUOUS_INTEGRATION',
		'GITHUB_ACTION',
	];
	foreach ( $env_vars as $key ) {
		if ( (bool) getenv( $key ) ) {
			return true;
		}
	}

	return false;
}

// Whether the current run context is a `slic` binary one or not.
function is_slic() {
	$env_vars = [
		'STELLAR_SLIC',
		'SLIC',
	];
	foreach ( $env_vars as $key ) {
		if ( (bool) getenv( $key ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the current run context.
 *
 * @return string The current run context, one of `ci`, `slic` or `default`.
 */
function run_context() {
	if ( is_slic() ) {
		return 'slic';
	}

	if ( is_ci() ) {
		return 'ci';
	}

	return 'default';
}

/**
 * Returns the path to the root directory or to a sub-path in it.
 *
 * @param string $path The path to append to the absolute path of the root directory.
 *
 * @return string The absolute path to the root directory or a to a sub-directory of it.
 */
function root( $path = '' ) {
	$root = dirname( __DIR__ );

	return empty( $path ) ? $root : $root . DIRECTORY_SEPARATOR . ltrim( $path, '\\/' );
}

/**
 * Writes a key and values map to an env format file.
 *
 * @param string               $file   The path to the env file to write or update.
 * @param array<string,string> $lines  The map of values to write to the env file.
 * @param bool                 $update Whether to update the lines in the file with the new ones, or replace them.
 */
function write_env_file( $file, array $lines = [], $update = false ) {
	$existing_lines = [];

	if ( $update && is_file( $file ) ) {
		$existing_lines = read_env_file( $file );
	}

	$new_lines = array_merge( $existing_lines, $lines );

	$data = implode( "\n", array_map( static function ( $key, $value ) {
		return "{$key}={$value}";
	}, array_keys( $new_lines ), $new_lines ) );

	// If this is the first time creating the .env.slic.run file, assume this is the first run and place the CLI version in `.build-version`.
	if ( false !== strpos( $file, '.env.slic.run' ) && ! is_file( $file ) ) {
		write_build_version();
	}

	$put = file_put_contents( $file, $data );

	if ( false === $put ) {
		echo PHP_EOL . "Could not write env file {$file}";
		exit( 1 );
	}
}

/**
 * Parses an env format file to return its values.
 *
 * @param string $file The path to the env file to parse.
 *
 * @return \Closure A closure that will take the `$key` and `$default` arguments to fetch a value read from the env
 *                  format file.
 */
function env_file( $file ) {
	$map = read_env_file( $file );

	return static function ( $key, $default ) use ( $map ) {

		return isset( $map[ $key ] ) ? $map[ $key ] : $default;
	};
}

/**
 * Prints a debug message, if CLI_VERBOSITY is not `0`.
 *
 * @param string $message The debug message to print.
 */
function debug( $message ) {
	$verbosity = getenv( 'CLI_VERBOSITY' );
	if ( empty( $verbosity ) ) {
		return;
	}

	echo magenta( "[debug] " . $message );
}

/**
 * Reads the SSH_AUTH_SOCK from environment and tries to provide guidance if not set.
 *
 * @return string The `SSH_AUTH_SOCK` environment variable variable value.
 */
function ssh_auth_sock() {
	$env_ssh_sock = getenv( 'SSH_AUTH_SOCK' );
	if ( ! empty( $env_ssh_sock ) ) {
		debug( 'SSH_AUTH_SOCK read from environment.' . PHP_EOL );

		return $env_ssh_sock;
	}

	echo colorize( "❌ <red>SSH_AUTH_SOCK environment variable is not set!</red>" . PHP_EOL );
	echo colorize( "Read why and how to debug here: <light_cyan>https://developer.github.com/v3/guides/using-ssh-agent-forwarding/</light_cyan>" . PHP_EOL );
	exit( 1 );
}

/**
 * Prompts the user for an answer to a question.
 *
 * If the default value is a 'yes' or a 'no' (-ish), then the return value will be cast to a boolean.
 *
 * @param string      $question The question to ask, including the question mark?
 * @param null|string $default  The default value for the answer.
 *
 * @return string|null The user answer or the default value if the user did not provide an answer to the question.
 */
function ask( $question, $default = null ) {
	$is_interactive = getenv( 'SLIC_INTERACTIVE' );

	$prompt = colorize( "<bold>{$question}</bold>" );

	if ( null !== $default && '' !== $default ) {
		$prompt .= " ({$default})";
	}

	// Add an empty space after the prompt to separate visual confusion.
	$prompt .= ' ';

	$is_boolean = false;
	if ( is_bool( $default ) || preg_match( '/(^yes|no)$/i', $default ) ) {
		// It's a yes or no question, cast to boolean at the end.
		$is_boolean = true;
	}

	if ( empty( $is_interactive ) ) {
		$value = $default;
	} else {
		// Using echo rather than the parameter for the readline() for prompting due to a terminal window incompatibility.
		echo $prompt;
		$value = readline();
	}

	/*
	 * If the answer is an empty line, then the user just pressed Enter: use the default value.
	 */
	if ( $default !== '' ) {
		$value = '' === trim( $value ) ? $default : $value;
	}

	if ( $is_boolean ) {
		return preg_match( '/^y/i', $value );
	}

	return '' === $value ? $default : $value;
}

/**
 * Changes a string to its UPPER_SNAKE_CASE version.
 *
 * @param string $string The string to transform.
 *
 * @return string The transformed string.
 * @since TBD
 *
 */
function upper_snake_case( $string ) {
	return strtoupper( snake_case( $string ) );
}

/**
 * Changes a string to its snake_case version.
 *
 * @param string $string The string to transform.
 *
 * @return string The transformed string.
 * @since TBD
 *
 */
function snake_case( $string ) {
	return preg_replace( '/[^\\w_]/', '_', $string ) ?: $string;
}

/**
 * Removes a directory and all its contents recursively.
 *
 * @param string $dir The path to the directory to remove.
 *
 * @return bool Whether the removal was correctly completed or not.
 */
function rrmdir( $dir ) {
	if ( empty( $dir ) || ! file_exists( $dir ) ) {
		return true;
	}

	if ( is_file( $dir ) || is_link( $dir ) ) {
		return unlink( $dir );
	}

	$files = new \RecursiveIteratorIterator (
		new \RecursiveDirectoryIterator(
			$dir,
			\RecursiveDirectoryIterator::SKIP_DOTS
		),
		\RecursiveIteratorIterator::CHILD_FIRST
	);

	/** @var \SplFileInfo $fileinfo
	 */
	foreach ( $files as $fileinfo ) {
		if ( $fileinfo->isDir() ) {
			if ( rrmdir( $fileinfo->getRealPath() ) === false ) {
				return false;
			}
		} else {
			if ( unlink( $fileinfo->getRealPath() ) === false ) {
				return false;
			}
		}
	}

	return rmdir( $dir );
}

/**
 * Like PHP `array_merge_recursive`, but duplicate leaf keys will be overridden (`array_merge`)
 * and not be duplicated.
 *
 * @param array ...$args A set of arrays to recursively merge, right to left.
 *
 * @return array The merged array.
 */
function array_merge_multi( ...$args ) {
	$a = array_shift( $args );

	foreach ( $args as $b ) {
		foreach ( $b as $key => $val ) {
			if ( is_array( $val ) && is_array( $a[ $key ] ) ) {
				$b[ $key ] = array_merge_multi( $a[ $key ], $val );
			}
		}
		$a = array_merge( $a, $b );
	}

	return $a;
}

/**
 * Downloads a file to the specified path.
 *
 * @param string $source_url  The URL to download the file from
 * @param string $dest_file   The name of the file to write downloaded contents to.
 * @param bool   $verify_host Whether to verify the source host certificate or not.
 *
 * @return string|false Either the absolute path to the destination file, or `false`on failure.
 */
function download_file( $source_url, $dest_file, $verify_host = true ) {
	debug( "Downloading file $source_url ..." . PHP_EOL );

	$file_handle = fopen( $dest_file, 'wb' );

	if ( ! is_resource( $file_handle ) ) {
		return false;
	}

	$curl_handle = curl_init();

	if ( ! is_resource( $curl_handle ) ) {
		fclose( $file_handle );

		return false;
	}

	curl_setopt( $curl_handle, CURLOPT_URL, $source_url );
	curl_setopt( $curl_handle, CURLOPT_FAILONERROR, true );
	curl_setopt( $curl_handle, CURLOPT_HEADER, 0 );
	curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $curl_handle, CURLOPT_AUTOREFERER, true );
	curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 120 );
	curl_setopt( $curl_handle, CURLOPT_FILE, $file_handle );

	if ( ! $verify_host ) {
		curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $curl_handle, CURLOPT_SSL_VERIFYPEER, 0 );
	}

	if ( ! ( curl_exec( $curl_handle ) ) ) {
		return false;
	}

	// This will fclose as well.
	curl_close( $curl_handle );

	debug( "File $source_url downloaded." . PHP_EOL );

	return $dest_file;
}

/**
 * Unzips a zip file contents into the specified destination directory.
 *
 * @param string $source_file The path to the zip file to unzip.
 * @param string $dest_dir    The path to the directory to unzip the file contents into; if not present, it will
 *                            be created.
 *
 * @return string|false The path to the directory containing the extracted files, or `false` on failure.
 */
function unzip_file( $source_file, $dest_dir ) {
	debug( "Unzipping file $source_file to $dest_dir ..." . PHP_EOL );

	$zip      = new \ZipArchive;
	$basename = basename( $source_file );
	$tmp_dir  = cache( '/temp_zip_dir' );

	if ( ! (
		$zip->open( $source_file )
		&& $zip->extractTo( $tmp_dir )
		&& ( is_dir( $dest_dir ) && rrmdir( $dest_dir ) )
		&& rename( $tmp_dir . '/wordpress', $dest_dir )
		&& rrmdir( $tmp_dir )
		&& $zip->close()
	) ) {
		return false;
	}

	debug( "Unzipped $source_file to $dest_dir." . PHP_EOL );

	return $dest_dir;
}

/**
 * Returns the path to a directory, creating it if required.
 *
 * @return string The absolute path to the directory.
 */
function ensure_dir( $dir ) {
	if ( ! is_dir( $dir ) && mkdir( $dir, 0755, true ) && ! is_dir( $dir ) && realpath( $dir ) ) {
		echo magenta( "Cannot create the {$dir} directory." );
		exit( 1 );
	}

	return realpath( $dir );
}
