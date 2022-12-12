<?php

namespace StellarWP\Slic;


if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Enables or disables the test object-cache dropin file.

	USAGE:

		<yellow>{$cli_name} object-cache-dropin [on|off|status]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} object-cache-dropin on</light_cyan>
		Places the object-cache.php file in the current wp-content directory.

		<light_cyan>{$cli_name} object-cache-dropin off</light_cyan>
		Removes the object-cache.php file from the current wp-content directory.
		
		<light_cyan>{$cli_name} object-cache-dropin status</light_cyan>
		Displays a message indicating whether the object-cache.php file is present in the current wp-content directory or not.
	HELP;

	echo colorize( $help );

	return;
}

$xdebug_args = args( [ 'toggle' ], $args( '...' ), 0 );
switch ( $xdebug_args( 'toggle', false ) ) {
	case 'on':
		( new Dropin\Object_Cache( wordpress_content_dir() ) )->enable();
		break;
	case 'off':
		( new Dropin\Object_Cache( wordpress_content_dir() ) )->disable();
		break;
	case 'status':
		( new Dropin\Object_Cache( wordpress_content_dir() ) )->status();
		break;
	default:
		echo magenta( 'Please specify a valid argument; supported arguments are on|off|status.' . PHP_EOL );
		break;
}
