# How to contribute

We happily review/accept third-party Pull Requests. To help get your patches adopted into our plugins, there's a few bits of info that are worth knowing.

## Prerequisites

* Docker
* PHP 7.4+ at the system level

## Build steps

Wooo! There aren't any! We do suggest adding `slic` to your `$PATH`, though. To do that, place the following in your `.bashrc` or `.zshrc` (or whatever) file:

```bash
export PATH=$PATH:path/to/slic
```

## Testing

We use [PHPUnit](https://phpunit.de/) for testing, but require [the `uopz` extension]() to run the tests.  
The testing environment for slic is Docker-based, so you'll need to have Docker installed and running.
Furthermore, `slic` tests use [PhpUnit](https://phpunit.de/ "PHPUnit – The PHP Testing Framework") and [Composer](https://getcomposer.org/).

Build the testing environment locally using the `make build` command.

Run the tests using the `make test` command.

```bash

## Releases

When we prep a release, we follow these steps:

1. Create a branch with the version number of the release, e.g. `1.0.0`
2. Merge changes into that branch. This will trigger the Docker images to build.
3. Ensure that the `CLI_VERSION` has been updated in `slic.php` to reflect the version number of the release.
4. Ensure that the `changelog.md` file has been updated.
5. Merge the release branch into `main`.
6. Delete the release branch.
7. Tag the release with the changelog.md contents for that release. This will trigger the final Docker images to build.
