<?php
/**
 * Handles the `init` command to initialize a plugin to use tric.
 *
 * @var bool     $is_help Whether we're handling an `help` request on this command or not.
 * @var \Closure $args    The argument map closure, as produced by the `args` function.
 */

namespace Tribe\Test;

if ( $is_help ) {
	echo "Initializes a plugin for use in tric.\n";
	echo PHP_EOL;
	echo colorize( "signature: <light_cyan>{$cli_name} init <plugin> [<branch>]</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} init the-events-calendar</light_cyan>\n" );
	echo colorize( "example: <light_cyan>{$cli_name} init event-tickets release/B20.04</light_cyan>\n" );
	return;
}

$sub_args = args( [ 'plugin', 'branch' ], $args( '...' ), 0 );
$plugin   = $sub_args( 'plugin', false );
// The default branch might not be `master`.
$branch = $sub_args( 'branch' );

// If a plugin isn't passed as an argument, the target is the current plugin being used.
if ( empty( $plugin ) ) {
	$plugin = tric_target();
	echo light_cyan( "Using {$plugin}\n" );
}

clone_plugin( $plugin, $branch );

// Since the `init` command is also the one that will rebuild assets, we need to switch branch if required.
if ( null !== $branch ) {
	switch_plugin_branch( $branch, $plugin );
}

setup_plugin_tests( $plugin );

if ( getenv( 'TRIC_BUILD_PROMPT' ) ) {
	$current_target = tric_target();

	if ( $current_target !== $plugin ) {
		tric_switch_target( $plugin );
	}

	$command_pool = maybe_build_install_command_pool( 'composer', $plugin, [ 'common' ] );
	$command_pool = array_merge( $command_pool, maybe_build_install_command_pool( 'npm', $plugin, [ 'common' ] ) );
	execute_command_pool( $command_pool );

	if ( $current_target !== $plugin ) {
		tric_switch_target( $current_target );
	}
}

echo light_cyan( "Finished initializing {$plugin}\n" );
