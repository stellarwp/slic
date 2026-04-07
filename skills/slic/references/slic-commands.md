# slic CLI Command Reference

Complete reference for all slic commands. Run `slic help` to see the list, or `slic <command> help` for detailed usage of any command.

## Project selection

### `slic here`

Sets the directory where slic looks for plugins, themes, and WordPress.

```bash
# From a plugins directory (e.g. wp-content/plugins):
cd /path/to/wp-content/plugins
slic here

# From a WordPress root (where wp-config.php lives):
cd /path/to/wordpress
slic here

# Reset to slic's default directories:
slic here reset
```

**Two modes:**

- **Plugins directory mode** — run from a directory containing plugin folders. slic will test plugins found here.
- **WordPress root mode** — run from a WordPress installation root. slic can test plugins, themes, or the site itself. It detects WordPress by looking for `wp-config.php` or a `wp/` subdirectory.

### `slic use <target>`

Selects which plugin, theme, or site to run tests against.

```bash
slic use the-events-calendar
slic use event-tickets/common        # subdirectory target
slic use starter-theme               # theme target (if slic here pointed at wp root)
```

The target must exist in the directory set by `slic here`. Use `slic using` to see the current target.

### `slic using`

Displays the currently selected target.

```bash
slic using
# Output: Using the-events-calendar
```

### `slic init <plugin> [<branch>]`

Initializes a plugin for slic-based testing. Creates configuration files and optionally runs dependency installs.

```bash
slic init the-events-calendar
slic init event-tickets release/B20.04    # checkout a specific branch
```

**Generated files:**

| File | Purpose |
|------|---------|
| `.env.testing.slic` | Database credentials, WordPress URL, container paths |
| `codeception.slic.yml` | Loads `.env.testing.slic` as Codeception params |
| `test-config.slic.php` | Optional WPLoader custom config (only created if needed) |

After generating files, slic prompts to run `composer install` and `npm install`.

## Testing

### `slic run [suite] [file_path_no_ext]::[method]`

Runs Codeception tests. Wraps `vendor/bin/codecept run`.

```bash
slic run                                  # all suites, sequentially
slic run wpunit                           # one suite
slic run tests/wpunit/FooTest.php         # one file
slic run tests/wpunit/FooTest::test_bar    # one method
slic run wpunit -- --debug                # pass flags to codecept
```

**Codeception config precedence** (cascading, later overrides earlier):

1. `codeception.dist.yml` (if exists)
2. `codeception.yml` (if exists)
3. `codeception.slic.yml` (if exists, loaded via `-c`)
4. `codeception.tric.yml` (backwards compatibility, only if no `.slic.yml`)

### `slic cc <command>`

Runs any Codeception command (not just `run`). Wraps `vendor/bin/codecept`.

```bash
slic cc generate:wpunit wpunit "FooTest"
slic cc generate:wpunit wpunit "Admin/SettingsTest"
slic cc run wpunit --coverage
slic cc clean
slic cc clean
slic cc build
```

### `slic shell [<service>]`

Opens an interactive shell in a container. Default service is `slic` (the test runner).

```bash
slic shell                # slic container (Codeception runner)
slic shell wordpress      # WordPress container
slic shell db             # Database container
```

Inside the slic shell, shorthand commands are available:

| Shell command | Equivalent to |
|--------------|---------------|
| `cr <suite>` | `codecept run <suite>` |
| `xon` | Enable Xdebug (immediate, no restart) |
| `xoff` | Disable Xdebug (immediate, no restart) |

**Alias:** `slic ssh` is equivalent to `slic shell`.

### `slic playwright <command>`

Runs Playwright commands in the stack for browser-based testing.

> **Note:** Available since slic 2.x. Requires Playwright to be installed in the target project (`slic playwright install`).

```bash
slic playwright install                   # install Playwright + Chromium
slic playwright test                      # run all Playwright tests
slic playwright test tests/e2e/my-test.spec.ts  # run a specific test file
```

## Stack management

### `slic start` / `slic up`

Starts the Docker containers (db, redis, wordpress, chrome, slic).

```bash
slic start
slic up          # alias
```

### `slic stop` / `slic down`

Stops and removes the Docker containers.

```bash
slic stop
slic down        # alias
```

### `slic restart`

Restarts the stack containers.

```bash
slic restart
```

### `slic ps`

Lists running containers in the slic stack.

```bash
slic ps
```

### `slic logs`

Displays container logs.

```bash
slic logs
```

### `slic build-stack`

Rebuilds Docker images for the stack.

```bash
slic build-stack
slic build-stack slic        # rebuild specific service
```

## PHP version management

### `slic php-version`

Shows or sets the PHP version used by the stack.

```bash
slic php-version                     # show current version
slic php-version set 8.1             # set PHP 8.1 (prompts to rebuild)
slic php-version set 8.4 --skip-rebuild   # stage for next `slic use`
slic php-version reset               # reset to default (7.4)
```

**Version auto-detection priority:**

1. `SLIC_PHP_VERSION` environment variable override
2. Staged version (from `--skip-rebuild`)
3. Project's `.env.slic.local` file
4. `slic.json` → `phpVersion` field
5. `composer.json` → `config.platform.php` field

## Development tools

### `slic composer <command>`

Runs Composer inside the container.

```bash
slic composer install
slic composer update
slic composer require vendor/package
slic composer set-version 2          # switch to Composer v2
slic composer get-version            # show Composer version
```

### `slic composer-cache`

Sets or shows the composer cache directory mapping.

```bash
slic composer-cache set $HOME/.cache/composer
slic composer-cache show
```

### `slic npm <command>`

Runs npm inside the container, using the Node version from `.nvmrc`.

```bash
slic npm install
slic npm run build
```

### `slic wp <command>` / `slic cli <command>`

Runs wp-cli commands in the WordPress container.

```bash
slic wp plugin list
slic wp option get blogname
slic wp user create testuser test@example.com --role=editor
```

`slic wp` is an alias for `slic cli`.

### `slic phpcs` / `slic phpcbf`

Runs PHP_CodeSniffer or Code Beautifier and Fixer on the current target.

```bash
slic phpcs
slic phpcbf
```

### `slic mysql`

Opens a MySQL shell connected to the test database.

```bash
slic mysql
```

### `slic exec <command>`

Runs an arbitrary bash command in the slic container.

```bash
slic exec "php -v"
slic exec "ls -la /var/www/html"
```

## Debug and configuration

### `slic xdebug`

Manages Xdebug in the stack.

```bash
slic xdebug on              # enable (requires restart)
slic xdebug off             # disable (requires restart)
slic xdebug status          # show current state
slic xdebug host <ip>       # set host IP for IDE connection
slic xdebug port <port>     # set port (default 9001)
slic xdebug key <key>       # set IDE key (default slic)
```

### `slic debug`

Toggles debug output for slic commands.

```bash
slic debug on
slic debug off
slic debug status
```

### `slic interactive`

Toggles interactive mode. Disable for CI environments.

```bash
slic interactive on
slic interactive off
slic interactive status
```

### `slic cache`

Toggles WordPress object cache support.

```bash
slic cache on
slic cache off
slic cache status
```

### `slic airplane-mode`

Toggles the airplane-mode plugin to block external HTTP requests from WordPress.

```bash
slic airplane-mode on
slic airplane-mode off
```

### `slic config`

Prints the Docker Compose configuration with interpolated environment variables.

```bash
slic config
```

### `slic info`

Displays information about the slic installation.

```bash
slic info
```

## Utilities

### `slic target <commands...>`

Runs commands against multiple targets.

```bash
slic target run wpunit
```

### `slic group`

Creates or removes groups of targets for batch operations.

```bash
slic group
```

### `slic update`

Updates slic and its Docker images.

```bash
slic update
```

### `slic upgrade`

Upgrades the slic repository itself (git pull).

```bash
slic upgrade
```

### `slic update-dump`

Updates a SQL dump file for acceptance testing by importing, upgrading, and re-exporting.

```bash
slic update-dump
```

### `slic reset`

Resets slic to its initial state as configured by the env files.

```bash
slic reset
```

### `slic host-ip`

Returns the IP address of the host machine from the container's perspective.

```bash
slic host-ip
```

### `slic dc <command>`

Runs a raw `docker compose` command in the slic stack context.

```bash
slic dc ps
slic dc exec slic bash
```
