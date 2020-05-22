<?php

namespace Tribe\Test;

if ( $is_help ) {
	echo "Updates the tool and the images used in its services.\n";
	echo PHP_EOL;
	echo colorize( "usage: <light_cyan>{$cli_name} update</light_cyan>\n" );
	return;
}

rebuild_stack();
update_stack_images();
