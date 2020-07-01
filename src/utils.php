<?php
/**
 * Utility functions for the build PHP scripts.
 */

namespace Tribe\Test;

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
			$full_map [ $key ] = array_slice( $source, $position + $offset );
			$parsed_variadic   = true;
			continue;
		}

		$full_map[ $key ] = isset( $source[ $position + $offset ] ) ? $source[ $position + $offset ] : null;
	}

	return static function ( $key, $default = null ) use ( $full_map ) {
		return null !== $full_map[ $key ] ? $full_map[ $key ] : $default;
	};
}

/**
 * Uses curl to fire a GET request to a URL.
 *
 * @param string $url The URL to fire the request to.
 * @param array  $query_args
 *
 * @return string  The curl response.
 */
function curl_get( $url, array $query_args = [] ) {
	$full_url = $url . ( strpos( $url, '?' ) === false ? '?' : '' ) . http_build_query( $query_args );

	$curl_handle = curl_init();
	curl_setopt( $curl_handle, CURLOPT_URL, $full_url );
	curl_setopt( $curl_handle, CURLOPT_HEADER, 0 );
	curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 10 );
	curl_setopt( $curl_handle, CURLOPT_FOLLOWLOCATION, true );

	if ( ! $result = curl_exec( $curl_handle ) ) {
		echo "\nFailed to process curl request.";
		echo "\nError: " . curl_error( $curl_handle );
		exit( 1 );
	}

	curl_close( $curl_handle );

	return $result;
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
		echo "\nLicenses file not specified, licenses will be read from environment.";
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
		echo "\nenv file ${env_file} does not exist.";
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
	$uid = getenv( 'UID' );

	if ( false === $uid && in_array( os(), [ 'Linux', 'macOS' ] ) ) {
		$uid = check_status_or_exit( process( 'id -u' ) )( 'string_output' );
	}

	return false !== $uid ? $uid : 0;
}

/**
 * Returns the user GID reading it from the environment, or from the output of a command if not set.
 *
 * @return string The current user group ID.
 */
function gid() {
	$gid = getenv( 'GID' );

	if ( false === $gid && in_array( os(), [ 'Linux', 'macOS' ] ) ) {
		$gid = check_status_or_exit( process( 'id -g' ) )( 'string_output' );
	}

	if ( false === $gid ) {
		$gid = 0;
		putenv( 'GID=0' );
	}

	return false !== $gid ? $gid : 0;
}

/**
 * Sets up the user id and group in the environment.
 *
 * On OSes that will handle user ID and group ID mapping at the Docker daemon level, macOS and Windows, the
 * `DOCKER_RUN_UID` and `DOCKER_RUN_GID` env variables will be set to empty strings.
 * This, in turn, will fill the `user` parameter of the stack services to `user: ":"` that will prompt docker-compose
 * to not set the user at all, the wanted behavior on such OSes.
 *
 * @param bool $reset Whether to re-fetch and reset the user id and group or not.
 */
function setup_id( $reset = false ) {
	if (
		false === $reset
		&& false !== getenv( 'DOCKER_RUN_UID' )
		&& false !== getenv( 'DOCKER_RUN_GID' )
	) {
		return;
	}

	$os = os();
	if ( 'Windows' === $os || 'macOS' === $os ) {
		// Leave the value empty to allow the vm user-mapping to kick in.
		putenv( 'DOCKER_RUN_UID=' );
		putenv( 'DOCKER_RUN_GID=' );
	} else {
		// On other systems explicitly set the values.
		putenv( 'DOCKER_RUN_UID=' . uid() );
		putenv( 'DOCKER_RUN_GID=' . gid() );
	}

	putenv( 'DOCKER_RUN_SSH_AUTH_SOCK=' . ssh_auth_sock() );
}

/**
 * Echoes a process output.
 *
 * @param callable $process the process to output from.
 */
function the_process_output( callable $process ) {
	echo "\n" . implode( "\n", $process( 'output' ) );
}

/**
 * Clarifies the nature of the issue.
 *
 * @return string Helpful ASCII art.
 */
function the_fatality() {
	return '
                       _..----------.._                       
                  .-=""        _       ""=-.                  
               .-"    _.--""j _\""""--._    "-.               
            .-"  .-i   \   / / \;       ""--.  "-.            
          .\'  .-"  : ( "  : :                "-.  `.          
        .\'  .\'      `.`.   \ \                  `.  `.        
       /  .\'      .---" ""--`."-./\'---.           `.  \       
      /  /      .\'                    \'-.           \  \      
     /  /      /                         `.          \  \     
    /  /      /                  ,--._   (            \  \    
   ,  /    \'-\')                  `---\'    `.           \  .   
  .  :      .\'                              "-._.-.     ;  ,  
  ;  ;     /            :;         ,-"-.    ,--.   )    :  :  
 :  :     :             ::        :_    "-. \'-\'   `,     ;  ; 
 |  |     :              \\     .--."-.    `._ _   ;     |  | 
 ;  ;     :              / "---"    "-."-.    l.`./      :  : 
:  :      ;             :              `. "-._; \         ;  ;
;  ;      ;             ;                `..___/\\        :  :
;  ;      ;             :                        \\    _  :  :
:  :     /              \'.                        ;;.__)) ;  ;
 ;  ; .-\'                 "-...______...--._      ::`--\' :  : 
 |  |  `--\'\                                "-.    \`._, |  | 
 :  :       \                                  `.   "-"  ;  ; 
  ;  ;       `.                                  \      :   \' 
  \'  :        ;                                   ;     ;  \'  
   \'  \    _  : :`.                               :    /  /   
    \  \   \`-\' ; ; ._                             ;  /  /    
     \  \   `--\'  : ; "-.                          : /  /     
      \  \        ;/     \                         ;/  /      
       \  `.              ;                        \'  /       
        `.  "-.   bug    /                          .\'        
          `.   "-..__..-"                         .\'          
            "-.                                .-"            
               "-._                        _.-"               
                   """---...______...---"""	
	';
}

/**
 * Returns the host machine IP address as reachable from the containers.
 *
 * The way the host machine IP address is fetched will vary depending on the Operating System the function runs on.
 *
 * @param string $os The operating system to get the host machine IP address for.
 *
 * @return string The host machine IP address or host name (e.g. `host.docker.internal` on macOS or Windows), or
 *                an empty string to indicate the host machine IP address could not be obtained.
 */
function host_ip( $os = 'Linux' ) {
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
			echo magenta( "Cannot get the host machine IP address.\n" );
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

// Whether the current run context is a `tric` binary one or not.
function is_tric() {
	$env_vars = [
		'TRIBE_TRIC',
		'TRIC',
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
 * @return string The current run context, one of `ci`, `tric` or `default`.
 */
function run_context() {
	if ( is_tric() ) {
		return 'tric';
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

	if ( $update && file_exists( $file ) ) {
		$existing_lines = read_env_file( $file );
	}

	$new_lines = array_merge( $existing_lines, $lines );

	$data = implode( "\n", array_map( static function ( $key, $value ) {
		return "{$key}={$value}";
	}, array_keys( $new_lines ), $new_lines ) );

	// If this is the first time creating the .env.tric.run file, assume this is the first run and place the CLI version in `.build-version`.
	if ( false !== strpos( $file, '.env.tric.run' ) && ! file_exists( $file ) ) {
		write_build_version();
	}

	$put = file_put_contents( $file, $data );

	if ( false === $put ) {
		echo "\nCould not write env file {$file}";
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

	echo colorize( "<red>SSH_AUTH_SOCK environment variable is not set!</red>\n" );
	echo colorize( "Read why and how to debug here: <light_cyan>https://developer.github.com/v3/guides/using-ssh-agent-forwarding/</light_cyan>\n" );
	exit( 1 );
}

/**
 * Prompts the user for an answer to a question.
 *
 * If the default value is a 'yes' or a 'no' (-ish), then the return value will be cast to a boolean.
 *
 * @param      string $question The question to ask, including the question mark?
 * @param null|string $default The default value for the answer.
 *
 * @return string|null The user answer or the default value if the user did not provide an answer to the question.
 */
function ask( $question, $default = null ) {
	$is_interactive = getenv( 'TRIC_INTERACTIVE' );

	$prompt = colorize( "<bold>{$question}</bold>" );

	if ( null !== $default && '' !== $default ) {
		$prompt .= " ({$default})";
	}

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

	if ( $is_boolean ) {
		return preg_match( '/^y/i', $value );
	}

	return '' === $value ? $default : $value;
}
