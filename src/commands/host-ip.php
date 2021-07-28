<?php
namespace Tribe\Test;

if ( $is_help ) {
    echo "Returns the IP Address of the host machine from the container perspective.\n";
    echo PHP_EOL;
    echo colorize( "example: <light_cyan>{$cli_name} host-ip</light_cyan>\n" );
    return;
}

// Buffer the output to avoid printing empty blank lines that might mangle the output in quite mode.
ob_start();
tric_passive()( [ 'run', '--rm', 'host-ip' ] );
echo trim( ob_get_clean() );
