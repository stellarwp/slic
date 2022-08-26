<?php
/**
 * Dynamic script functions.
 */

namespace TEC\Tric;

/**
 * Gets the local environment's script directory.
 *
 * @return string
 */
function get_local_script_dir() {
	return TRIC_ROOT_DIR . '/' . trim( getenv( 'TRIC_SCRIPTS' ), '.' ) . '/';
}

/**
 * Gets the mounted script directory.
 *
 * @return string
 */
function get_mounted_script_dir() {
	return '/tric-scripts/';
}

/**
 * Builds the dynamic npm script.
 *
 * @param string $command The npm command to run.
 *
 * @return string
 */
function build_npm_script( $command ) {
	$command_script = <<< SCRIPT
	#!/bin/bash

	. /tric-scripts/before-npm.sh
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
function get_script_command( $script ) {
	return 'bash -c ". ' . $script . '"';
}