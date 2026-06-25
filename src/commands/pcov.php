<?php
/**
 * Handles the `pcov` command.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of slic CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Activates and deactivates PCOV in the stack, or returns the current PCOV status.

	USAGE:

		<yellow>{$cli_name} pcov [on|off|status] [--yes|-y]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} pcov</light_cyan>
		Gets the PCOV status.

		<light_cyan>{$cli_name} pcov on</light_cyan>
		Turns PCOV on in the running stack. If Xdebug is enabled, slic will prompt to disable it first.

		<light_cyan>{$cli_name} pcov on --yes</light_cyan>
		Turns PCOV on and automatically disables Xdebug if needed.

		<light_cyan>{$cli_name} pcov off</light_cyan>
		Turns PCOV off in the running stack.

		<light_cyan>{$cli_name} pcov status</light_cyan>
		Gets the PCOV status.
	HELP;

	echo colorize( $help );
	return;
}

$command   = $args( '...' );
$confirm   = in_array( '--yes', $command, true ) || in_array( '-y', $command, true );
$pcov_args = args( [ 'toggle' ], $command, 0 );

slic_handle_pcov( $pcov_args, $confirm );
