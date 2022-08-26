<?php
/**
 * Handles the `composer-cache` command.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of slic CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	echo "Sets or displays the composer cache directory setting.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} composer-cache [(set <dir>|unset)]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer-cache</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer-cache unset</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} composer-cache set /home/person/.cache/composer</light_cyan>\n" );

	return;
}

$composer_cache_args = args( [ 'toggle', 'value' ], $args( '...' ), 0 );

slic_handle_composer_cache( $composer_cache_args );
