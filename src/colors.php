<?php
/**
 * Functions to colorize and style CLI output.
 */

namespace Tribe\Test;

/**
 * Colorizes a string in a specific color.
 *
 * @param string $string     The string to colorize.
 * @param int    $color_code The string color, or style code, to apply to the string.
 *
 * @return string The style string.
 *
 * @see https://misc.flogisoft.com/bash/tip_colors_and_formatting
 */
function style( $string, $color_code ) {
	return "\033[" . $color_code . "m" . $string . "\033[0m";
}

/**
 * Colorizes a string in light cyan.
 *
 * @param string $string The string to colorize.
 *
 * @return string The colorized string.
 */
function light_cyan( $string ) {
	return style( $string, '1;36' );
}

/**
 * Colorizes a string in magenta.
 *
 * @param string $string The string to colorize.
 *
 * @return string The colorized string.
 */
function magenta( $string ) {
	return style( $string, 35 );
}

/**
 * Colorizes a string in red.
 *
 * @param string $string The string to colorize.
 *
 * @return string The colorized string.
 */
function red( $string ) {
	return style( $string, 31 );
}

/**
 * Colorizes a string in green.
 *
 * @param string $string The string to colorize.
 *
 * @return string The colorized string.
 */
function green( $string ) {
	return style( $string, 32 );
}

/**
 * Colorizes a string in yellow.
 *
 * @param string $string The string to colorize.
 *
 * @return string The colorized string.
 */
function yellow( $string ) {
	return style( $string, 33 );
}

/**
 * Colorizes and styles a string.
 *
 * Colors and styles placeholders should have the `<color>...</color>` format.
 * Nested styles will not work.
 *
 * @param string $string The string to style and colorize.
 *
 * @return string The styled and colorized string.
 */
function colorize( $string ) {
	$result = preg_replace_callback(
		'/<(?<style>[\\w]+)>(?<string>.*?)<\\/\\k<style>>/us',
		static function ( array $m ) {
			$function_name = '\\Tribe\\Test\\' . $m['style'];
			if ( ! function_exists( $function_name ) ) {
				return $m['string'];
			}

			return call_user_func( $function_name, $m['string'] );
		},
		$string
	);

	return $result;
}

/**
 * Bolds a string.
 *
 * @param string $string The string to make bold.
 *
 * @return string The string with the bold style applied.
 */
function bold( $string ) {
	return style( $string, 1 );
}
