<?php

use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use StellarWP\Slic\Tests\Uopz_Functions;

if ( ! defined( 'SLIC_OBJECT_CACHE_DISABLE' ) ) {
	// Avoid the slic object cache from being globalized.
	define( 'SLIC_OBJECT_CACHE_DISABLE', true );
}

require_once dirname( __DIR__, 4 ) . '/src/classes/Dropin/dropins/object-cache.php';

class Slic_Object_Cache_Test extends TestCase {
	use Uopz_Functions;
	use MatchesSnapshots;

	/**
	 * It should be instantiatable
	 *
	 * @test
	 */
	public function should_be_instantiatable(): void {
		$dropin = new Slic_Object_Cache();

		$this->assertInstanceOf( Slic_Object_Cache::class, $dropin );
	}

	/**
	 * It should not add value to cache the addition suspended
	 *
	 * @test
	 */
	public function should_not_add_value_to_cache_the_addition_suspended(): void {
		$this->uopz_set_fn_return( 'wp_suspend_cache_addition', true );

		$added = ( new Slic_Object_Cache() )->add( 'key', 'data' );

		$this->assertFalse( $added );
	}

	public function invalid_key_provider(): array {
		return [
			'empty string' => [ '' ],
			'null'         => [ null ],
			'false'        => [ false ],
			'true'         => [ true ],
			'array'        => [ [ 'key' ] ],
			'object'       => [ (object) [ 'key' ] ],
		];
	}

	/**
	 * It should log violation if key is not valid
	 *
	 * @test
	 * @dataProvider invalid_key_provider
	 */
	public function should_log_violation_if_key_is_not_valid( $key ): void {
		$cache = new Slic_Object_Cache();

		$cache->add( $key, 'data' );

		$this->assertCount( 1, $cache->get_violations( Slic_Object_Cache::INVALID_SET_KEY ) );
		$this->assertMatchesSnapshot( $cache->get_violations() );
	}

	public function scalar_values_provider(): array {
		return [
			'string'                    => [ 'data' ],
			'empty string'              => [ '' ],
			'integer'                   => [ 23 ],
			'string integer'            => [ '23' ],
			'float'                     => [ 23.5 ],
			'string float'              => [ '23.5' ],
			'boolean true'              => [ true ],
			'string boolean true'       => [ 'true' ],
			'boolean false'             => [ false ],
			'string boolean false'      => [ 'false' ],
			'stdClass object'           => [ (object) [ 'hello' => 'world' ] ],
			'array of numbers'          => [ [ 1, 2, 3 ] ],
			'array of strings'          => [ [ 'one', 'two', 'three' ] ],
			'array of booleans'         => [ [ true, false, true ] ],
			'array of objects'          => [ [ (object) [ 'hello' => 'world' ], (object) [ 'hello' => 'world' ] ] ],
			'array of all types'        => [ [ 'one', 2, 3.5, true, false, (object) [ 'hello' => 'world' ], null ] ],
			'nested_array_of_all_types' => [
				[
					'one',
					2,
					3.5,
					true,
					false,
					(object) [ 'hello' => 'world' ],
					null,
					[ 'one', 2, 3.5, true, false, (object) [ 'hello' => 'world' ], null ]
				]
			]
		];
	}

	/**
	 * It should not report any violation when storing scalars or array of scalars
	 *
	 * @test
	 * @dataProvider scalar_values_provider
	 */
	public function should_not_report_any_violation_when_storing_scalars_or_array_of_scalars( $value ): void {
		$cache = new Slic_Object_Cache();

		$cache->add( 'test', $value, 'test_group' );

		$this->assertEmpty( $cache->get_violations() );
	}

	public function non_scalar_values_provider(): array {
		return [
			'non internal object'                                         => [
				new Test_Object()
			],
			'closure'                                                     => [
				function () {
				}
			],
			'static closure'                                              => [
				static function () {
				}
			],
			'file resource'                                               => [
				// This will be closed during the garbage collection.
				fopen( __FILE__, 'rb' )
			],
			'stdClass object with non scalar property'                    => [
				(object) [ 'hello' => 'world', 'object' => new Test_Object() ]
			],
			'array with non scalar value'                                 => [
				[ 'one', 2, 3.5, true, false, (object) [ 'hello' => 'world' ], null, new Test_Object() ]
			],
			'array with non scalar value in nested array'                 => [
				[
					new Test_Object(),
					[ 'one', 2, 3.5, true, false, (object) [ 'hello' => 'world' ], null, new Test_Object() ]
				]
			],
			'array with non scalar value and string keys in nested array' => [
				[
					'one'   => 'one',
					'two'   => 2,
					'three' => 3.5,
					'four'  => true,
					'five'  => false,
					'six'   => (object) [ 'hello' => 'world' ],
					'seven' => null,
					'eight' => new Test_Object(),
					'nine'  => [ 'one', 2, 3.5, true, false, (object) [ 'hello' => 'world' ], null, new Test_Object() ],
				]
			]
		];
	}

	/**
	 * It should report violations when storing non scalar values
	 *
	 * @test
	 * @dataProvider non_scalar_values_provider
	 */
	public function should_report_violations_when_storing_non_scalar_values( $value ): void {
		$cache = new Slic_Object_Cache();

		$cache->set( 'test', $value, 'test_group' );

		$this->assertMatchesSnapshot( $cache->get_violations() );
	}

	/**
	 * It should log a violation when trying to get value with invalid key
	 *
	 * @test
	 * @dataProvider invalid_key_provider
	 */
	public function should_log_a_violation_when_trying_to_get_value_with_invalid_key( $invalid_key ): void {
		$cache = new Slic_Object_Cache();

		$cache->get( $invalid_key );

		$this->assertCount( 1, $cache->get_violations() );
		$this->assertMatchesSnapshot( $cache->get_violations() );
	}

	/**
	 * It should log a violation when trying to delete with invalid key
	 *
	 * @test
	 * @dataProvider invalid_key_provider
	 */
	public function should_log_a_violation_when_trying_to_delete_with_invalid_key($invalid_key): void {
		$cache = new Slic_Object_Cache();

		$cache->delete( $invalid_key, 'test_group' );

		$this->assertCount( 1, $cache->get_violations() );
		$this->assertMatchesSnapshot( $cache->get_violations() );
	}

	/**
	 * It should allow cache CRUD operations
	 *
	 * @test
	 */
	public function should_allow_cache_CRUD_operations(): void {
		$cache = new Slic_Object_Cache();

		$cache->add( 'test', 'data', 'test_group' );
		$this->assertEmpty( $cache->get_violations() );

		$this->assertEquals( 'data', $cache->get( 'test', 'test_group',false,$found ) );
		$this->assertTrue( $found );
		$this->assertEmpty( $cache->get_violations() );

		$cache->add( 'test', 'data_2', 'test_group' );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertEquals( 'data', $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertTrue( $found );

		$cache->set( 'test', 'data_3', 'test_group' );

		$this->assertEmpty( $cache->get_violations() );
		$this->assertEquals( 'data_3', $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertTrue( $found );

		$cache->delete('test', 'test_group');
		$this->assertEmpty( $cache->get_violations() );
		$this->assertFalse( $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertFalse( $found );

		$cache->replace( 'test', 'data_4', 'test_group' );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertFalse( $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertFalse( $found );

		$cache->set( 'test', 'data_5', 'test_group' );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertEquals( 'data_5', $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertTrue( $found );

		$cache->replace( 'test', 'data_6', 'test_group' );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertEquals( 'data_6', $cache->get( 'test', 'test_group', false, $found ) );
		$this->assertEmpty( $cache->get_violations() );
		$this->assertTrue( $found );
	}

	/**
	 * It should log violations when trying to replace with invalid key
	 *
	 * @test
	 * @dataProvider invalid_key_provider
	 */
	public function should_log_violations_when_trying_to_replace_with_invalid_key($invalid_key): void {
		$cache = new Slic_Object_Cache();

		$cache->replace( $invalid_key, 'data', 'test_group' );

		$this->assertMatchesSnapshot( $cache->get_violations() );
	}
}
