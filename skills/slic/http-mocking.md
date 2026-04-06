# HTTP Mocking

WordPress plugins frequently make HTTP requests (API calls, license checks, remote data fetches). In integration tests you need to control these requests so tests are fast, deterministic, and don't depend on external services.

This document covers three patterns for intercepting WordPress HTTP requests, from simplest to most flexible.

## The slic testing environment

By default, slic containers can reach external networks. If you need total network isolation, use the airplane-mode command:

```bash
slic airplane-mode on   # blocks all external HTTP from WordPress
slic airplane-mode off  # restores normal networking
```

Regardless of the airplane-mode setting, you should mock HTTP in your tests to avoid depending on network availability or external API state.

## Pattern A: Simple mock response

Use the `pre_http_request` filter to short-circuit `wp_remote_get()`, `wp_remote_post()`, and friends. When this filter returns a non-false value, WordPress skips the actual HTTP request entirely.

```php
class ApiClientTest extends \Codeception\TestCase\WPTestCase {

	private \Closure $mock_filter;

	protected function setUp(): void {
		parent::setUp();

		// Return a fake 200 response for all HTTP requests.
		$this->mock_filter = static function ( $preempt, $parsed_args, $url ) {
			return [
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'body'     => wp_json_encode( [
					'status' => 'active',
					'items'  => [ 'one', 'two', 'three' ],
				] ),
				'headers'  => [],
				'cookies'  => [],
			];
		};

		add_filter( 'pre_http_request', $this->mock_filter, 10, 3 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->mock_filter, 10 );

		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_parse_api_response(): void {
		// Act — this calls wp_remote_get() internally.
		$result = my_plugin_fetch_items();

		// Assert.
		$this->assertCount( 3, $result );
		$this->assertContains( 'one', $result );
	}
}
```

### Response array structure

The mock response must match the structure WordPress expects from `WP_Http::request()`:

```php
[
	'response' => [
		'code'    => 200,         // HTTP status code (int).
		'message' => 'OK',        // HTTP reason phrase (string).
	],
	'body'     => '...',          // Response body (string). Use wp_json_encode() for JSON.
	'headers'  => [],             // Response headers (array or Requests_Utility_CaseInsensitiveDictionary).
	'cookies'  => [],             // Response cookies (array of WP_Http_Cookie).
]
```

**Common mistake**: Returning only the `body` without the `response` key — this causes `wp_remote_retrieve_response_code()` to return `''` instead of the expected status code.

### URL-specific mocking

To mock different responses for different URLs:

```php
$this->mock_filter = static function ( $preempt, $parsed_args, $url ) {
	if ( str_contains( $url, 'api.example.com/items' ) ) {
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => wp_json_encode( [ 'items' => [] ] ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	if ( str_contains( $url, 'api.example.com/auth' ) ) {
		return [
			'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
			'body'     => wp_json_encode( [ 'error' => 'Invalid token' ] ),
			'headers'  => [],
			'cookies'  => [],
		];
	}

	// Return false to let other URLs through (or mock them too).
	return false;
};
```

## Pattern B: Request capture

Sometimes you need to verify that your code sends the right HTTP request (correct URL, method, headers, body) rather than testing how it handles the response. Capture requests in an array and assert against them.

```php
class WebhookSenderTest extends \Codeception\TestCase\WPTestCase {

	private array $captured_requests = [];

	private \Closure $capture_filter;

	protected function setUp(): void {
		parent::setUp();

		$this->captured_requests = [];

		$this->capture_filter = function ( $preempt, $parsed_args, $url ) {
			$this->captured_requests[] = [
				'url'  => $url,
				'args' => $parsed_args,
			];

			// Return a success response so the code continues normally.
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => '',
				'headers'  => [],
				'cookies'  => [],
			];
		};

		add_filter( 'pre_http_request', $this->capture_filter, 10, 3 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->capture_filter, 10 );

		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_send_webhook_with_correct_payload(): void {
		// Arrange.
		$event_data = [ 'event' => 'order.completed', 'order_id' => 42 ];

		// Act.
		my_plugin_send_webhook( 'https://hooks.example.com/notify', $event_data );

		// Assert — verify exactly one request was made.
		$this->assertCount( 1, $this->captured_requests );

		// Assert — verify the URL.
		$this->assertSame(
			'https://hooks.example.com/notify',
			$this->captured_requests[0]['url']
		);

		// Assert — verify the body.
		$sent_body = json_decode( $this->captured_requests[0]['args']['body'], true );
		$this->assertSame( 'order.completed', $sent_body['event'] );
		$this->assertSame( 42, $sent_body['order_id'] );

		// Assert — verify it was a POST.
		$this->assertSame( 'POST', $this->captured_requests[0]['args']['method'] );
	}
}
```

## Pattern C: Response queue

When the code under test makes multiple sequential HTTP requests and you need each to return a different response, use a queue:

```php
class PaginatedFetcherTest extends \Codeception\TestCase\WPTestCase {

	private array $response_queue = [];

	private \Closure $queue_filter;

	protected function setUp(): void {
		parent::setUp();

		$this->queue_filter = function ( $preempt, $parsed_args, $url ) {
			if ( empty( $this->response_queue ) ) {
				$this->fail( 'Unexpected HTTP request: no more queued responses.' );
			}

			return array_shift( $this->response_queue );
		};

		add_filter( 'pre_http_request', $this->queue_filter, 10, 3 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->queue_filter, 10 );

		parent::tearDown();
	}

	/**
	 * @test
	 */
	public function it_should_fetch_all_pages(): void {
		// Arrange — queue two pages of results.
		$this->response_queue = [
			// Page 1.
			[
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => wp_json_encode( [
					'items'    => [ 'a', 'b' ],
					'has_more' => true,
				] ),
				'headers'  => [],
				'cookies'  => [],
			],
			// Page 2 (final page).
			[
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => wp_json_encode( [
					'items'    => [ 'c' ],
					'has_more' => false,
				] ),
				'headers'  => [],
				'cookies'  => [],
			],
		];

		// Act.
		$all_items = my_plugin_fetch_all_items();

		// Assert.
		$this->assertSame( [ 'a', 'b', 'c' ], $all_items );
		$this->assertEmpty( $this->response_queue, 'All queued responses should have been consumed.' );
	}
}
```

## Cleanup rules

1. **Always remove your filter in `tearDown()`** — store the callback reference in a property so you can pass the exact same callable to `remove_filter()`.

2. **Match the priority** — `remove_filter( 'pre_http_request', $this->mock_filter, 10 )` must use the same priority (here `10`) that was used in `add_filter()`.

3. **Reset captured data** — if you capture requests in an array property, reset it to `[]` in `setUp()` to prevent data leaking from a prior test method.

## Common pitfalls

- **Using an anonymous function without storing it** — you won't be able to remove it in `tearDown()`. Always assign the closure to a property.
- **Forgetting the `cookies` key** — some WordPress internals expect all four top-level keys. Missing `cookies` can cause notices.
- **Returning `true` instead of a response array** — `true` short-circuits the request but code calling `wp_remote_retrieve_body()` gets an empty string, which may cause confusing failures.
- **Filter priority conflicts** — if the plugin itself adds a `pre_http_request` filter, your test's filter priority matters. Use a lower number (higher priority) to intercept first, or a higher number to let the plugin's filter run and then override.
