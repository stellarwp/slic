<?php

namespace StellarWP\Slic\Dropin;

use function StellarWP\Slic\light_cyan;
use function StellarWP\Slic\magenta;
use function StellarWP\Slic\yellow;

class Object_Cache {
	public const DROPIN_NOT_FOUND = 0;
	public const OTHER_DROPIN_FOUND = 1;
	public const DROPIN_FOUND = 2;
	public const FAILED_BACKUP = 3;
	public const FAILED_DELETE = 4;
	public const FAILED_COPY = 5;

	private string $content_dir;
	/*
	 * @var resource
	 */
	private $output_stream;
	private string $header_id = 'Object-cache drop-in by slic';

	public function __construct( string $content_dir, $output_stream = STDOUT ) {
		if ( ! ( is_dir( $content_dir ) ) ) {
			throw new \InvalidArgumentException( "The content directory $content_dir does not exist.", 1 );
		}

		if ( ! ( is_readable( $content_dir ) && is_writable( $content_dir ) ) ) {
			throw new \InvalidArgumentException( "The content directory $content_dir is not accessible.", 2 );
		}

		$this->content_dir   = $content_dir;
		$this->output_stream = $output_stream;
	}

	public function enable( bool $quiet = false ): void {
		$status = $this->status( true );

		if ( $status === self::DROPIN_FOUND ) {
			if ( ! $quiet ) {
				fwrite( $this->output_stream, yellow( "The object-cache drop-in is already enabled." . PHP_EOL ) );
			}

			return;
		}

		if ( $status === self::OTHER_DROPIN_FOUND ) {
			if ( ! $quiet ) {
				fwrite( $this->output_stream, yellow( "Another object-cache drop-in found: it will be renamed to object-cache.php.bak." . PHP_EOL ) );
			}
			$this->backup_dropin();
		}

		$this->put_dropin();
		if ( ! $quiet ) {
			fwrite( $this->output_stream, light_cyan( "Object-cache drop-in enabled." ) . PHP_EOL );
		}
	}

	public function disable( bool $quiet ): void {
		$status = $this->status( true );

		if ( $status === self::DROPIN_NOT_FOUND ) {
			if ( ! $quiet ) {
				fwrite( $this->output_stream, yellow( "No object-cache drop-in found." . PHP_EOL ) );
			}

			return;
		}

		$this->delete_dropin();

		if ( ! $quiet ) {
			fwrite( $this->output_stream, light_cyan( "Object-cache drop-in disabled." ) . PHP_EOL );
		}
	}

	public function status( bool $quiet = false ): int {
		if ( is_file( $this->content_dir . '/object-cache.php' ) ) {
			if ( ! $quiet ) {
				fwrite(
					$this->output_stream,
					light_cyan( 'The object-cache.php file is present in the current wp-content directory.' ) . PHP_EOL
				);
			}

			$header = $this->read_file_header();

			if ( strpos( $header, $this->header_id ) === false ) {
				return self::OTHER_DROPIN_FOUND;
			}

			if ( ! $quiet ) {
				fwrite(
					$this->output_stream,
					magenta( 'Found an object-cache.php file, but it\'s not from slic.' ) . PHP_EOL
				);
			}

			return self::DROPIN_FOUND;
		}

		if ( ! $quiet ) {
			fwrite(
				$this->output_stream,
				light_cyan( 'The object-cache.php file is not present in the current wp-content directory.' ) . PHP_EOL
			);
		}

		return self::DROPIN_NOT_FOUND;
	}

	private function read_file_header(): string {
		$file = fopen( $this->content_dir . '/object-cache.php', 'rb' );

		if ( ! is_resource( $file ) ) {
			return '';
		}

		$file_header = fread( $file, 1024 );
		fclose( $file );

		return $file_header;
	}

	private function put_dropin(): void {
		if ( ! copy( __DIR__ . '/__dropins__/object-cache.php', $this->content_dir . '/object-cache.php' ) ) {
			throw new \RuntimeException( "Could not copy the object-cache.php drop-in to the $this->content_dir directory.", self::FAILED_COPY );
		}
	}

	private function delete_dropin(): void {
		if ( ! unlink( $this->content_dir . '/object-cache.php' ) ) {
			throw new \RuntimeException( "Could not delete the object-cache.php drop-in from the $this->content_dir directory.", self::FAILED_DELETE );
		}
	}

	private function backup_dropin(): void {
		if ( ! copy( $this->content_dir . '/object-cache.php', $this->content_dir . '/object-cache.php.bak' ) ) {
			throw new \RuntimeException( "Could not backup the object-cache.php drop-in in the $this->content_dir directory.", self::FAILED_BACKUP );
		}
	}
}
