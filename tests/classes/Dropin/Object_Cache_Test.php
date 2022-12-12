<?php

use PHPUnit\Framework\TestCase;
use StellarWP\Slic\Dropin\Object_Cache;
use StellarWP\Slic\Tests\Temp_Dirs;
use StellarWP\Slic\Tests\Uopz_Functions;

class Object_Cache_Test extends TestCase {
	use Uopz_Functions;
	use Temp_Dirs;

	/**
	 * It should throw if built on non existing content directory
	 *
	 * @test
	 */
	public function should_throw_if_built_on_non_existing_content_directory(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionCode( 1 );

		// Open with the equivalent of a `/dev/null` file.
		new Object_Cache( '/non/existing/content/dir', fopen( 'php://memory', 'wb' ) );
	}

	/**
	 * It should throw if content dir is not accessible
	 *
	 * @test
	 */
	public function should_throw_if_content_dir_is_not_accessible(): void {
		$this->uopz_set_fn_return( 'is_readable', static function ( string $dir ): bool {
			return $dir !== __DIR__ && is_readable( $dir );
		}, true );
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionCode( 2 );

		new Object_Cache( __DIR__, fopen( 'php://memory', 'wb' ) );
	}


	/**
	 * It should return the correct status of the object dropin
	 *
	 * @test
	 */
	public function should_return_the_correct_status_of_the_object_dropin(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );

		$this->assertEquals( Object_Cache::DROPIN_NOT_FOUND, $dropin->status( true ) );

		// Place another drop-in file in the temp dir.
		file_put_contents( $temp_dir . '/object-cache.php', '<?php /** Another drop-in file */' );

		$this->assertEquals( Object_Cache::OTHER_DROPIN_FOUND, $dropin->status( true ) );
	}

	/**
	 * It should correctly enable disable the dropin
	 *
	 * @test
	 */
	public function should_correctly_enable_disable_the_dropin(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable();

		$this->assertEquals( Object_Cache::DROPIN_FOUND, $dropin->status( true ) );
		$this->assertFileExists( $temp_dir . '/object-cache.php' );

		$dropin->disable( true );

		$this->assertEquals( Object_Cache::DROPIN_NOT_FOUND, $dropin->status( true ) );
		$this->assertFileDoesNotExist( $temp_dir . '/object-cache.php' );
	}

	/**
	 * It should replace the dropin if existing dropin header cannot be read
	 *
	 * @test
	 */
	public function should_replace_the_dropin_if_existing_dropin_header_cannot_be_read(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );
		file_put_contents( $temp_dir . '/object-cache.php', '<?php /** Another drop-in file */' );
		$this->uopz_set_fn_return( 'fopen', function ( string $file, ...$args ) use ( $temp_dir ) {
			if ( $file !== $temp_dir . '/object-cache.php' ) {
				return fopen( $file, ...$args );

			}

			// Stop mocking the return value.
			uopz_unset_return( 'fopen' );

			return false;
		}, true );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable( true );

		$this->assertEquals( Object_Cache::DROPIN_FOUND, $dropin->status( true ) );
		$this->assertFileExists( $temp_dir . '/object-cache.php' );
	}

	/**
	 * It should replace the dropin if different
	 *
	 * @test
	 */
	public function should_replace_the_dropin_if_different(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );
		file_put_contents( $temp_dir . '/object-cache.php', '<?php /** Another drop-in file */' );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable( true );

		$this->assertEquals( Object_Cache::DROPIN_FOUND, $dropin->status( true ) );
		$this->assertFileExists( $temp_dir . '/object-cache.php' );
	}

	/**
	 * It should throw if dropin cannot be written
	 *
	 * @test
	 */
	public function should_throw_if_dropin_cannot_be_written(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );
		$this->uopz_set_fn_return( 'copy', static function ( string $source, string $destination ) use ( $temp_dir ) {
			return ! ( $destination === $temp_dir . '/object-cache.php' ) && copy( $source, $destination );
		}, true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionCode( Object_Cache::FAILED_COPY );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable( true );
	}

	/**
	 * It should throw if dropin cannot be deleted
	 *
	 * @test
	 */
	public function should_throw_if_dropin_cannot_be_deleted(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );
		$this->uopz_set_fn_return( 'unlink', static function ( string $file ) use ( $temp_dir ) {
			return ! ( $file === $temp_dir . '/object-cache.php' ) && unlink( $file );
		}, true );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable( true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionCode( Object_Cache::FAILED_DELETE );

		$dropin->disable( true );
	}

	/**
	 * It should throw if dropin cannot be backed up
	 *
	 * @test
	 */
	public function should_throw_if_dropin_cannot_be_backed_up(): void {
		$temp_dir = $this->make_tmp_dir( 'object-cache-test_' );
		file_put_contents( $temp_dir . '/object-cache.php', '<?php /** Another drop-in file */' );
		$this->uopz_set_fn_return( 'copy', static function ( string $source, string $destination ) use ( $temp_dir ) {
			return ! ( $destination === $temp_dir . '/object-cache.php.bak' ) && copy( $source, $destination );
		}, true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionCode( Object_Cache::FAILED_BACKUP );

		$dropin = new Object_Cache( $temp_dir, fopen( 'php://memory', 'wb' ) );
		$dropin->enable( true );
	}
}
