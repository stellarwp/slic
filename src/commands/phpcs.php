<?php
/**
 * Handles a request to run a PHP Code Sniffer (phpcs) command using the stack `php` service.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 * @var string   $cli_name The current name of the main CLI command, e.g. `slic`.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Runs PHP_CodeSniffer against the current use target.

		This command requires a use target set using the <light_cyan>use</light_cyan> command.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [...<commands>]</yellow>
	HELP;

	echo colorize( $help );
	return;
}

$using = slic_target_or_fail();
echo light_cyan( "Using {$using}" . PHP_EOL );

ensure_service_running( 'slic' );

setup_id();
$phpcs_args = $args( '...' );
$status = slic_realtime()(
	array_merge(
		[
			'exec',
			'--user',
			sprintf( '"%s:%s"', getenv( 'SLIC_UID' ), getenv( 'SLIC_GID' ) ),
			'--workdir',
			escapeshellarg( get_project_container_path() ),
			'slic',
			'vendor/bin/phpcs',
		],
		$phpcs_args
	)
);

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );
