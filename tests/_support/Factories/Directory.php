<?php

namespace StellarWP\Slic\Test\Support\Factories;

use RuntimeException;

class Directory {
	private string $absolutePath;

	/**
	 * Directory constructor; it will create the directory as side-effect.
	 *
	 * since TBD
	 *
	 * @param string $absolutePath The absolute path to the directory to create.
	 *
	 * @throws RuntimeException On failure to create the directory.
	 */
	public function __construct( string $absolutePath ) {
		$this->absolutePath = $this->createDirectory( $absolutePath );
	}

	/**
	 * Returns the directory absolute path.
	 *
	 * @return string The directory absolute path.
	 */
	public function getAbsolutePath(): string {
		return $this->absolutePath;
	}

	/**
	 * Factory method to create a new directory in the system temp directory.
	 *
	 * @param string|null $path The relative path of the directory to create, or `null` to generate a random path.
	 *
	 * @return self
	 *
	 * @throws RuntimeException If the directory already exists or cannot be created.
	 */
	public static function createTemp( ?string $path = null ): self {
		$path         ??= '/slic-test-plugins-dir-' . uniqid( '', true );
		$absolutePath = sys_get_temp_dir() . '/' . ltrim( $path, '/' );

		return new self( $absolutePath );
	}

	/**
	 * Creates a plugin directory that contains an empty plugin main file.
	 *
	 * @param string $pluginDirectoryName The name of the plugin directory to create, e.g. `test-plugin`.
	 *
	 * @return $this
	 *
	 * @throws RuntimeException On failure to create the plugin directory or the main plugin file.
	 */
	public function createPlugin( string $pluginDirectoryName ): self {
		/** @noinspection UnusedFunctionResultInspection */
		$this->createDirectory( $this->absolutePath . '/' . ltrim( $pluginDirectoryName, '/' ) );

		$pluginFilePath = $this->absolutePath . "/$pluginDirectoryName/plugin.php";
		if ( ! file_put_contents( $pluginFilePath, "/**\n* Plugin Name: Test Plugin\n*/" ) ) {
			throw new RuntimeException( "Failed to create $pluginFilePath" );
		}

		return $this;
	}

	/**
	 * Creates a directory.
	 *
	 * @param string $absolutePath The absolute path to the directory to create.
	 *
	 * @return string The absolute path to the created directory.
	 *
	 * @throws RuntimeException On failure to create the directory or if the directory already exists.
	 */
	private function createDirectory( string $absolutePath ): string {
		if ( is_dir( $absolutePath ) ) {
			throw new RuntimeException( "Directory $absolutePath already exists." );
		}

		if ( ! mkdir( $absolutePath, 0777, true ) || ! is_dir( $absolutePath ) ) {
			throw new RuntimeException( "Failed to create plugins directory $absolutePath" );
		}

		return $absolutePath;
	}
}
