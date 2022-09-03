<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Updates {$cli_name} and the images used in its services.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

$confirm = ask( 'Would you like to stop slic before updating?', 'yes' );

if ( $confirm ) {
	command_stop();
}

rebuild_stack();
update_stack_images();
