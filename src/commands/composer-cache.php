<?php

namespace Tribe\Test;

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

tric_handle_composer_cache( $composer_cache_args );
