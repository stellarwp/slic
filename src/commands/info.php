<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Displays information about the slic tool.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );

	return;
}

setup_id();
slic_info();
