# Advanced Patterns

This document covers testing patterns for REST API endpoints, private methods, custom database tables, and other advanced scenarios in WPUnit integration tests.

## REST API dispatch testing

Test REST endpoints without making actual HTTP requests by using `rest_do_request()`. This dispatches the request through WordPress's REST infrastructure internally.

```php
class REST_Items_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int
	 */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = static::factory()->user->create( [ 'role' => 'administrator' ] );

		// Register routes if your plugin registers them on rest_api_init.
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_return_items_for_authenticated_user(): void {
		// Arrange.
		wp_set_current_user( $this->admin_id );
		static::factory()->post->create_many( 3, [
			'post_type'   => 'post',
			'post_status' => 'publish',
		] );

		// Act.
		$request  = new \WP_REST_Request( 'GET', '/my-plugin/v1/items' );
		$request->set_param( 'per_page', 10 );
		$response = rest_do_request( $request );

		// Assert.
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 3, $response->get_data() );
	}

	/**
	 * @test
	 */
	public function it_should_reject_unauthenticated_requests(): void {
		// Arrange — no user set (logged out).
		wp_set_current_user( 0 );

		// Act.
		$request  = new \WP_REST_Request( 'GET', '/my-plugin/v1/items' );
		$response = rest_do_request( $request );

		// Assert.
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_create_an_item_via_post(): void {
		// Arrange.
		wp_set_current_user( $this->admin_id );

		// Act.
		$request = new \WP_REST_Request( 'POST', '/my-plugin/v1/items' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [
			'title'  => 'New Item',
			'status' => 'active',
		] ) );
		$response = rest_do_request( $request );

		// Assert.
		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'New Item', $data['title'] );
	}
}
```

### Key `WP_REST_Request` methods

| Method | Purpose |
|--------|---------|
| `set_param( $key, $value )` | Set a query/body parameter |
| `set_header( $key, $value )` | Set a request header |
| `set_body( $body )` | Set the raw request body |
| `set_method( $method )` | Override the HTTP method |
| `set_query_params( $params )` | Set multiple query params at once |
| `set_body_params( $params )` | Set multiple body params at once |

### Key `WP_REST_Response` methods

| Method | Purpose |
|--------|---------|
| `get_status()` | HTTP status code (int) |
| `get_data()` | Decoded response data (array/object) |
| `get_headers()` | Response headers (array) |
| `get_links()` | HAL links (array) |

## Testing private/protected methods with Reflection

Use Reflection only when there's no reasonable way to test behavior through the public API. Prefer testing through public methods whenever possible.

```php
/**
 * @test
 */
public function it_should_format_price_correctly(): void {
	$instance = new \My_Plugin\Pricing();

	// Access a private method via Reflection.
	$method = new \ReflectionMethod( $instance, 'format_price' );
	$method->setAccessible( true );

	$result = $method->invoke( $instance, 1234.5 );

	$this->assertSame( '$1,234.50', $result );
}

/**
 * @test
 */
public function it_should_have_correct_default_config(): void {
	$instance = new \My_Plugin\Settings();

	// Access a private property via Reflection.
	$property = new \ReflectionProperty( $instance, 'defaults' );
	$property->setAccessible( true );

	$defaults = $property->getValue( $instance );

	$this->assertArrayHasKey( 'enabled', $defaults );
	$this->assertTrue( $defaults['enabled'] );
}
```

### When to use Reflection

- Testing a complex private algorithm that's hard to exercise through the public API.
- Verifying internal state after a series of operations.
- **Not recommended** for simple getters/setters — those should be testable through public methods.

## Custom database tables

When your plugin creates custom tables, tests need to create and drop them to match production behavior.

```php
class Custom_Table_Test extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->create_custom_table();
	}

	public function tearDown(): void {
		$this->drop_custom_table();

		parent::tearDown();
	}

	private function create_custom_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'my_plugin_logs';

		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			message TEXT NOT NULL,
			level VARCHAR(20) NOT NULL DEFAULT 'info',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level)
		) {$wpdb->get_charset_collate()}" );
	}

	private function drop_custom_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'my_plugin_logs';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * @test
	 */
	public function it_should_insert_a_log_entry(): void {
		global $wpdb;

		my_plugin_log( 'Something happened', 'warning' );

		$row = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}my_plugin_logs ORDER BY id DESC LIMIT 1"
		);

		$this->assertSame( 'Something happened', $row->message );
		$this->assertSame( 'warning', $row->level );
	}
}
```

**Important**: Custom table operations (CREATE, DROP, INSERT) happen outside the test transaction managed by `WPTestCase`. You must explicitly drop the table in `tearDown()` to avoid leaking state.

If your plugin has its own table-creation method, use it instead of duplicating the schema:

```php
private function create_custom_table(): void {
	\My_Plugin\Database::create_tables();
}
```

## Mid-test option and filter changes

Sometimes you need to test how code behaves when options or filters change during execution:

```php
/**
 * @test
 */
public function it_should_respect_feature_flag_toggle(): void {
	// Start with feature disabled.
	update_option( 'my_plugin_feature_x', false );
	$this->assertFalse( my_plugin_is_feature_x_enabled() );

	// Enable mid-test.
	update_option( 'my_plugin_feature_x', true );
	$this->assertTrue( my_plugin_is_feature_x_enabled() );

	// Clean up — the transaction rollback handles option changes in the
	// wp_options table, so explicit cleanup is optional here. But if the
	// plugin caches the value in a static property, you must reset that.
}

/**
 * @test
 */
public function it_should_allow_filter_override(): void {
	// Default behavior.
	$default = my_plugin_get_limit();
	$this->assertSame( 10, $default );

	// Override via filter.
	$filter = static function () {
		return 50;
	};
	add_filter( 'my_plugin_limit', $filter );

	$this->assertSame( 50, my_plugin_get_limit() );

	// Clean up.
	remove_filter( 'my_plugin_limit', $filter );
}
```

## Testing cron schedules and events

```php
/**
 * @test
 */
public function it_should_schedule_daily_sync(): void {
	// Act.
	my_plugin_activate();

	// Assert.
	$timestamp = wp_next_scheduled( 'my_plugin_daily_sync' );
	$this->assertNotFalse( $timestamp, 'Expected cron event to be scheduled.' );

	$schedule = wp_get_schedule( 'my_plugin_daily_sync' );
	$this->assertSame( 'daily', $schedule );
}

/**
 * @test
 */
public function it_should_unschedule_on_deactivate(): void {
	// Arrange — schedule the event first.
	wp_schedule_event( time(), 'daily', 'my_plugin_daily_sync' );

	// Act.
	my_plugin_deactivate();

	// Assert.
	$timestamp = wp_next_scheduled( 'my_plugin_daily_sync' );
	$this->assertFalse( $timestamp );
}
```

## Testing with custom post types and taxonomies

If your test needs a custom post type that isn't registered by the plugin's bootstrap, register it in setUp and unregister in tearDown:

```php
public function setUp(): void {
	parent::setUp();

	register_post_type( 'event', [
		'public' => true,
		'label'  => 'Events',
	] );
}

public function tearDown(): void {
	unregister_post_type( 'event' );

	parent::tearDown();
}

/**
 * @test
 */
public function it_should_query_events(): void {
	static::factory()->post->create_many( 2, [
		'post_type'   => 'event',
		'post_status' => 'publish',
	] );

	$query = new \WP_Query( [ 'post_type' => 'event' ] );

	$this->assertSame( 2, $query->found_posts );
}
```

**Note**: If the plugin registers the post type during its normal activation (and the WPLoader module activates the plugin), you don't need to register it manually. Only use this pattern for post types not covered by the plugin's activation flow.

## Resetting static and singleton state

Some plugins use static properties or singletons that persist across tests because they live in PHP memory, not the database.

```php
public function tearDown(): void {
	// Reset a singleton instance.
	$property = new \ReflectionProperty( \My_Plugin\Container::class, 'instance' );
	$property->setAccessible( true );
	$property->setValue( null, null );

	// Reset a static cache property.
	$cache = new \ReflectionProperty( \My_Plugin\Cache::class, 'store' );
	$cache->setAccessible( true );
	$cache->setValue( null, [] );

	parent::tearDown();
}
```

If the class provides a public reset method (e.g., `Container::reset()`), prefer that over Reflection.
