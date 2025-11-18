<?php
// Requires the function files we might need.
require_once __DIR__ . '/includes/polyfills.php';
require_once __DIR__ . '/src/classes/Cache.php';
require_once __DIR__ . '/src/cache.php';
require_once __DIR__ . '/src/utils.php';
require_once __DIR__ . '/src/scaffold.php';
require_once __DIR__ . '/src/slic.php';
require_once __DIR__ . '/src/docker.php';
require_once __DIR__ . '/src/notify.php';
require_once __DIR__ . '/src/plugins.php';
require_once __DIR__ . '/src/themes.php';
require_once __DIR__ . '/src/scripts.php';
require_once __DIR__ . '/src/shell.php';
require_once __DIR__ . '/src/wordpress.php';
require_once __DIR__ . '/src/services.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/project.php';
require_once __DIR__ . '/src/env.php';
require_once __DIR__ . '/src/commands.php';

use StellarWP\Slic\Cache; use function StellarWP\Slic\args;
use function StellarWP\Slic\cli_header;
use function StellarWP\Slic\colorize;
use function StellarWP\Slic\maybe_prompt_for_repo_update;
use function StellarWP\Slic\maybe_prompt_for_stack_update;
use function StellarWP\Slic\root;
use function StellarWP\Slic\setup_slic_env;

$cli_name = 'slic';
const CLI_VERSION = '3.0.0';

/*
 * Parse global flags BEFORE argument parsing to avoid them being treated as commands.
 *
 * This two-pass loop allows global flags like --stack and -q to appear anywhere in the command line,
 * providing flexibility in command invocation (e.g., both "slic --stack=/path shell" and "slic shell --stack=/path" work).
 *
 * Global flags are removed from $argv before the args() function processes the remaining arguments.
 */
global $SLIC_STACK_OVERRIDE;
$SLIC_STACK_OVERRIDE = null;

// Track indices to remove after iteration
$indices_to_remove = [];
$is_quiet = false;
$stack_flag_count = 0; // Track duplicate --stack flags

foreach ( $argv as $index => $arg ) {
	// Handle --stack=<path> syntax
	if ( strpos( $arg, '--stack=' ) === 0 ) {
		$stack_flag_count++;
		// Extract the path value after '--stack=' (8 characters: length of '--stack=')
		$SLIC_STACK_OVERRIDE = substr( $arg, 8 );
		if ( empty( $SLIC_STACK_OVERRIDE ) ) {
			echo colorize( "<magenta>Error: --stack requires a path argument</magenta>" . PHP_EOL );
			echo colorize( "Usage: slic --stack=<path> <command> or slic --stack <path> <command>" . PHP_EOL );
			exit( 1 );
		}
		$indices_to_remove[] = $index;
	}
	// Handle --stack <path> syntax (two separate arguments)
	elseif ( $arg === '--stack' ) {
		$stack_flag_count++;
		/*
		 * Check if the next argument exists and is not a flag.
		 * We check for '--' prefix to identify flags, allowing paths that start with a single hyphen
		 * (e.g., "-mypath" is valid, but "--help" is a flag).
		 */
		if ( isset( $argv[ $index + 1 ] ) && strpos( $argv[ $index + 1 ], '--' ) !== 0 ) {
			$SLIC_STACK_OVERRIDE = $argv[ $index + 1 ];
			// Mark both --stack and the path for removal
			$indices_to_remove[] = $index;
			$indices_to_remove[] = $index + 1;
		} else {
			// --stack flag present but no valid path provided
			echo colorize( "<magenta>Error: --stack requires a path argument</magenta>" . PHP_EOL );
			echo colorize( "Usage: slic --stack=<path> <command> or slic --stack <path> <command>" . PHP_EOL );
			exit( 1 );
		}
	}
	// Handle -q (quiet) flag
	elseif ( $arg === '-q' ) {
		$is_quiet = true;
		$indices_to_remove[] = $index;
	}
}

// Warn if duplicate --stack flags were provided
if ( $stack_flag_count > 1 ) {
	echo colorize( "<yellow>Warning: Multiple --stack flags detected. Using the last one: {$SLIC_STACK_OVERRIDE}</yellow>" . PHP_EOL );
}

// Validate the stack path exists if --stack was provided
if ( null !== $SLIC_STACK_OVERRIDE ) {
	// Store the original path for error messages
	$original_stack_path = $SLIC_STACK_OVERRIDE;

	// Check if this is a stack ID with @ format (worktree)
	$is_stack_id = strpos( $SLIC_STACK_OVERRIDE, '@' ) !== false;

	if ( $is_stack_id ) {
		// Stack ID format: /base/path@worktree-dir
		// Extract the actual directory path for validation
		$parts = explode( '@', $SLIC_STACK_OVERRIDE, 2 );
		if ( count( $parts ) === 2 ) {
			$base_path = $parts[0];
			$worktree_dir = $parts[1];

			// Validate that both components are non-empty
			if ( empty( $base_path ) || empty( $worktree_dir ) ) {
				echo colorize( "<magenta>Error: Invalid stack ID format - both base path and worktree directory are required</magenta>" . PHP_EOL );
				echo colorize( "Stack ID provided: {$original_stack_path}" . PHP_EOL );
				echo colorize( "Expected format: /base/path@worktree-directory" . PHP_EOL );
				echo colorize( "Examples:" . PHP_EOL );
				echo colorize( "  /Users/Alice/project@feature-branch" . PHP_EOL );
				echo colorize( "  ~/work/tec@bugfix-123" . PHP_EOL );
				exit( 1 );
			}

			// The actual worktree directory is: base_path/worktree_dir
			$worktree_full_path = $base_path . '/' . $worktree_dir;

			// Validate the worktree directory exists
			$worktree_real = realpath( $worktree_full_path );
			if ( false === $worktree_real || ! file_exists( $worktree_real ) ) {
				echo colorize( "<magenta>Error: The worktree directory specified with --stack does not exist</magenta>" . PHP_EOL );
				echo colorize( "Stack ID: {$original_stack_path}" . PHP_EOL );
				echo colorize( "Expected path: {$worktree_full_path}" . PHP_EOL );
				exit( 1 );
			}
		} else {
			echo colorize( "<magenta>Error: Invalid stack ID format</magenta>" . PHP_EOL );
			echo colorize( "Stack ID provided: {$original_stack_path}" . PHP_EOL );
			echo colorize( "Expected format: /base/path@worktree-dir" . PHP_EOL );
			exit( 1 );
		}
	} else {
		// Regular filesystem path - validate as before
		// Expand relative paths and handle tilde (~) expansion
		if ( '~' === $SLIC_STACK_OVERRIDE[0] ) {
			$home = getenv( 'HOME' );
			if ( false !== $home ) {
				$SLIC_STACK_OVERRIDE = $home . substr( $SLIC_STACK_OVERRIDE, 1 );
			}
		}

		// Convert to absolute path if relative
		if ( '/' !== $SLIC_STACK_OVERRIDE[0] ) {
			$SLIC_STACK_OVERRIDE = getcwd() . '/' . $SLIC_STACK_OVERRIDE;
		}

		// Normalize the path (remove .., ., etc.)
		$SLIC_STACK_OVERRIDE = realpath( $SLIC_STACK_OVERRIDE );

		// Check if the path exists
		if ( false === $SLIC_STACK_OVERRIDE || ! file_exists( $SLIC_STACK_OVERRIDE ) ) {
			echo colorize( "<magenta>Error: The path specified with --stack does not exist</magenta>" . PHP_EOL );
			echo colorize( "Path provided: {$original_stack_path}" . PHP_EOL );
			exit( 1 );
		}
	}
}

// Remove the global flags from argv
foreach ( $indices_to_remove as $index ) {
	unset( $argv[ $index ] );
}
if ( ! empty( $indices_to_remove ) ) {
	$argv = array_values( $argv );
	$argc = count( $argv );
}

// Set up the argument parsing function.
$args = args( [
	'subcommand',
	'...',
] );

// If the run-time option `-q`, for "quiet", is specified, then do not print the header.
if ( $is_quiet || ( in_array( 'exec', $argv, true ) && ! in_array( 'help', $argv, true ) ) ) {
	// Remove the `-q` flag from the global array of arguments to leave the rest of the commands unchanged.
	unset( $argv[ array_search( '-q', $argv ) ] );
	$argv = array_values( $argv );
	$argc = count( $argv );
	// Define a const commands will be able to check for quietness.
	define( 'SLIC_QUIET', true );
} else {
	echo cli_header( $cli_name, $argc < 2 || $argv[1] === 'help', $cli_header_extra ?? null );
}

define( 'SLIC_ROOT_DIR', __DIR__ );

setup_slic_env( SLIC_ROOT_DIR );

// Start the cache.
global $slic_cache;
$slic_cache = new Cache();


$help_message_template = <<< HELP
» Learn how to use <light_cyan>slic</light_cyan> at <yellow>https://github.com/stellarwp/slic</yellow>

Available commands:
-------------------
<yellow>Popular:</yellow>
  <light_cyan>composer</light_cyan>       Runs a Composer command in the stack.
  <light_cyan>help</light_cyan>           Displays this help message.
  <light_cyan>here</light_cyan>           Sets the current plugins directory to be the one used by slic.
  <light_cyan>info</light_cyan>           Displays information about the slic tool.
  <light_cyan>logs</light_cyan>           Displays the current stack logs.
  <light_cyan>npm</light_cyan>            Runs an npm command in the stack using the version of node specified by .nvmrc.
  <light_cyan>phpcbf</light_cyan>         Runs PHP Code Beautifier and Fixer within the current <light_cyan>use</light_cyan> target.
  <light_cyan>phpcs</light_cyan>          Runs PHP_CodeSniffer within the current <light_cyan>use</light_cyan> target.
  <light_cyan>restart</light_cyan>        Restarts containers in the stack.
  <light_cyan>run</light_cyan>            Runs a Codeception test in the stack, the equivalent to <light_cyan>'codecept run ...'</light_cyan>.
  <light_cyan>shell</light_cyan>          Opens a shell in the `slic` container.
  <light_cyan>stack</light_cyan>          Manages multiple slic stacks (list, stop, info).
  <light_cyan>start</light_cyan>          Starts containers in the stack.
  <light_cyan>stop</light_cyan>           Stops containers in the stack.
  <light_cyan>use</light_cyan>            Sets the plugin to use in the tests.
  <light_cyan>using</light_cyan>          Returns the current <light_cyan>use</light_cyan> target.
  <light_cyan>wp</light_cyan>             Runs a wp-cli command or opens a `wp-cli shell` in the stack.
  <light_cyan>xdebug</light_cyan>         Activates and deactivates XDebug in the stack, returns the current XDebug status or sets its values.
  <light_cyan>playwright</light_cyan>     Runs Playwright commands in the stack.

Type <light_cyan>{$cli_name} <command> help</light_cyan> for info about each command.
Global option: <yellow>--stack=<path></yellow> to target a specific stack.
HELP;

$help_advanced_message_template = <<< HELP
<yellow>Advanced:</yellow>
  <light_cyan>airplane-mode</light_cyan>  Activates or deactivates the airplane-mode plugin.
  <light_cyan>build-prompt</light_cyan>   Activates or deactivates whether or not composer/npm build prompts should be provided.
  <light_cyan>build-stack</light_cyan>    Builds the stack containers that require it, or builds a specific service image.
  <light_cyan>build-subdir</light_cyan>   Activates or deactivates whether or not composer/npm build should apply to sub-directories.
  <light_cyan>cache</light_cyan>          Activates and deactivates object cache support, returns the current object cache status.
  <light_cyan>cc</light_cyan>             Runs a Codeception command in the stack, the equivalent of <light_cyan>'codecept ...'</light_cyan>.
  <light_cyan>cli</light_cyan>            Runs a wp-cli command or opens a `wp-cli shell` in the stack; alias of `wp`.
  <light_cyan>composer-cache</light_cyan> Sets or shows the composer cache directory.
  <light_cyan>config</light_cyan>         Prints the stack configuration as interpolated from the environment.
  <light_cyan>debug</light_cyan>          Activates or deactivates {$cli_name} debug output or returns the current debug status.
  <light_cyan>down</light_cyan>           Tears down the stack; alias of `stop`.
  <light_cyan>dc</light_cyan>             Runs a docker compose command in the stack.
  <light_cyan>exec</light_cyan>           Runs a bash command in the stack.
  <light_cyan>group</light_cyan>          Create or remove group of targets for the current plugins directory.
  <light_cyan>host-ip</light_cyan>        Returns the IP Address of the host machine from the container perspective.
  <light_cyan>init</light_cyan>           Initializes a plugin for use in slic.
  <light_cyan>interactive</light_cyan>    Activates or deactivates interactivity of {$cli_name} commands.
  <light_cyan>mysql</light_cyan>          Opens a mysql shell in the database service.
  <light_cyan>ps</light_cyan>             Lists the containers part of {$cli_name} stack.
  <light_cyan>php-version</light_cyan>    Sets or shows the PHP version of the stack.
  <light_cyan>reset</light_cyan>          Resets {$cli_name} to the initial state as configured by the env files.
  <light_cyan>site-cli</light_cyan>       Waits for WordPress to be correctly set up to run a wp-cli command in the stack.
  <light_cyan>ssh</light_cyan>            Opens a shell in the `slic` container; alias of `shell`.
  <light_cyan>target</light_cyan>         Runs a set of commands on a set of targets.
  <light_cyan>up</light_cyan>             Starts containers in the stack; alias of `start`.
  <light_cyan>update</light_cyan>         Updates the tool and the images used in its services.
  <light_cyan>upgrade</light_cyan>        Upgrades the {$cli_name} repo.
  <light_cyan>update-dump</light_cyan>    Updates a SQL dump file. Optionally, installs a specific WordPress version..
HELP;

$help_message          = colorize( $help_message_template );
$help_advanced_message = colorize( $help_advanced_message_template );

$is_help = args( [ 'help' ], $args( '...' ), 0 )( 'help', false ) === 'help';

$original_subcommand = $args( 'subcommand' );
$subcommand          = $args( 'subcommand', 'help' );

// Both these variables will be used by commands.
$run_settings_file = root( '/.env.slic.run' );
$cli_name          = basename( $argv[0] );

if ( 'help' !== $subcommand ) {
	maybe_prompt_for_repo_update();
} else {
	$help_subcommand = $args( '...' );

	if ( $help_subcommand ) {
		$subcommand = $help_subcommand[0];
		$is_help    = true;
	}
}

if ( ! in_array( $subcommand, [ 'help', 'update' ] ) ) {
	maybe_prompt_for_stack_update();
}

if ( empty( $subcommand ) || $subcommand === 'help' ) {
	echo $help_message . PHP_EOL;
	if ( $original_subcommand ) {
		echo PHP_EOL . $help_advanced_message;
	} else {
		echo colorize( PHP_EOL . "There are a lot more commands. Use <light_cyan>slic help</light_cyan> to see them all!" . PHP_EOL );
	}
	maybe_prompt_for_repo_update();
	maybe_prompt_for_stack_update();
	echo PHP_EOL;
	exit( 0 );
}

/*
 * Resolve command aliases.
 * A map from the user-facing alias to the command that will be actually called.
 */
$aliases = [
	'wp' => 'cli',
];
if ( isset( $aliases[ $subcommand ] ) ) {
	$subcommand = $aliases[ $subcommand ];
}

$subcommand_file = __DIR__ . '/src/commands/' . $subcommand . '.php';
if ( file_exists( $subcommand_file ) ) {
	include_once $subcommand_file;
} else {
	echo colorize( "<magenta>Unknown command: {$subcommand}</magenta>" . PHP_EOL . PHP_EOL );
	echo $help_message . PHP_EOL;
	maybe_prompt_for_repo_update();
	maybe_prompt_for_stack_update();
	echo PHP_EOL;
	exit( 1 );
}

// Add a break line at the end of each command to avoid dirty terminal issues.
echo PHP_EOL;
