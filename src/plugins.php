<?php
/**
 * Plugin related functions for the build PHP scripts.
 */

namespace Tribe\Test;

use CallbackFilterIterator;
use FilesystemIterator;
use SplFileInfo;

/**
 * Returns a list of the available plugins in the `_plugins` directory.
 *
 * @return array<string,SplFileInfo> A map of each directory in the `_plugins` directory to the corresponding file
 *                                   information.
 */
function dev_plugins() {
	$plugin_path     = tric_plugins_dir();

	if ( ! is_dir( $plugin_path ) ) {
		return [];
	}

	$dev_plugins     = [];
	$options         = FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

	$dev_plugins_dir = new CallbackFilterIterator( new FilesystemIterator( $plugin_path, $options ),
		static function ( SplFileInfo $file ) {
			return $file->isDir();
		}
	);

	$allowed_subdirs = [ 'common' ];
	foreach ( iterator_to_array( $dev_plugins_dir ) as $key => $value ) {
		$basename                 = basename( $key );
		$dev_plugins[ $basename ] = $value;
		foreach ( $allowed_subdirs as $subdir ) {
			$subdir_path = $value . '/' . $subdir;
			if ( file_exists( $subdir_path ) ) {
				$dev_plugins[ $basename . '/' . $subdir ] = $subdir_path;
			}
		}
	}

	return $dev_plugins;
}
