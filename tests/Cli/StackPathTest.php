<?php

namespace StellarWP\Slic\Test\Cli;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function StellarWP\Slic\slic_stacks_path_is_within;

require_once dirname( __DIR__, 2 ) . '/src/stacks.php';

// The stack resolvers compare paths as the OS returns them; a CLI test cannot produce Windows paths on Linux or macOS.
class StackPathTest extends TestCase {

	public static function pathProvider(): array {
		return [
			'the directory'                   => [ '/home/me/plugins', '/home/me/plugins', false, true ],
			'a descendant'                    => [ '/home/me/plugins/p1/src', '/home/me/plugins', false, true ],
			'a sibling with the same prefix'  => [ '/home/me/plugins-old', '/home/me/plugins', false, false ],
			'a sibling with a backslash'      => [ '/home/me/plugins\\p1', '/home/me/plugins', false, false ],
			'a Windows directory'             => [ 'C:\\Users\\me\\plugins', 'C:\\Users\\me\\plugins', true, true ],
			'a Windows descendant'            => [ 'C:\\Users\\me\\plugins\\p1', 'C:\\Users\\me\\plugins', true, true ],
			'a Windows sibling with a prefix' => [ 'C:\\Users\\me\\plugins-old', 'C:\\Users\\me\\plugins', true, false ],
		];
	}

	#[DataProvider( 'pathProvider' )]
	public function test_path_is_within_a_directory( string $path, string $dir, bool $windows, bool $expected ): void {
		$this->assertSame( $expected, slic_stacks_path_is_within( $path, $dir, $windows ) );
	}
}
