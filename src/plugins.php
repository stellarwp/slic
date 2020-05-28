<?php
/**
 * Plugin related functions for the build PHP scripts.
 */

namespace Tribe\Test;

use SplFileInfo;

/**
 * Returns a list of the available plugins in the `_plugins` directory.
 *
 * @return array<string,SplFileInfo> A map of each directory in the `_plugins` directory to the corresponding file
 *                                   information.
 */
function dev_plugins() {
	return wp_content_dir_list( 'plugins' );
}
