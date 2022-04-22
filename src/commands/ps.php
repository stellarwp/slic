<?php
/**
 * Lists the containers part of the stack.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of the main CLI command, e.g. `tric`.
 */

namespace Tribe\Test;

if ($is_help) {
    echo "Lists the containers part of the stack.\n";
    echo PHP_EOL;
    echo colorize("usage: <light_cyan>{$cli_name} ps [...<options>]</light_cyan>\n");
    echo colorize("example: <light_cyan>{$cli_name} ps --filter name=redis</light_cyan>");

    return;
}

tric_realtime()(['ps']);
