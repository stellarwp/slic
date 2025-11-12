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

// Determine current stack
$stack_id = slic_current_stack();

if ( null === $stack_id ) {
	echo magenta( "No active stack. Run 'slic here' to create a stack." . PHP_EOL );
	return;
}

$using = slic_target();
$target_path = slic_plugins_dir( $using );
if ( empty( $using ) ) {
	echo magenta( "Currently not using any target, commands requiring a target will fail." . PHP_EOL );
	echo light_cyan( "Stack: {$stack_id}" . PHP_EOL );
	return;
}

echo light_cyan( "Using {$using}" . PHP_EOL );
echo light_cyan( "Stack: {$stack_id}" . PHP_EOL );

// Show stack ports - ensure they're up-to-date from Docker
require_once __DIR__ . '/../stacks.php';
if ( slic_stacks_ensure_ports( $stack_id ) ) {
	$stack = slic_stacks_get( $stack_id );
	echo colorize( "WordPress URL: <yellow>http://localhost:{$stack['ports']['wp']}</yellow>" . PHP_EOL );
	echo colorize( "MySQL Port: <yellow>{$stack['ports']['mysql']}</yellow>" . PHP_EOL );
	if ( isset( $stack['ports']['redis'] ) ) {
		echo colorize( "Redis Port: <yellow>{$stack['ports']['redis']}</yellow>" . PHP_EOL );
	}
} else {
	echo colorize( "<yellow>Ports will be available after containers start.</yellow>" . PHP_EOL );
}

if ( slic_plugins_dir() !== root( '_plugins' ) ) {
	echo light_cyan( PHP_EOL . "Full target path: " ) . $target_path;
}

if ( $target_path === getcwd() ) {
	echo light_cyan( PHP_EOL . "The directory you are in is the current use target." );
} else {
	echo yellow( PHP_EOL . "The directory you are in is not the current use target." );
}
