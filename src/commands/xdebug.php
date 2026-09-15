<?php
/**
 * Handles the `xdebug` command.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of slic CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Activates and deactivated XDebug in the stack, returns the current XDebug status or sets its values.

		Any change to XDebug settings will require tearing down the stack with <light_cyan>down</light_cyan> and restarting it!

	USAGE:

		<yellow>{$cli_name} xdebug (on|off|status|port|host|key) [<value>] [--yes|-y]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} xdebug on</light_cyan>
		Turns xdebug on. If PCOV is enabled, slic will prompt to disable it first.

		<light_cyan>{$cli_name} xdebug on --yes</light_cyan>
		Turns xdebug on and automatically disables PCOV if needed.

		<light_cyan>{$cli_name} xdebug status</light_cyan>
		Gets the xdebug status.

		<light_cyan>{$cli_name} xdebug host 192.168.1.2</light_cyan>
		Sets the xdebug host to 192.168.1.2.

		<light_cyan>{$cli_name} xdebug port 9009</light_cyan>
		Sets the xdebug port to 9009.
	HELP;

	echo colorize( $help );
	return;
}

$command     = $args( '...' );
$confirm     = in_array( '--yes', $command, true ) || in_array( '-y', $command, true );
$xdebug_args = args( [ 'toggle', 'value' ], $command, 0 );

slic_handle_xdebug( $xdebug_args, $confirm );
