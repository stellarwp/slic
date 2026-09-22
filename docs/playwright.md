# Running Playwright tests

Slic runs your tests with the Playwright version installed in your project. Browsers
run in a separate container with their dependencies already installed, so you can
skip `playwright install`. Your tests and setup hooks still have access to PHP,
WP-CLI, and Composer inside Slic.

## Get started

Prepare your WordPress test site as usual, then install your project's dependencies
and run the tests:

```bash
slic npm ci
slic playwright test
```

You can pass the usual test filters:

```bash
slic playwright test tests/e2e/login.spec.ts
slic playwright test --grep 'can log in'
```

Keep your dependency lockfile committed and use `npm ci` in CI. Slic chooses the
browser image to match your installed Playwright version; you don't need to pin a
second version in Slic. The first run may take longer while Docker pulls the image.

Existing preparation scripts can keep `slic playwright install chromium --with-deps`.
Slic skips that installation because the browsers are already available.

## Use Playwright's built-in fixtures

Use the `page`, `context`, or `browser` fixtures in your tests. They connect to the
browser automatically, and Playwright manages their cleanup.

> [!WARNING]
> Avoid calling `chromium.launch()` directly in tests or setup hooks. It launches a
> local browser and does not use Slic's remote browser connection. Use Playwright's
> built-in fixtures, or [update your existing setup code](#update-setup-code-that-launches-a-browser-directly)
> to connect explicitly. The same applies to `firefox.launch()` and `webkit.launch()`.

Set the WordPress URL in `playwright.config.ts`:

```ts
import { defineConfig } from '@playwright/test';

export default defineConfig({
    use: {
        baseURL: 'http://wordpress.test',
    },
});
```

Then use relative URLs and Playwright's locators and assertions:

```ts
import { expect, test } from '@playwright/test';

test('shows the WordPress login form', async ({ page }) => {
    await page.goto('/wp-login.php');

    await expect(page.getByLabel('Username or Email Address')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Log In', exact: true })).toBeVisible();
});
```

Use `http://wordpress.test` for WordPress inside Slic. From the browser,
`localhost` refers to the browser's own container. If your tests start another
web server inside Slic, bind it to `0.0.0.0` and use `http://slic:<port>` as its
browser-facing URL.

## Keep PHP and WP-CLI setup in your hooks

Your hooks can continue calling PHP, WP-CLI, and Composer. For example, this hook
checks that a plugin is active before running the tests:

```ts
import { execFileSync } from 'node:child_process';
import { test } from '@playwright/test';

test.beforeAll(() => {
    execFileSync('wp', ['plugin', 'is-active', 'my-plugin'], {
        stdio: 'inherit',
    });
});
```

Replace `my-plugin` with your plugin's slug. Pass arguments as an array with
`execFileSync` so values containing spaces or shell characters remain intact.
A failed command fails the hook, and `stdio: 'inherit'` shows its output in the test log.

If hooks reset or import a shared database, avoid running those tests in parallel.
Separate browser sessions still use the same WordPress database.

## Update setup code that launches a browser directly

For new browser-based setup, prefer a
[Playwright setup project](https://playwright.dev/docs/test-global-setup-teardown#option-1-project-dependencies).
It can use the same built-in fixtures as your tests, with automatic browser cleanup
and setup results in the test report.

If you already use `chromium.launch()` in a global setup file, change that call to
connect when Slic supplies an endpoint:

```ts
import { chromium } from '@playwright/test';

export default async function globalSetup() {
    const endpoint = process.env.PW_TEST_CONNECT_WS_ENDPOINT;
    const browser  = endpoint
        ? await chromium.connect(endpoint)
        : await chromium.launch();

    try {
        const context = await browser.newContext({
            baseURL: process.env.WP_BASE_URL ?? 'http://wordpress.test',
        });
        const page = await context.newPage();

        await page.goto('/wp-login.php');
        // Add your project's login or other browser setup here.
    } finally {
        await browser.close();
    }
}
```

`PW_TEST_CONNECT_WS_ENDPOINT` is Playwright's own environment variable. Slic sets
it automatically to the browser server's WebSocket address. You don't need to add
it to your `.env` file. The fallback lets the same setup run outside Slic when local
browsers are installed.

Apply the same pattern to direct `firefox.launch()` or `webkit.launch()` calls.
`launchPersistentContext()` has no equivalent in this connection pattern; code
that depends on a persistent browser profile needs a separate migration.

## Save downloads to the test output directory

Use `download.saveAs()` to copy a download to your test output. Register the download
listener before clicking, so you don't miss the event:

```ts
import { test } from '@playwright/test';

test('exports a report', async ({ page }, testInfo) => {
    // Replace this route and button name with your project's export screen.
    await page.goto('/reports');

    const downloadPromise = page.waitForEvent('download');

    await page.getByRole('button', { name: 'Export report' }).click();

    const download = await downloadPromise;

    await download.saveAs(testInfo.outputPath('report.csv'));
});
```

`testInfo.outputPath()` keeps files separate for each test. Avoid `download.path()`,
which is unavailable with remote browsers. See Playwright's
[download guide](https://playwright.dev/docs/downloads) for more examples.

## Before switching an existing suite

Check for direct browser launches, browser-facing `localhost` URLs, and
`download.path()` calls, then run the suite against your prepared WordPress site.
If you rely on custom browser executables, Chrome/Edge channels, or browser launch
arguments, verify those separately; the remote browser may not support your settings.

This prototype supports `slic playwright test`. Headed and UI debugging have not
been validated, and commands such as `codegen`, `open`, and `screenshot` don't use
the remote browser connection. Your Playwright package must also support the Node.js
version provided by Slic.
