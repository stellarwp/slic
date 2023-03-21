<?php
// Requires the function files we might need.
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
require_once __DIR__ . '/src/codeception.php';
require_once __DIR__ . '/src/commands.php';

require_once __DIR__ . '/src/classes/Callback_Stack.php';

use function StellarWP\Slic\args;
use function StellarWP\Slic\cli_header;
use function StellarWP\Slic\colorize;
use function StellarWP\Slic\maybe_prompt_for_repo_update;
use function StellarWP\Slic\maybe_prompt_for_stack_update;
use function StellarWP\Slic\root;
use function StellarWP\Slic\setup_slic_env;

// Set up the argument parsing function.
$args = args( [
	'subcommand',
	'...',
] );

$cli_name = 'slic';
const CLI_VERSION = '1.2.3';

// If the run-time option `-q`, for "quiet", is specified, then do not print the header.
if ( in_array( '-q', $argv, true ) || ( in_array( 'exec', $argv, true ) && ! in_array( 'help', $argv, true ) ) ) {
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
  <light_cyan>start</light_cyan>          Starts containers in the stack.
  <light_cyan>stop</light_cyan>           Stops containers in the stack.
  <light_cyan>use</light_cyan>            Sets the plugin to use in the tests.
  <light_cyan>using</light_cyan>          Returns the current <light_cyan>use</light_cyan> target.
  <light_cyan>wp</light_cyan>             Runs a wp-cli command or opens a `wp-cli shell` in the stack.
  <light_cyan>xdebug</light_cyan>         Activates and deactivates XDebug in the stack, returns the current XDebug status or sets its values.

Type <light_cyan>{$cli_name} <command> help</light_cyan> for info about each command.
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
HELP;

$help_message          = colorize( $help_message_template );
$help_advanced_message = colorize( $help_advanced_message_template );

$is_help = args( [ 'help' ], $args( '...' ), 0 )( 'help', false ) === 'help';

$run_settings_file = root( '/.env.slic.run' );

$original_subcommand = $args( 'subcommand' );
$subcommand          = $args( 'subcommand', 'help' );

$cli_name = basename( $argv[0] );

if ( 'help' !== $subcommand ) {
	maybe_prompt_for_repo_update();
} else {
	$help_subcommand = $args( '...' );

	if ( $help_subcommand ) {
		$subcommand = $help_subcommand[0];
		$is_help    = true;
	}
}

if ( ! in_array( $subcommand, [ 'help', 'update'] ) ) {
	maybe_prompt_for_stack_update();
}

// A map from the user-facing alias to the command that will be actually called.
$aliases = [
	'wp' => 'cli',
];

switch ( $subcommand ) {
	default:
	case 'help':
		echo $help_message . PHP_EOL;
		if ( $original_subcommand ) {
			echo PHP_EOL . $help_advanced_message;
		} else {
			echo colorize( PHP_EOL . "There are a lot more commands. Use <light_cyan>slic help</light_cyan> to see them all!" . PHP_EOL );
		}
		maybe_prompt_for_repo_update();
		maybe_prompt_for_stack_update();
		break;
	case 'cc':
	case 'restart':
	case 'run':
	case 'serve':
	case 'shell':
	case 'site-cli':
	case 'ssh':
	case 'up':
        // ensure_wordpress_files();
        // ensure_wordpress_configured();
		// Do not break, let the command be loaded then.
	case 'airplane-mode':
	case 'cache':
	case 'cli':
	case 'wp': // Alias of the `cli` command.
        // ensure_wordpress_installed();
		// Do not break, let the command be loaded then.
	case 'build-prompt':
	case 'build-stack':
	case 'build-subdir':
	case 'composer':
	case 'composer-cache':
	case 'config':
	case 'debug':
	case 'down':
	case 'exec':
	case 'here':
	case 'host-ip':
	case 'info':
	case 'init':
	case 'interactive':
	case 'logs':
	case 'mysql':
	case 'npm':
	case 'npm_lts':
	case 'phpcbf':
	case 'phpcs':
	case 'ps':
	case 'php-version':
	case 'reset':
	case 'start':
	case 'stop':
	case 'target':
	case 'update':
	case 'upgrade':
	case 'use':
	case 'using':
	case 'xdebug':
        if ( isset( $aliases[ $subcommand ] ) ) {
            $subcommand = $aliases[ $subcommand ];
        }
		include_once __DIR__ . '/src/commands/' . $subcommand . '.php';
		break;
}

// Add a break line at the end of each command to avoid dirty terminal issues.
echo PHP_EOL;

