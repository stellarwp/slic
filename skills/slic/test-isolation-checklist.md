# Test Isolation Checklist

Run through this checklist before committing new or modified tests. Each item prevents a class of flaky or order-dependent test failures.

## Checklist

1. **`parent::setUp()` is the first call in `setUp()`** — ensures WordPress starts in a clean, known state with a fresh database transaction.

2. **`parent::tearDown()` is the last call in `tearDown()`** — ensures the database transaction rolls back after all your cleanup code has run.

3. **Factory-created data is used for test objects** — `$this->factory()->post->create()` etc. lives inside the transaction and is automatically cleaned up. Avoid `wp_insert_post()` directly unless testing the insert function itself.

4. **All added filters and actions are removed in `tearDown()`** — store callbacks in properties and call `remove_filter()` / `remove_action()` with the same priority.

5. **Changed options are restored** — save the original value in `setUp()`, restore it in `tearDown()`. The transaction rollback covers `wp_options` table changes, but if the plugin caches option values in static properties, those need manual reset.

6. **Transients are deleted if set** — `delete_transient()` in `tearDown()` for any transient your test creates.

7. **Global variables are restored** — if you modify `$_GET`, `$_POST`, `$_SERVER`, or any plugin-specific global, save and restore the original value.

8. **No reliance on test execution order** — each test must pass when run alone (`slic run tests/wpunit/YourTest::test_one_method`) and when run in any order within the suite.

9. **No hardcoded IDs** — post IDs, user IDs, and term IDs are auto-incremented and differ between runs. Always use the return value of factory methods.

10. **HTTP mock filters are removed** — `pre_http_request` filters must be removed in `tearDown()` with the exact same callable and priority.

11. **Static and singleton state is reset** — if any test modifies a static property or singleton instance, reset it in `tearDown()` (via a public reset method or Reflection as a last resort).

## Quick reference

| # | Item | Risk if skipped | How to fix |
|---|------|----------------|------------|
| 1 | `parent::setUp()` first | WordPress state is dirty; tests see leftover data | Move to first line of `setUp()` |
| 2 | `parent::tearDown()` last | Transaction rollback happens before cleanup; cleanup may error or be skipped | Move to last line of `tearDown()` |
| 3 | Use factories | Manually inserted data persists if transaction fails to roll back | Replace `wp_insert_post()` with `$this->factory()->post->create()` |
| 4 | Remove filters/actions | Filters leak into subsequent tests, causing phantom failures | Store callback in `$this->callback`, remove in `tearDown()` |
| 5 | Restore options | Tests that read the option later get unexpected values | Save original in `setUp()`, restore in `tearDown()` |
| 6 | Delete transients | Cached values leak across tests | `delete_transient( 'key' )` in `tearDown()` |
| 7 | Restore globals | Request superglobals affect routing, authentication, and plugin behavior | Save `$_GET` etc. in `setUp()`, restore in `tearDown()` |
| 8 | No order dependence | Tests pass in CI but fail locally (or vice versa) when execution order changes | Run each test in isolation to verify |
| 9 | No hardcoded IDs | Tests fail on fresh databases or after other tests create objects | Use factory return values: `$id = $this->factory()->post->create()` |
| 10 | Remove HTTP mocks | Subsequent tests get mocked responses instead of real ones (or vice versa) | `remove_filter( 'pre_http_request', $this->mock, 10 )` |
| 11 | Reset static state | Singleton or cached values leak between tests | Call `Class::reset()` or use Reflection to null out the property |

## How to verify isolation

Run the specific test file in isolation to confirm it passes independently:

```bash
slic run tests/wpunit/YourTest.php
```

Then run the full suite to confirm no side effects:

```bash
slic run wpunit
```

If a test passes alone but fails in the suite (or vice versa), it has an isolation problem — work through this checklist to find the leak.
