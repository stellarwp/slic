# Test Anatomy

This document covers the structure, naming conventions, and skeleton for WPUnit integration tests used with slic and Codeception/wp-browser.

## File location and naming

Test files live inside the suite directory configured in your `codeception.dist.yml` or `codeception.yml`. The most common suite for integration tests is `wpunit`.

```
tests/
└── wpunit/
    ├── SomeFeatureTest.php
    ├── AnotherFeatureTest.php
    └── SubNamespace/
        └── DeeperTest.php
```

**Naming rules:**

- File name: `<DescriptiveName>Test.php` — always ends with `Test`.
- Class name: matches the file name exactly (PSR-4 autoloading).
- One test class per file.

## Namespace conventions

Namespaces typically mirror the directory structure under the suite root. Common patterns:

```php
// tests/wpunit/SomeFeatureTest.php
namespace Starter_Plugin\Tests\WPUnit;

// tests/wpunit/Admin/SettingsTest.php
namespace Starter_Plugin\Tests\WPUnit\Admin;
```

Check your project's `codeception.dist.yml` for the `namespace` key — it sets the root namespace for generated tests.

## Complete test skeleton

```php
<?php

namespace Starter_Plugin\Tests\WPUnit;

use Codeception\TestCase\WPTestCase;

/**
 * Tests for the Foo feature.
 */
class FooTest extends WPTestCase {

	private int $post_id;

	/**
	 * Runs before every test method.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Arrange shared fixtures.
		$this->post_id = $this->factory()->post->create( [
			'post_title'  => 'Test Post',
			'post_status' => 'publish',
		] );
	}

	/**
	 * Runs after every test method.
	 */
	protected function tearDown(): void {
		// Clean up anything not handled by factories.

		parent::tearDown();
	}

	public function test_it_should_return_the_post_title(): void {
		// Act.
		$title = get_the_title( $this->post_id );

		// Assert.
		$this->assertSame( 'Test Post', $title );
	}

	public function test_it_should_have_publish_status(): void {
		// Act.
		$post = get_post( $this->post_id );

		// Assert.
		$this->assertSame( 'publish', $post->post_status );
	}
}
```

### Key points

- **`parent::setUp()` is always the first call** in `setUp()`.
- **`parent::tearDown()` is always the last call** in `tearDown()`.
- The parent class handles database transaction rollback, so factory-created data is automatically cleaned up between tests.

## The AAA pattern

Every test method should follow Arrange, Act, Assert:

```php
public function test_discount_is_applied(): void {
	// Arrange — set up the conditions.
	$product_id = $this->factory()->post->create( [
		'post_type' => 'product',
	] );
	update_post_meta( $product_id, '_price', '100' );
	update_post_meta( $product_id, '_discount', '10' );

	// Act — execute the behavior being tested.
	$final_price = my_plugin_get_final_price( $product_id );

	// Assert — verify the outcome.
	$this->assertEquals( 90, $final_price );
}
```

Keep each section visually separated with a comment. If Arrange is complex, consider moving it to `setUp()` or a private helper method.

## Test method naming

Two styles are accepted. Pick one and stay consistent within a file:

### Style 1: `test_` prefix (preferred)

```php
public function test_it_should_create_a_user(): void {
	// ...
}
```

### Style 2: `@test` annotation

```php
/**
 * @test
 */
public function it_should_create_a_user(): void {
	// ...
}
```

Both are equivalent to Codeception/PHPUnit. The `test_` prefix is preferred — it's less noise and avoids needing a docblock just for the annotation.

## Data providers

Use data providers to run the same test logic with multiple inputs:

```php
/**
 * @dataProvider status_provider
 */
public function test_it_should_accept_valid_statuses( string $status, bool $expected ): void {
	$result = my_plugin_is_valid_status( $status );

	$this->assertSame( $expected, $result );
}

/**
 * Data provider for valid statuses.
 *
 * @return array<string, array{string, bool}>
 */
public function status_provider(): array {
	return [
		'publish is valid'   => [ 'publish', true ],
		'draft is valid'     => [ 'draft', true ],
		'invalid is invalid' => [ 'banana', false ],
	];
}
```

**Rules for data providers:**

- Method must be `public` and return an `array` (or `Generator`).
- Use descriptive string keys — they appear in test output on failure.
- The provider method name goes in the `@dataProvider` annotation.

## Setting up composer.json test namespaces

Tests should follow PSR-4 autoloading, configured in `composer.json`. Each suite gets its own namespace:

```json
{
    "autoload-dev": {
        "psr-4": {
            "My_Plugin\\Tests\\Unit\\": "tests/unit/",
            "My_Plugin\\Tests\\WPUnit\\": "tests/wpunit/",
            "My_Plugin\\Tests\\Functional\\": "tests/functional/",
            "My_Plugin\\Tests\\Acceptance\\": "tests/acceptance/"
        }
    }
}
```

**Key rules:**

- Each suite directory maps to its own namespace root.
- Test file namespaces must match the directory structure under the suite root (PSR-4).
- Run `slic composer dump-autoload` after modifying `autoload-dev` to regenerate the autoloader.

**Example mapping:**

| File path | Namespace |
|-----------|-----------|
| `tests/wpunit/FooTest.php` | `My_Plugin\Tests\WPUnit` |
| `tests/wpunit/Admin/SettingsTest.php` | `My_Plugin\Tests\WPUnit\Admin` |
| `tests/unit/HelperTest.php` | `My_Plugin\Tests\Unit` |

The namespace in `codeception.dist.yml` must match:

```yaml
suites:
  wpunit:
    actor: WpunitTester
    path: wpunit
    namespace: My_Plugin\Tests\WPUnit
    modules:
      enabled:
        - WPLoader
```

## Generating test files with slic

slic wraps the Codeception `generate` commands:

```bash
# Generate a WPUnit test class:
slic cc generate:wpunit wpunit "FooTest"

# Generate inside a subdirectory:
slic cc generate:wpunit wpunit "Admin/SettingsTest"
```

This creates a skeleton file in the correct location with the proper namespace and base class. You can then fill in `setUp()`, `tearDown()`, and test methods.

## Common mistakes to avoid

1. **Forgetting `parent::setUp()`** — WordPress won't be in a clean state; tests will interfere with each other.
2. **Forgetting `parent::tearDown()`** — the database transaction won't roll back; leftover data leaks into subsequent tests.
3. **Hardcoding post/user IDs** — IDs are auto-incremented and differ between runs. Always use factory return values.
4. **Putting test logic in the constructor** — use `setUp()` instead. Constructors run at class load time, not per-test.
5. **Multiple assertions testing unrelated things** — each test method should verify one behavior. Split if needed.
6. **Missing `void` return type** — while not strictly required, adding `: void` to test methods follows modern PHP conventions and makes intent clear.
