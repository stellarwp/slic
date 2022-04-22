<?php
/**
 * Handles a request to run a PHP Code Sniffer (phpcs) command using the stack `php` service.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 * @var string   $cli_name The current name of the main CLI command, e.g. `tric`.
 */

namespace TEC\Tric;

if ( $is_help ) {
	echo "Runs PHP_CodeSniffer against the current use target.\n";
	echo PHP_EOL;
	echo colorize( "This command requires a use target set using the <light_cyan>use</light_cyan> command.\n" );
	echo colorize( "usage: <light_cyan>{$cli_name} phpcs [...<commands>]</light_cyan>\n" );
	return;
}

$using = tric_target_or_fail();
echo light_cyan( "Using {$using}\n" );

setup_id();
$phpcs_args = $args( '...' );
$status = tric_realtime()( array_merge( [ 'run', '--rm', 'php', 'vendor/bin/phpcs' ], $phpcs_args ) );

// If there is a status other than 0, we have an error. Bail.
if ( $status ) {
	exit( $status );
}

exit( $status );
