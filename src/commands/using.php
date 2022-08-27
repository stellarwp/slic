<?php
/**
 * Handles the `using` command.
 *
 * @var bool     $is_help Whether we're handling an `help` request on this command or not.
 * @var \Closure $args    The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Returns the current 'use' target.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

$using = slic_target();
$target_path = slic_plugins_dir( $using );
if ( empty( $using ) ) {
	echo magenta( "Currently not using any target, commands requiring a target will fail." . PHP_EOL );
	return;
}

echo light_cyan( "Using {$using}" . PHP_EOL );

if ( slic_plugins_dir() !== root( '_plugins' ) ) {
	echo light_cyan( PHP_EOL . "Full target path: " ) . $target_path;
}

if ( $target_path === getcwd() ) {
	echo light_cyan( PHP_EOL . "The directory you are in is the current use target." );
} else {
	echo yellow( PHP_EOL . "The directory you are in is not the current use target." );
}
