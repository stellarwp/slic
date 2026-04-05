---
name: slic
description: >-
  Guide for creating, modifying, and running WPUnit integration tests with
  slic (StellarWP Local Interactive Containers). Covers slic workflow, test
  structure, environment setup, HTTP mocking, WordPress factories, assertions,
  and Codeception/wp-browser patterns. Use when writing or debugging WordPress
  plugin integration tests.
license: MIT
compatibility: Requires Docker and slic CLI on PATH
metadata:
  author: stellarwp
  version: "1.0"
---

# slic — WPUnit Integration Testing Guide

## When to use this skill

Activate this skill when:

- Writing new WPUnit integration tests for a WordPress plugin or theme
- Modifying or debugging existing Codeception/wp-browser tests
- Running test suites through slic (the `slic run` workflow)
- Setting up a project for slic-based testing for the first time
- Diagnosing flaky or order-dependent test failures

## Quick-start workflow

slic orchestrates Docker containers (MariaDB, Redis, WordPress, Chrome, and a Codeception runner) so you never configure a local test environment manually.

### 1. Point slic at your code

```bash
# From a plugins directory (e.g. wp-content/plugins):
slic here

# Or from a full WordPress root (where wp-config.php lives):
slic here
```

### 2. Select the target project

```bash
slic use my-plugin

# Subdirectory targets are supported:
slic use event-tickets/common
```

### 3. Initialize (first time only)

```bash
slic init my-plugin
```

This generates three files in the plugin root:

| File | Purpose |
|------|---------|
| `.env.testing.slic` | Database credentials, WordPress URL, container paths |
| `codeception.slic.yml` | Loads `.env.testing.slic` as Codeception params |
| `test-config.slic.php` | Optional WPLoader custom configuration |

### 4. Run tests

```bash
slic run                              # all suites, sequentially
slic run wpunit                       # one suite
slic run tests/wpunit/FooTest.php     # one file
slic run tests/wpunit/FooTest.php:test_something  # one method
```

### 5. Interactive shell (optional)

```bash
slic shell
# Inside the container:
> cr wpunit                           # shorthand for codecept run
```

## Test creation rules

When creating or modifying a test file, follow these rules:

1. **Extend `WPTestCase`** — every WPUnit test class extends `\Codeception\TestCase\WPTestCase` (wp-browser v3) or `lucatume\WPBrowser\TestCase\WPTestCase` (wp-browser v4).
2. **Use the AAA pattern** — Arrange, Act, Assert. Keep each section visually distinct.
3. **Name clearly** — file: `<DescriptiveName>Test.php`; methods: `test_<what_it_verifies>` or use `@test`.
4. **Isolate** — every test must pass in any order. Clean up in `tearDown()`.
5. **Use factories** — prefer `static::factory()->post->create()` over raw SQL or `wp_insert_post()` in test setup.
6. **Follow WordPress coding standards** — tabs for indentation, spaces inside parentheses.

See [test-anatomy.md](test-anatomy.md) for the complete file skeleton and naming rules.

## Environment setup tiers

Choose the right level of setUp/tearDown for your test:

| Tier | When to use | Guide |
|------|------------|-------|
| Minimal | Tests that only need WordPress loaded | [environment-setup.md](environment-setup.md#tier-1-minimal) |
| Standard | Tests that create posts, users, or terms | [environment-setup.md](environment-setup.md#tier-2-standard-with-factories) |
| Full isolation | Tests that mock HTTP, change globals, or modify options | [environment-setup.md](environment-setup.md#tier-3-full-isolation) |

## Testing patterns

| Pattern | Guide |
|---------|-------|
| HTTP mocking (3 approaches) | [http-mocking.md](http-mocking.md) |
| Assertions and WordPress factories | [assertions.md](assertions.md) |
| REST dispatch, Reflection, custom tables | [advanced-patterns.md](advanced-patterns.md) |
| Test isolation checklist (11 items) | [test-isolation-checklist.md](test-isolation-checklist.md) |

## Verification workflow

After writing or modifying tests, follow this sequence:

1. **Write the code** under test (or confirm it exists).
2. **Create or update tests** following the patterns above.
3. **Run the targeted test** — `slic run tests/wpunit/YourTest.php`.
4. **Run the full suite** — `slic run wpunit` — to catch side effects.
5. **Fix any failures** and re-run.
6. **Verify isolation** — run the single test again to confirm it passes independently.
7. **Check the [isolation checklist](test-isolation-checklist.md)** before committing.

## Reference material

| Topic | File |
|-------|------|
| Complete slic CLI command reference | [references/slic-commands.md](references/slic-commands.md) |
| Installation, setup, env files, CI | [references/slic-setup.md](references/slic-setup.md) |
| WPLoader config and WPTestCase API | [references/wp-browser-wploader.md](references/wp-browser-wploader.md) |

## External resources

- [slic repository](https://github.com/stellarwp/slic)
- [wp-browser documentation](https://wpbrowser.wptestkit.dev/)
- [Codeception documentation](https://codeception.com/docs/Introduction)
- [WordPress PHPUnit test utilities](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
