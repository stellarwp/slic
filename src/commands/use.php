<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets the plugin to use in the tests.

	USAGE:

		<yellow>{$cli_name} {$subcommand} <target>[/<subdir>]</yellow>

	EXAMPLES:

	<light_cyan>{$cli_name} {$subcommand} the-events-calendar</light_cyan>
	Set the use target to the-events-calendar.

	<light_cyan>{$cli_name} {$subcommand} event-tickets/common</light_cyan>
	Set the use target to the common/ directory within event-tickets.
	HELP;

	echo colorize( $help );
	return;
}

$sub_args = args( [ 'target' ], $args( '...' ), 0 );
$target   = $sub_args( 'target', false );

$target = (string) ensure_valid_target( $target );

if ( ! empty( $target ) ) {
	slic_switch_target( $target );
}

echo light_cyan( "Using {$target}" . PHP_EOL );

project_apply_config( get_target_relative_path( $target ) );