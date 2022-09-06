<?php
/**
 * Provides functions for container notifications.
 */

namespace StellarWP\Slic;

function service_wordpress_notify() {
	echo colorize( PHP_EOL . "Your WordPress site is reachable at: <yellow>http://localhost:" . getenv( 'WORDPRESS_HTTP_PORT' ) . "</yellow>" . PHP_EOL );
}