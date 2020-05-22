<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Sets the plugin to use in the tests.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} use <target>[/<subdir>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} use the-events-calendar</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} use event-tickets/common</light_cyan>" );
	return;
}

$sub_args = args( [ 'target' ], $args( '...' ), 0 );
$target   = $sub_args( 'target', false );
ensure_dev_plugin( $target );
write_env_file( $run_settings_file, [ 'TRIC_CURRENT_PROJECT' => $target ], true );

echo light_cyan( "Using {$target}\n" );
