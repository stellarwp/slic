<?php
/**
 * Disable persistent object caching inside Slic test containers.
 *
 * WordPress automatically loads `object-cache.php` from `WP_CONTENT_DIR` before
 * normal plugins. A project's drop-in can retain data between tests or require
 * services and configuration that are not part of the test environment.
 *
 * `slic-stack.yml` bind-mounts this file read-only at
 * `/slic-disable-object-cache.php` in both the WordPress and Slic/Codeception
 * containers. Their PHP configuration loads it with `auto_prepend_file`, before
 * WordPress begins bootstrapping. Unlike mounting over the drop-in itself, this
 * does not create, replace, or remove anything in the host content directory.
 *
 * WordPress supports registering filters before its plugin API is loaded using
 * this array structure. When `wp-includes/plugin.php` loads, it converts the
 * entry into a `WP_Hook`. Later, `wp_start_object_cache()` applies the filter and
 * skips the project's drop-in. WordPress then loads its built-in, non-persistent
 * `WP_Object_Cache` implementation instead.
 */
$GLOBALS['wp_filter']['enable_loading_object_cache_dropin'][10][] = [
	'function'      => static function () {
		return false;
	},
	'accepted_args' => 0,
];
