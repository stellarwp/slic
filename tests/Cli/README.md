# CLI Tests

These tests treat the `slic` CLI application as a black box. They control only:

- Working directory
- Files on disk
- Environment variables
- Command-line options and arguments

Tests must assert on either the CLI output (stdout/stderr, exit code) or its side-effects (created files, modified state).

## Purpose

These tests provide a **refactoring umbrella**: they validate the CLI's external behavior without any knowledge of specific files, classes, or functions in the implementation. Internal restructuring should not require changes to these tests as long as the CLI's behavior remains the same.

## Framework

The tests use PHPUnit for familiarity, but they are **CLI end-to-end tests**, not unit tests. Each test exercises the `slic` command as a user would.
