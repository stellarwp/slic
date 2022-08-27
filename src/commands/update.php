<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Updates slic and the images used in its services.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

rebuild_stack();
update_stack_images();
