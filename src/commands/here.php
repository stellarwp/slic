<?php
/**
 * Sets the directory target operations directory.
 * The directory could be a WordPress installation directory, the one containing the `wp-config.php` file,
 * a themes directory or a plugins directory.
 *
 * @var string $run_settings_file The absolute path to the file that should be used to load runtime settings.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Sets the current plugins directory to be the one used by slic.

	USAGE:

		<yellow>{$cli_name} {$subcommand} [reset]</yellow>

	EXAMPLES:

		<light_cyan>{$cli_name} here</light_cyan>
		Sets the current directory to be used within the {$cli_name} stack for selecting targets.

		<light_cyan>{$cli_name} here reset</light_cyan>
		Unsets the directory that {$cli_name} uses to select targets.
	HELP;

	echo colorize( $help );
	return;
}

$sub_args    = args( [ 'reset' ], $args( '...' ), 0 );
$reset       = $sub_args( 'reset', false );

$wp_dir         = SLIC_ROOT_DIR . '/_wordpress';
$plugins_dir    = SLIC_ROOT_DIR . '/_plugins';
$mu_plugins_dir = SLIC_ROOT_DIR . '/_wordpress/wp-content/mu-plugins';
$themes_dir     = SLIC_ROOT_DIR . '/_wordpress/wp-content/themes';

if ( empty( $reset ) ) {
	$here_dir = getcwd();
	if ( false === $here_dir ) {
		echo magenta( "Cannot get the current working directory with 'getcwd'; please make sure it's accessible." );
		exit( 1 );
	}
} else {
	$here_dir = $plugins_dir;
}

$has_wp_config = dir_has_wp_config( $here_dir );
$env_values    = [
	'SLIC_BUILD_SUBDIR'             => 1,
	'SLIC_MU_PLUGINS_DIR'           => $mu_plugins_dir,
	'SLIC_WP_CONTENT_CONTAINER_DIR' => '/var/www/html/wp-content',
];

if ( $has_wp_config ) {
	if ( file_exists( "{$here_dir}/wp-content" ) ) {
		$wp_content_dir = "{$here_dir}/wp-content";
	} elseif ( file_exists( "{$here_dir}/content" ) ) {
		$wp_content_dir = "{$here_dir}/content";
	} else {
		echo magenta( "Cannot locate the WordPress content directory." );
		exit( 1 );
	}

	$env_values['SLIC_HERE_DIR'] = $here_dir;

	$env_values['SLIC_BUILD_SUBDIR']              = 0;
	$env_values['SLIC_MU_PLUGINS_DIR']            = "{$wp_content_dir}/mu-plugins";
	$env_values['SLIC_PLUGINS_DIR']              = "{$wp_content_dir}/plugins";
	$env_values['SLIC_THEMES_DIR']               = "{$wp_content_dir}/themes";
	$env_values['SLIC_WP_DIR']                    = $here_dir;
	$env_values['SLIC_WP_CONTENT_CONTAINER_DIR'] = '/var/www/html/' . basename( $wp_content_dir );
} else {
	$env_values['SLIC_HERE_DIR']     = $here_dir;
	$env_values['SLIC_WP_DIR']       = $wp_dir;

	// Auto-detect a themes directory: the current directory is named `themes`. This covers both standard WordPress
	// (`wp-content/themes` beside `wp-content/plugins`) and Bedrock-style (`content/themes` beside `content/plugins`)
	// layouts.
	// In that case point SLIC_THEMES_DIR at the current directory so SLIC_HERE_DIR === SLIC_THEMES_DIR
	// and get_project_type() resolves theme targets correctly, while SLIC_PLUGINS_DIR points at the sibling plugins
	// directory so dependencies still install into wp-content/plugins.
	$sibling_plugins_dir = dirname( $here_dir ) . '/plugins';
	if ( basename( $here_dir ) === 'themes' ) {
		$env_values['SLIC_PLUGINS_DIR'] = $sibling_plugins_dir;
		$env_values['SLIC_THEMES_DIR']  = $here_dir;
	} else {
		$env_values['SLIC_PLUGINS_DIR'] = $here_dir;
		$env_values['SLIC_THEMES_DIR']  = $themes_dir;
	}
}

// When changing the here target, clear the currently selected project.
$env_values['SLIC_CURRENT_PROJECT']                = '';
$env_values['SLIC_CURRENT_PROJECT_CONTAINER_PATH'] = '';
$env_values['SLIC_CURRENT_PROJECT_RELATIVE_PATH']  = '';
$env_values['SLIC_CURRENT_PROJECT_SUBDIR']         = '';

write_env_file( $run_settings_file, $env_values, true );

setup_slic_env( root() );
quietly_tear_down_stack();

echo colorize( PHP_EOL . "<light_cyan>Slic plugin path set to</light_cyan> {$here_dir}." . PHP_EOL . PHP_EOL );
echo colorize( "If this is the first time setting this plugin path, be sure to <light_cyan>slic init <plugin></light_cyan>." );
