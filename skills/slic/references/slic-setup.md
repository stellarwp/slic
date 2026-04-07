# slic Setup and Configuration

This document covers installing slic, configuring projects for testing, environment file management, and CI usage.

## Installation

### Prerequisites

- **Docker** — the only hard requirement. Install and ensure the Docker daemon is running.
- **PHP 7.4+** — required on the host machine to run the slic CLI itself.

### Clone and add to PATH

```bash
# Clone the repository (example: into ~/projects).
git clone git@github.com:stellarwp/slic.git ~/projects/slic

# Add to PATH — bash:
echo 'export PATH="$HOME/projects/slic:$PATH"' >> ~/.bashrc
source ~/.bashrc

# Add to PATH — zsh:
echo 'export PATH="$HOME/projects/slic:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

Verify the installation:

```bash
slic info
slic help
```

## First-time workflow

### Step 1: Set the working directory

```bash
# Option A — point at a plugins directory:
cd /path/to/wp-content/plugins
slic here

# Option B — point at a WordPress root:
cd /path/to/wordpress
slic here
```

**How slic detects the mode:**

- If `wp-config.php` or a `wp/` subdirectory exists at the current path, slic enters **WordPress root mode**. It will look for plugins in `wp-content/plugins/` and themes in `wp-content/themes/` relative to the root.
- Otherwise, slic enters **plugins directory mode**. It treats the current directory as a plugins directory and uses its own internal WordPress installation.

Running `slic here reset` restores the default directories inside the slic installation.

### Step 2: Select a target

```bash
slic use my-plugin

# With a subdirectory (e.g., a package inside a plugin):
slic use event-tickets/common
```

This stores the target in `.env.slic.run` as the `SLIC_CURRENT_PROJECT` variable.

### Step 3: Initialize the target

```bash
slic init my-plugin
```

This generates three files in the plugin root:

#### `.env.testing.slic`

Contains environment variables for the slic Docker stack:

```env
WP_ROOT_FOLDER=/var/www/html
WP_URL=http://wordpress.test
WP_DOMAIN=wordpress.test
DB_HOST=db
DB_NAME=test
DB_PASSWORD=password
DB_PORT=3306
TEST_TABLE_PREFIX=wp_
CHROMEDRIVER_HOST=chrome
USING_CONTAINERS=1
```

slic generates this by reading your existing `.env.testing`, `.env`, or `.env.dist` file and replacing database/URL values with the Docker container values.

#### `codeception.slic.yml`

A minimal Codeception configuration that loads the slic env file:

```yaml
params:
  - .env.testing.slic
```

This is loaded via the `-c` flag on top of the project's existing `codeception.dist.yml` or `codeception.yml`.

#### `test-config.slic.php` (optional)

Created only if custom WPLoader configuration is needed. Contains PHP defines or setup code loaded by the WPLoader module's `configFile` option.

### Step 4: Install dependencies

After `slic init`, slic prompts to run `composer install` and `npm install` inside the container:

```bash
slic composer install
slic npm install
```

### Step 5: Run tests

```bash
slic run wpunit
```

## Codeception configuration precedence

When `slic run` executes, Codeception loads configuration files in this order (later files override earlier ones):

1. **`codeception.dist.yml`** — the project's distributed configuration (committed to version control).
2. **`codeception.yml`** — local overrides (often gitignored).
3. **`codeception.slic.yml`** — slic-specific overrides, loaded via the `-c` flag. This is what `slic init` generates.

For backwards compatibility, if `codeception.slic.yml` does not exist but `codeception.tric.yml` does, slic uses the tric version instead.

The cascading behavior means your `codeception.dist.yml` defines the base configuration (suites, modules, paths), and `codeception.slic.yml` only overrides the parameters needed for the Docker environment (database host, WordPress URL, etc.).

## Environment file cascade

slic loads environment files in this order (later files override earlier ones):

| Order | File | Location | Purpose |
|-------|------|----------|---------|
| 1 | `.env.slic` | slic repo root | Default configuration (version-controlled, do not edit) |
| 2 | `.env.slic.local` | slic repo root | Machine-specific overrides for all projects |
| 3 | `.env.slic.local` | Target plugin/theme root | Project-specific overrides |
| 4 | `.env.slic.run` | slic repo root | Runtime state (set by slic commands, auto-generated) |

### Key environment variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `SLIC_PHP_VERSION` | `7.4` | PHP version for containers |
| `SLIC_CURRENT_PROJECT` | (none) | Currently selected target |
| `MYSQL_ROOT_PASSWORD` | `password` | Database root password |
| `WORDPRESS_HTTP_PORT` | `8888` | WordPress HTTP port on localhost |
| `SLIC_GIT_HANDLE` | (none) | GitHub handle for cloning plugins |
| `XDEBUG_DISABLE` | `0` | Set to `1` to disable Xdebug extension |
| `SLIC_WP_DIR` | slic's `_wordpress/` | Path to WordPress installation |
| `SLIC_PLUGINS_DIR` | slic's `_plugins/` | Path to plugins directory |

## Container paths

Inside the Docker containers, paths are mapped as follows:

| Host path | Container path |
|-----------|---------------|
| WordPress root | `/var/www/html` |
| Plugins directory | `/var/www/html/wp-content/plugins` |
| Themes directory | `/var/www/html/wp-content/themes` |
| MU-plugins directory | `/var/www/html/wp-content/mu-plugins` |

## Database connection

Tests connect to the database with these defaults:

| Setting | Value |
|---------|-------|
| Host | `db` |
| Port | `3306` |
| Database name | `test` |
| User | `root` |
| Password | `password` (from `MYSQL_ROOT_PASSWORD`) |
| Table prefix | `wp_` |

These values are set in `.env.testing.slic` and injected into Codeception via the `params` key in `codeception.slic.yml`.

## Project defaults with `slic.json`

A `slic.json` file in the project root lets you define default settings that apply when `slic use` targets the project:

```json
{
    "phpVersion": "8.1"
}
```

When `slic use my-plugin` runs and finds this file, it automatically switches the PHP version to 8.1 (prompting for a stack rebuild if needed).

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `phpVersion` | string | PHP version to use (e.g., `"8.1"`, `"8.4"`) |

## CI configuration

### Disabling interactivity

slic prompts for confirmations in interactive mode. For CI, disable this:

```bash
slic interactive off
```

Or set the environment variable:

```bash
export SLIC_INTERACTIVE=0
```

### GitHub Actions example

This example assumes the project already has `codeception.slic.yml` and `.env.testing.slic` committed (generated by `slic init` during local development). CI only needs to install dependencies and run tests.

```yaml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.1', '8.4']

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}

      - name: Clone slic
        run: git clone https://github.com/stellarwp/slic.git ~/slic

      - name: Add slic to PATH
        run: echo "$HOME/slic" >> $GITHUB_PATH

      - name: Set up slic
        run: |
          slic here
          slic interactive off
          slic php-version set ${{ matrix.php }} --skip-rebuild
          slic use ${{ github.event.repository.name }}
          slic start

      - name: Install dependencies
        run: slic composer install

      - name: Run tests
        run: slic run wpunit
```

### Composer authentication in CI

For private packages, set the `COMPOSER_AUTH` environment variable:

```yaml
env:
  COMPOSER_AUTH: '{"github-oauth": {"github.com": "${{ secrets.GH_BOT_TOKEN }}"}}'
```

## Database dumps (`dump.sql`)

Some test suites (particularly acceptance tests) use a `dump.sql` file to initialize the database with a known state before tests run. This file is typically located at `tests/_data/dump.sql`.

### How it works

The WPDb module (used by acceptance/functional suites) imports `dump.sql` before each test to reset the database to a clean baseline state. The WPLoader module (used by wpunit suites) does not use `dump.sql` — it bootstraps WordPress directly.

### Best practices

1. **Keep the dump minimal** — it should contain only the bare WordPress installation data (default tables, default options, admin user). Do not pre-seed test-specific data in the dump.

2. **Tests should create their own data** — each test is responsible for arranging the data it needs using factories (`$this->factory()->post->create()`) or direct API calls. This makes tests self-contained and easy to understand.

3. **Never commit data that tests rely on** — if a test needs a specific post, user, or option, create it in `setUp()` or in the test method itself. Data baked into `dump.sql` creates hidden dependencies that break when the dump is regenerated.

4. **Regenerate with `slic update-dump`** — when your WordPress version changes or you need a fresh baseline:
   ```bash
   slic update-dump
   ```
   This imports the current dump, runs WordPress upgrades, and re-exports a clean version.

5. **Watch for collation issues** — when upgrading MySQL/MariaDB versions, you may need to update collations in the dump file. See the slic [Update Guide](https://github.com/stellarwp/slic#from-10-to-20) for details.

## Updating slic

```bash
slic update     # updates slic and Docker images
slic upgrade    # git pulls the latest slic code
```
