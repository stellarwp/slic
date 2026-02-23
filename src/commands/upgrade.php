<?php

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Upgrades {$cli_name} to the latest version.

	USAGE:

		<yellow>{$cli_name} {$subcommand}</yellow>
	HELP;

	echo colorize( $help );
	return;
}

if ( is_phar() ) {
	phar_self_update();
} else {
	git_upgrade();
}

/**
 * Upgrades slic via git pull on the main branch.
 */
function git_upgrade() {
	chdir( SLIC_ROOT_DIR );
	$status = passthru( 'git checkout main && git pull' );

	if ( ! $status ) {
		$remote_version_file = slic_data_dir() . '/.remote-version';
		if ( file_exists( $remote_version_file ) ) {
			unlink( $remote_version_file );
		}

		$status = passthru( PHP_BINARY . ' ' . escapeshellarg( $GLOBALS['argv'][0] ) . ' update' );
	}

	exit( $status );
}

/**
 * Self-updates the phar archive from GitHub Releases.
 */
function phar_self_update() {
	$current_version = CLI_VERSION;

	echo colorize( "Checking for updates..." . PHP_EOL );

	$latest_version = fetch_latest_github_release_version();

	if ( null === $latest_version ) {
		echo magenta( "Error: Could not check for updates. Please try again later." . PHP_EOL );
		exit( 1 );
	}

	if ( version_compare( $latest_version, $current_version, '<=' ) ) {
		echo light_cyan( "You are already running the latest version ({$current_version})." . PHP_EOL );
		exit( 0 );
	}

	echo colorize( "Updating from <yellow>{$current_version}</yellow> to <yellow>{$latest_version}</yellow>..." . PHP_EOL );

	// Fetch the release to get the slic.phar asset URL.
	$context = stream_context_create( [
		'http' => [
			'method'  => 'GET',
			'header'  => "User-Agent: slic-cli\r\nAccept: application/vnd.github.v3+json\r\n",
			'timeout' => 10,
		],
	] );

	$response = @file_get_contents( 'https://api.github.com/repos/stellarwp/slic/releases/latest', false, $context );

	if ( false === $response ) {
		echo magenta( "Error: Could not fetch release information." . PHP_EOL );
		exit( 1 );
	}

	$release = json_decode( $response, true );

	if ( ! is_array( $release ) || empty( $release['assets'] ) ) {
		echo magenta( "Error: No assets found in the latest release." . PHP_EOL );
		exit( 1 );
	}

	// Find the slic.phar asset.
	$phar_url = null;
	foreach ( $release['assets'] as $asset ) {
		if ( $asset['name'] === 'slic.phar' ) {
			$phar_url = $asset['browser_download_url'];
			break;
		}
	}

	if ( null === $phar_url ) {
		echo magenta( "Error: slic.phar not found in the latest release assets." . PHP_EOL );
		exit( 1 );
	}

	// Download the new phar.
	$download_context = stream_context_create( [
		'http' => [
			'method'          => 'GET',
			'header'          => "User-Agent: slic-cli\r\n",
			'timeout'         => 60,
			'follow_location' => true,
		],
	] );

	$new_phar = @file_get_contents( $phar_url, false, $download_context );

	if ( false === $new_phar || empty( $new_phar ) ) {
		echo magenta( "Error: Failed to download the new phar." . PHP_EOL );
		exit( 1 );
	}

	// Get the path to the currently running phar.
	$phar_path = \Phar::running( false );

	if ( empty( $phar_path ) ) {
		echo magenta( "Error: Could not determine the running phar path." . PHP_EOL );
		exit( 1 );
	}

	// Write the new phar to a temp file first, then rename for atomicity.
	$tmp_path = $phar_path . '.tmp';

	if ( false === file_put_contents( $tmp_path, $new_phar ) ) {
		echo magenta( "Error: Could not write the new phar to {$tmp_path}." . PHP_EOL );
		exit( 1 );
	}

	// Make the temp file executable.
	chmod( $tmp_path, 0755 );

	// Replace the old phar with the new one.
	if ( ! rename( $tmp_path, $phar_path ) ) {
		unlink( $tmp_path );
		echo magenta( "Error: Could not replace the phar at {$phar_path}." . PHP_EOL );
		exit( 1 );
	}

	// Clear the cached remote version.
	$remote_version_file = slic_data_dir() . '/.remote-version';
	if ( file_exists( $remote_version_file ) ) {
		unlink( $remote_version_file );
	}

	echo light_cyan( "Successfully updated slic to version {$latest_version}." . PHP_EOL );
	exit( 0 );
}
