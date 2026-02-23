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

## Tips

- Always include the closing `?>` tag in `--FILE--` sections.
- Prefer `--EXPECTF--` over `--EXPECT--` for output containing paths, versions, or timestamps.
- Use `--SKIPIF--` to guard tests that require Docker or other external dependencies.
- Each `.phpt` file runs in a separate PHP process, providing full isolation.
- `%s` does not match newlines; use `%a` for multi-line wildcards.
