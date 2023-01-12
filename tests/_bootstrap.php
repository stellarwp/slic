<?php
/**
 * A bootstrap file dedicated to the tests.
 */

// Define a set of WordPress functions the object-cache plugin would require.
if ( ! function_exists( 'wp_suspend_cache_addition' ) ) {
	function wp_suspend_cache_addition( $suspend = null ) {
		return false;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return 1;
	}
}

// Recursively remove the tests/classes/Dropin/__tmp__ directory using the system's rm command.
exec( 'rm -rf "' . __DIR__ . '/classes/Dropin/__tmp__' . '"', $output, $result_code );
if ( $result_code !== 0 ) {
	throw new RuntimeException( 'Failed to remove the tests/classes/Dropin/__tmp__ directory: ' . implode( PHP_EOL, $output ) );
}

// Define some custom classes used in tests.
class Test_Object {
}
