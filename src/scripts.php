<?php
/**
 * Dynamic script functions.
 */

namespace StellarWP\Slic;

/**
 * Gets the local environment's script directory.
 *
 * @return string
 */
function get_local_script_dir() : string {
	return SLIC_ROOT_DIR . '/' . trim( getenv( 'SLIC_SCRIPTS' ), '.' ) . '/';
}

/**
 * Gets the mounted script directory.
 *
 * @return string
 */
function get_mounted_script_dir() : string {
	return '/slic-scripts/';
}

/**
 * Builds the dynamic npm script.
 *
 * @param string $command The npm command to run.
 *
 * @return string
 */
function build_npm_script( string $command ) : string {
	$command_script = <<< SCRIPT
	#!/bin/bash

	. /slic-scripts/before-npm.sh
	$command
	SCRIPT;

	$file_name = '.npm.sh';
	$file      = get_local_script_dir() . $file_name;

	file_put_contents( $file, $command_script );

	return get_mounted_script_dir() . $file_name;
}

/**
 * Builds a docker exec compatible script execution command.
 *
 * @param string $script The script to run.
 *
 * @return string
 */
function get_script_command( string $script ) : string {
	return 'bash -c ". ' . $script . '"';
}