<?php
/**
 * A set of functions to interact with Codeception and wp-browser
 *
 * @
 */

namespace StellarWP\Slic;

/**
 * Returns a set of service dependencies required to run a Codeception
 * command depending on the specific Codeception command.
 *
 * This list is manually curated and should be updated when required.
 * As an example, the `--version` command should not require the whole
 * WordPress stack to be up and running, while the `run` sub-command
 * should.
 *
 * @param array $codeception_args
 *
 * @return array<string> A list of service dependencies required to run
 *                       a Codeception command.
 */
function codeception_dependencies( array $codeception_args = [] ) {
	if ( empty( $codeception_args ) ) {
		return [];
	}

	$dependencies = [];

	if ( count( array_intersect( [
		'run',
	], $codeception_args ) ) ) {
		$dependencies[] = 'wordpress';
		$dependencies[] = 'db';
	}

	return array_values( array_unique( array_filter( $dependencies ) ) );
}
