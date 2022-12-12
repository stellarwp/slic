<?php

namespace StellarWP\Slic\Tests;

trait Temp_Dirs {
	private function make_tmp_dir( string $prefix = 'tmp_' ): string {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 1 );
		$file  = $trace[0]['file'] ?? null;

		if ( ! is_string( $file ) ) {
			throw new \RuntimeException( 'Could not determine the calling file.' );
		}

		$tmp_dir = dirname( $file ) . '/__tmp__/' . $prefix . uniqid();
		if ( ! ( mkdir( $tmp_dir, 0777, true ) && is_dir( $tmp_dir ) ) ) {
			throw new \RuntimeException( "Could not create temporary directory $tmp_dir" );
		}

		return $tmp_dir;
	}
}
