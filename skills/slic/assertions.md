# Assertions and WordPress Factories

This document covers assertion patterns and factory usage for WPUnit integration tests with Codeception/wp-browser.

## WordPress factories

Factories create WordPress objects (posts, users, terms, comments, attachments) inside the test's database transaction so they are automatically cleaned up on rollback.

### Creating objects

```php
// Create and return the ID.
$post_id = static::factory()->post->create( [
	'post_title'  => 'My Post',
	'post_status' => 'publish',
	'post_type'   => 'post',
] );

// Create and return the full WP_Post object.
$post = static::factory()->post->create_and_get( [
	'post_title'  => 'My Post',
	'post_status' => 'publish',
] );

// Create multiple — returns array of IDs.
$post_ids = static::factory()->post->create_many( 5, [
	'post_status' => 'publish',
] );
```

### Available factories

| Factory | Creates | Common args |
|---------|---------|-------------|
| `static::factory()->post` | `WP_Post` | `post_title`, `post_status`, `post_type`, `post_author`, `post_content`, `post_date` |
| `static::factory()->user` | `WP_User` | `role`, `user_login`, `user_email`, `display_name` |
| `static::factory()->term` | `WP_Term` | `taxonomy`, `name`, `slug`, `parent`, `description` |
| `static::factory()->comment` | `WP_Comment` | `comment_post_ID`, `user_id`, `comment_content`, `comment_approved` |
| `static::factory()->attachment` | Attachment `WP_Post` | `file`, `post_parent`, `post_mime_type` |
| `static::factory()->category` | Category `WP_Term` | `name`, `slug`, `parent` |
| `static::factory()->tag` | Tag `WP_Term` | `name`, `slug` |

### Common factory recipes

**Post with meta:**

```php
$post_id = static::factory()->post->create( [
	'post_type' => 'product',
] );
update_post_meta( $post_id, '_price', '29.99' );
update_post_meta( $post_id, '_sku', 'WIDGET-001' );
```

**User with specific role and meta:**

```php
$user_id = static::factory()->user->create( [
	'role'       => 'editor',
	'user_email' => 'editor@example.com',
] );
update_user_meta( $user_id, 'preferred_language', 'en' );
```

**Term hierarchy:**

```php
$parent_id = static::factory()->term->create( [
	'taxonomy' => 'category',
	'name'     => 'Parent Category',
] );
$child_id = static::factory()->term->create( [
	'taxonomy' => 'category',
	'name'     => 'Child Category',
	'parent'   => $parent_id,
] );
```

**Post with terms assigned:**

```php
$post_id = static::factory()->post->create();
$term_id = static::factory()->term->create( [
	'taxonomy' => 'category',
	'name'     => 'News',
] );
wp_set_object_terms( $post_id, [ $term_id ], 'category' );
```

## Assertion patterns

### WP_Error assertions

```php
// Assert a value is a WP_Error.
$result = my_plugin_validate( '' );
$this->assertWPError( $result );

// Assert a value is NOT a WP_Error.
$result = my_plugin_validate( 'valid-input' );
$this->assertNotWPError( $result );

// Assert the error code.
$result = my_plugin_validate( '' );
$this->assertSame( 'empty_input', $result->get_error_code() );

// Assert the error message.
$this->assertSame( 'Input cannot be empty.', $result->get_error_message() );
```

### Post meta assertions

```php
$post_id = static::factory()->post->create();
update_post_meta( $post_id, '_my_key', 'expected_value' );

// Assert meta value.
$this->assertSame( 'expected_value', get_post_meta( $post_id, '_my_key', true ) );

// Assert meta does not exist.
$this->assertEmpty( get_post_meta( $post_id, '_nonexistent_key', true ) );
```

### User and user meta assertions

```php
$user_id = static::factory()->user->create( [ 'role' => 'editor' ] );
$user    = get_userdata( $user_id );

// Assert the role.
$this->assertContains( 'editor', $user->roles );

// Assert capabilities.
$this->assertTrue( $user->has_cap( 'edit_posts' ) );
$this->assertFalse( $user->has_cap( 'manage_options' ) );

// Assert user meta.
update_user_meta( $user_id, 'score', 42 );
$this->assertEquals( 42, get_user_meta( $user_id, 'score', true ) );
```

### Hook assertions (actions and filters)

```php
/**
 * @test
 */
public function it_should_fire_custom_action(): void {
	$fired = false;
	add_action( 'my_plugin_after_save', static function () use ( &$fired ) {
		$fired = true;
	} );

	my_plugin_save_settings( [ 'key' => 'value' ] );

	$this->assertTrue( $fired, 'Expected my_plugin_after_save to fire.' );
}

/**
 * @test
 */
public function it_should_count_action_calls(): void {
	$call_count = did_action( 'my_plugin_init' );

	my_plugin_initialize();

	$this->assertSame(
		$call_count + 1,
		did_action( 'my_plugin_init' ),
		'Expected my_plugin_init to fire exactly once more.'
	);
}

/**
 * @test
 */
public function it_should_apply_title_filter(): void {
	add_filter( 'my_plugin_title', static function ( $title ) {
		return 'Filtered: ' . $title;
	} );

	$result = apply_filters( 'my_plugin_title', 'Original' );

	$this->assertSame( 'Filtered: Original', $result );
}
```

### Option assertions

```php
/**
 * @test
 */
public function it_should_save_settings_to_options(): void {
	my_plugin_save_settings( [ 'color' => 'blue' ] );

	$saved = get_option( 'my_plugin_settings' );
	$this->assertIsArray( $saved );
	$this->assertSame( 'blue', $saved['color'] );
}
```

### HTML output assertions

```php
/**
 * @test
 */
public function it_should_render_widget_html(): void {
	ob_start();
	my_plugin_render_widget( [ 'title' => 'Hello' ] );
	$html = ob_get_clean();

	$this->assertStringContainsString( '<h2>Hello</h2>', $html );
	$this->assertStringContainsString( 'class="my-widget"', $html );
	$this->assertStringNotContainsString( 'class="error"', $html );
}
```

### REST response assertions

```php
/**
 * @test
 */
public function it_should_return_items_via_rest(): void {
	// Arrange — create test data.
	static::factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );

	// Act — dispatch a REST request internally (no HTTP needed).
	$request  = new \WP_REST_Request( 'GET', '/my-plugin/v1/items' );
	$response = rest_do_request( $request );

	// Assert.
	$this->assertSame( 200, $response->get_status() );

	$data = $response->get_data();
	$this->assertIsArray( $data );
	$this->assertCount( 3, $data );
}
```

### Exception assertions

```php
/**
 * @test
 */
public function it_should_throw_on_invalid_input(): void {
	$this->expectException( \InvalidArgumentException::class );
	$this->expectExceptionMessage( 'ID must be positive' );

	my_plugin_get_item( -1 );
}
```

## wp-browser v4 differences

In wp-browser v4, the base class namespace changes:

```php
// wp-browser v3
use Codeception\TestCase\WPTestCase;

// wp-browser v4
use lucatume\WPBrowser\TestCase\WPTestCase;
```

The factory API remains the same (`static::factory()->post->create()`, etc.). The key difference is the import path. Check your project's `composer.json` for which version is installed:

```bash
slic composer show lucatume/wp-browser | grep versions
```

## Assertion tips

1. **Prefer `assertSame()` over `assertEquals()`** — `assertSame()` checks type and value; `assertEquals()` does loose comparison. Use `assertSame()` by default and `assertEquals()` only when you intentionally want loose comparison (e.g., comparing int and numeric string).

2. **Use descriptive failure messages** — the third argument to most assertions is a message shown on failure:
   ```php
   $this->assertTrue( $result, 'Expected the sync to succeed for published posts.' );
   ```

3. **Assert one behavior per test** — if you have multiple unrelated assertions, split them into separate test methods. Related assertions (e.g., checking status code and response body of the same request) belong together.

4. **Use factories instead of raw SQL** — factories are transaction-safe and express intent clearly. Only use `$wpdb` directly when testing database-specific behavior.
