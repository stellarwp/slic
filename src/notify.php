<?php
/**
 * Provides functions for container notifications.
 */

namespace StellarWP\Slic;

/**
 * Outputs a notification about the WordPress site URL.
 *
 * Displays a colored message showing the local WordPress site URL with the configured HTTP port.
 *
 * @return void
 */
function service_wordpress_notify() {
	echo colorize( PHP_EOL . "Your WordPress site is reachable at: <yellow>http://localhost:" . getenv( 'WORDPRESS_HTTP_PORT' ) . "</yellow>" . PHP_EOL );
}