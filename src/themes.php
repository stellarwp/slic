<?php
/**
 * Theme related functions for the build PHP scripts.
 */

namespace StellarWP\Slic;

use SplFileInfo;

/**
 * Returns a list of the available themes in the wp-content/themes directory.
 *
 * @return array<string,SplFileInfo> A map of each directory in the themes directory to the corresponding file
 *                                   information.
 */
function dev_themes() {
	return wp_content_dir_list( 'themes' );
}
