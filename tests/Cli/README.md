# CLI Tests (.phpt)

This directory contains `.phpt` tests for the slic CLI application, run by PHPUnit.

## Running Tests

From this directory:

```bash
phpunit
```

Or from the project root:

```bash
phpunit -c tests/cli/phpunit.xml
```

## .phpt File Format

Each `.phpt` file is a self-contained test case made of named sections delimited by `--SECTION_NAME--` markers.

### Required Sections

| Section | Description |
|---------|-------------|
| `--TEST--` | Single-line test title. Must be the first section. |
| `--FILE--` | PHP code to execute (wrap in `<?php ... ?>`). |
| One of the `--EXPECT*--` variants below. | |

### Expectation Sections (exactly one required)

| Section | Description |
|---------|-------------|
| `--EXPECT--` | Exact string match against stdout. |
| `--EXPECTF--` | Format-string match with specifiers (see below). |
| `--EXPECTREGEX--` | Full regular expression match. |
| `--EXPECT_EXTERNAL--` | Load expected output from an external file. |
| `--EXPECTF_EXTERNAL--` | External file with format specifiers. |
| `--EXPECTREGEX_EXTERNAL--` | External file with regex. |

### `--EXPECTF--` Format Specifiers

| Specifier | Matches |
|-----------|---------|
| `%s` | One or more characters (not newlines). |
| `%d` | Unsigned integer. |
| `%i` | Signed integer. |
| `%f` | Floating-point number. |
| `%c` | Single character. |
| `%x` | Hexadecimal characters (0-9, a-f, A-F). |
| `%e` | Directory separator (`/` or `\`). |
| `%w` | Zero or more whitespace characters. |
| `%a` | One or more of anything (including newlines). |
| `%A` | Zero or more of anything (including newlines). |
| `%r...%r` | Inline regex between `%r` delimiters. |

### CLI-Relevant Optional Sections

| Section | Description |
|---------|-------------|
| `--ARGS--` | Arguments passed to PHP, available in `$argv`. |
| `--STDIN--` | Data fed to `php://stdin`. |
| `--ENV--` | Environment variables, one `KEY=VALUE` per line. |
| `--INI--` | PHP ini settings, one `key=value` per line. Supports `{PWD}` and `{TMP}` placeholders. |

### Conditional Sections

| Section | Description |
|---------|-------------|
| `--SKIPIF--` | PHP code; if output starts with `skip`, the test is skipped. |
| `--XFAIL--` | Marks the test as expected to fail (provide a reason string). |

### Other Sections

| Section | Description |
|---------|-------------|
| `--CLEAN--` | PHP code to run after the test for cleanup (runs in a separate process). |
| `--DESCRIPTION--` | Extended multi-line description (informational only). |
| `--FILEEOF--` | Like `--FILE--` but trailing newline is stripped. |
| `--FILE_EXTERNAL--` | Load test code from an external file. |

### Sections NOT Supported by PHPUnit

`REDIRECTTEST`, `REQUEST`, `POST`, `PUT`, `POST_RAW`, `GZIP_POST`, `DEFLATE_POST`, `GET`, `COOKIE`, `HEADERS`, `CGI`, `EXPECTHEADERS`, `EXTENSIONS`, `PHPDBG`.

## Example

```phpt
--TEST--
slic prints version information
--FILE--
<?php
$output = shell_exec('slic version 2>&1');
echo $output;
?>
--EXPECTF--
%s3.0.0%s
```

## slic Environment Variables

The following environment variables can be used to control slic behavior. In `.phpt` tests, set them
via the `--ENV--` section.

### Binary Overrides

| Variable | Default | Description |
|----------|---------|-------------|
| `SLIC_DOCKER_BIN` | `docker` | Path to the `docker` binary. |
| `SLIC_DOCKER_COMPOSE_BIN` | `<SLIC_DOCKER_BIN> compose` | Path to the `docker compose` binary. Defaults to `docker compose` derived from `SLIC_DOCKER_BIN`. |

### Output Control

| Variable | Default | Description |
|----------|---------|-------------|
| `NO_COLOR` | _(unset)_ | When set (any value), disables ANSI color codes and the ASCII art banner. See [no-color.org](https://no-color.org/). |

### Stack and Project Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `SLIC_PHP_VERSION` | `7.4` | PHP version used in the stack containers. |
| `SLIC_COMPOSER_VERSION` | _(auto)_ | Composer version to use in the stack. |
| `SLIC_PLUGINS_DIR` | `./_plugins` | Path to the plugins directory. |
| `SLIC_THEMES_DIR` | `./_wordpress/wp-content/themes` | Path to the themes directory. |
| `SLIC_WP_DIR` | `./_wordpress` | Path to the WordPress installation. |
| `SLIC_HERE_DIR` | _(unset)_ | Path set by the `here` command for the current working directory. |
| `SLIC_CURRENT_PROJECT` | _(unset)_ | The currently active project (set via `use`). |
| `SLIC_CURRENT_PROJECT_SUBDIR` | _(unset)_ | Subdirectory within the current project. |
| `SLIC_STACK` | _(unset)_ | Path to a specific stack to target. |
| `SLIC_SCRIPTS` | `./containers/scripts` | Path to container scripts. |

### Runtime Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `SLIC_INTERACTIVE` | `1` | Whether slic runs in interactive mode. |
| `SLIC_BUILD_PROMPT` | `1` | Whether to prompt before builds. |
| `SLIC_BUILD_SUBDIR` | `1` | Whether to build subdirectories. |
| `SLIC_UID` | _(auto)_ | UID used for container user mapping. |
| `SLIC_GID` | _(auto)_ | GID used for container user mapping. |
| `SLIC_HOST` | _(auto)_ | Host IP address for container networking. |
| `SLIC_TEST_SUBNET` | `28` | Subnet used for test networking. |
| `SLIC_DB_LOCALHOST_PORT` | `9906` | Localhost port for the database service. |

### WordPress Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `SLIC_WP_HTTP_BLOCK_EXTERNAL` | `true` | Block external HTTP requests in WordPress. |
| `SLIC_DISABLE_WP_CRON` | `true` | Disable WP-Cron in the test environment. |
| `SLIC_WP_AUTO_UPDATE_CORE` | `false` | Control WordPress core auto-updates. |
| `SLIC_AUTOMATIC_UPDATER_DISABLED` | `true` | Disable the WordPress automatic updater. |

## Tips

- Always include the closing `?>` tag in `--FILE--` sections.
- Prefer `--EXPECTF--` over `--EXPECT--` for output containing paths, versions, or timestamps.
- Use `--SKIPIF--` to guard tests that require Docker or other external dependencies.
- Each `.phpt` file runs in a separate PHP process, providing full isolation.
- `%s` does not match newlines; use `%a` for multi-line wildcards.
