<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Upgrades {$cli_name} to the latest version.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

$today = date( 'Y-m-d' );
chdir( SLIC_ROOT_DIR );
$status = passthru( 'git checkout main && git pull' );

if ( ! $status ) {
	unlink( SLIC_ROOT_DIR . '/.remote-version' );

	$status = passthru( 'php slic update' );
}

exit( $status );
