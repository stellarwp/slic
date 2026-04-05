# Environment Setup

This document describes three tiers of `setUp()` / `tearDown()` complexity for WPUnit integration tests. Choose the tier that matches your test's needs — avoid over-engineering setup when a simpler tier suffices.

## Tier 1: Minimal

Use when your test only needs WordPress loaded and makes assertions against built-in functions or your plugin's API without creating custom data.

```php
class SimpleTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_return_site_title(): void {
		$this->assertNotEmpty( get_bloginfo( 'name' ) );
	}
}
```

**What `parent::setUp()` does for you:**

- Starts a database transaction (rolled back in `tearDown()`).
- Resets WordPress global state (`$wp_actions`, `$wp_filters`, etc.).
- Clears the object cache.
- Sets the current user to `0` (logged out).

**What `parent::tearDown()` does for you:**

- Rolls back the database transaction — any rows inserted during the test disappear.
- Restores global state snapshots.

Even if your test does nothing special, always call both parent methods. They are the foundation of test isolation.

## Tier 2: Standard (with factories)

Use when your test creates posts, users, terms, or other WordPress objects. Factories create data inside the database transaction, so it's automatically rolled back.

```php
class PostFeatureTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int
	 */
	private $editor_id;

	/**
	 * @var int[]
	 */
	private $post_ids;

	public function setUp(): void {
		parent::setUp();

		// Create a user with the editor role.
		$this->editor_id = static::factory()->user->create( [
			'role' => 'editor',
		] );

		// Create 3 published posts authored by the editor.
		$this->post_ids = static::factory()->post->create_many( 3, [
			'post_author' => $this->editor_id,
			'post_status' => 'publish',
		] );
	}

	public function tearDown(): void {
		// Factory-created data is cleaned up by the transaction rollback.
		// Only clean non-transactional side effects here.

		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_return_posts_by_editor(): void {
		$query = new \WP_Query( [
			'author'      => $this->editor_id,
			'post_status' => 'publish',
		] );

		$this->assertSame( 3, $query->found_posts );
	}

	/**
	 * @test
	 */
	public function it_should_set_correct_author(): void {
		$post = get_post( $this->post_ids[0] );

		$this->assertEquals( $this->editor_id, $post->post_author );
	}
}
```

### Factory quick reference

```php
// Single objects — returns ID.
$post_id = static::factory()->post->create( [ 'post_title' => 'Hello' ] );
$user_id = static::factory()->user->create( [ 'role' => 'subscriber' ] );
$term_id = static::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'News' ] );

// Get the full object instead of just the ID.
$post = static::factory()->post->create_and_get( [ 'post_title' => 'Hello' ] );

// Create multiple — returns array of IDs.
$post_ids = static::factory()->post->create_many( 5, [ 'post_status' => 'publish' ] );
```

All factory-created data lives inside the test's database transaction and is rolled back automatically.

## Tier 3: Full isolation

Use when your test modifies global state beyond the database: options, filters, actions, transients, HTTP mocking, or static/singleton properties.

```php
class FullIsolationTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var string|false Original option value to restore.
	 */
	private $original_option;

	/**
	 * @var callable The HTTP mock filter callback (stored for removal).
	 */
	private $http_filter;

	public function setUp(): void {
		parent::setUp();

		// Save and override an option.
		$this->original_option = get_option( 'my_plugin_setting' );
		update_option( 'my_plugin_setting', 'test_value' );

		// Add an HTTP mock — block all external requests with a fake response.
		$this->http_filter = static function () {
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => wp_json_encode( [ 'success' => true ] ),
				'headers'  => [],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		// Add a custom action for testing.
		add_action( 'my_plugin_event', [ $this, 'track_event' ] );
	}

	public function tearDown(): void {
		// Restore the original option value.
		if ( false === $this->original_option ) {
			delete_option( 'my_plugin_setting' );
		} else {
			update_option( 'my_plugin_setting', $this->original_option );
		}

		// Remove the HTTP mock filter.
		remove_filter( 'pre_http_request', $this->http_filter, 10 );

		// Remove the custom action.
		remove_action( 'my_plugin_event', [ $this, 'track_event' ] );

		// Always call parent last.
		parent::tearDown();
	}

	// ...test methods...
}
```

### Checklist for Tier 3 tearDown

| Side effect | How to undo |
|-------------|-------------|
| `update_option()` | Restore original value or `delete_option()` |
| `add_filter()` / `add_action()` | `remove_filter()` / `remove_action()` with same priority |
| `set_transient()` | `delete_transient()` |
| Global variable changed | Restore from saved copy |
| Static property set | Reset to original value |
| `wp_cache_set()` | `wp_cache_flush()` or `wp_cache_delete()` |

Every item you touch in `setUp()` must have a corresponding undo in `tearDown()`.

## One-time setup with `setUpBeforeClass` / `tearDownAfterClass`

For expensive operations that don't change between tests (e.g., importing a large fixture file, registering a custom post type for the entire class), use the static class-level hooks:

```php
class ExpensiveSetupTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int Shared fixture — created once for all tests in this class.
	 */
	private static $fixture_page_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		// Expensive one-time setup.
		self::$fixture_page_id = static::factory()->post->create( [
			'post_type'   => 'page',
			'post_title'  => 'Fixture Page',
			'post_status' => 'publish',
		] );
	}

	public static function tearDownAfterClass(): void {
		// Clean up the shared fixture.
		wp_delete_post( self::$fixture_page_id, true );

		parent::tearDownAfterClass();
	}

	public function setUp(): void {
		parent::setUp();
		// Per-test setup can reference self::$fixture_page_id.
	}

	public function tearDown(): void {
		parent::tearDown();
	}
}
```

**Important**: Data created in `setUpBeforeClass()` lives outside the per-test transaction. You must clean it up explicitly in `tearDownAfterClass()`.

## Common pitfalls

1. **Calling `parent::setUp()` last instead of first** — WordPress state is not clean when your setup code runs, leading to hard-to-debug failures.

2. **Calling `parent::tearDown()` first instead of last** — the transaction rolls back before your cleanup code runs, so you may be trying to undo changes that are already gone (or worse, you skip cleanup that happens outside the transaction).

3. **Forgetting to remove filters added in setUp** — even though `parent::tearDown()` resets some global state, filters added at specific priorities may not be fully cleaned up. Always explicitly remove them.

4. **Using `setUpBeforeClass` for data that should be per-test** — if tests modify the shared fixture, they'll interfere with each other. Use `setUp()` for anything tests might mutate.

5. **Not storing the original value before overriding** — if you change an option or global, you need the original value to restore it. Save it at the top of `setUp()`.
