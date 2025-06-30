# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

# [1.9.2] - 2025-05-14
- Restored the `selenium/standaolone-crome` image to version `3.141.59` from `4.27.0-20241225` to ensure compatibility with older PHP driver versions.

# [1.9.1] - 2025-04-15
- Fixed - Better detection of ARM64 architecture in `is_arm64` function.

# [1.9.0] - 2025-01-20
- Added - Allow for projects to declare their desired PHP version for slic via `slic.json` (`phpVersion`) or `composer.json` (`config.platform.php`).
- Added - Support for auto-setup of appropriate PHP containers when running `slic use`.

# [1.8.1] - 2024-12-30
- Updated the ARM64 Chrome container image to version 4.20.0-20240427 from 4.1.2-20220227.
- Updated the x86 Chrome container image to version 4.27.0-20241225 from 3.141.59.

# [1.8.0] - 2024-12-04
- Updated the `node` version to `18.17.0`, this affects the `npm` command and any other command using the `npm` container.

# [1.7.2] - 2024-08-30
* Fixed - Set the `opcache.revalidate_freq` to `0` in the `slic` and `wordpress` containers to avoid issues with cached files in tests.

# [1.7.1] - 2024-08-30
* Fixed - Run the `playwright install` command as root, allow running Playwright tests as the `slic` user.
* Change - The `playwright install` command will now install only the Chromium browser and its dependencies.

# [1.7.0] - 2024-06-06
* Fixed - Properly read changes and restart stack after using the `slic php-version set` command.
* Added - PHP 8.3 docker images.
* Change - Update GitHub action versions to remove node notices.
* Change - Build slic images from the latest PHP patch versions.

# [1.6.3] - 2024-05-10
* Added - The `playwright` command to Playwright commands in the stack.
* Added - The `less` binary to the `slic` container (to correctly format wp-cli output).
* Added - The `p` alias to the `slic` container to allow running Playwright commands with `p <command>`, similar to `c <command>` to run Codeception commands.

# [1.6.2] - 2024-05-10
* Added - The `slic` command now loads the `.env.slic.local` file from the target directory if it exists. (thanks @cliffordp)
* Added - The `chrome` container shm size is now set to `256m` by default, and is controllable via the `SLIC_CHROME_CONTAINER_SHM_SIZE` env var. (thanks @maurisrx)

# [1.6.1] - 2024-04-19
* Change - the `airplane-mode` command now installs the plugin in the must-use plugins directory instead of the plugins directory.
* Fixed - `.bat` file now uses the correct path to the `slic` executable on Windows.

# [1.6.0] - 2024-04-10
* Added - The `slic update-dump` command to update a dump file for the current project, with an optional WordPress version update, e.g. `slic update-dump tests/_data/dump.sql latest`.

# [1.5.4] - 2024-04-08
* Change - Disable WordPress's automatic updating in slic containers via docker compose `WORDPRESS_CONFIG_EXTRA` defines. See comments in `.env.slic` to customize this behavior.

# [1.5.3] - 2024-04-05
* Change - Build `linux/arm64` images for the `slic` and `wordpress` containers to avoid issues when running `slic` on ARM machines.

# [1.5.2] - 2024-04-04
* Change - Remove `version` property from docker compose .yml files as it's obsolete since v2.25. https://github.com/docker/compose/issues/11628

# [1.5.1] - 2024-01-05
* Change - Update the `npm` version to `18.13.0`
* Change - Update the `nvm` version to `v0.39.7`
* Fixed - Ensure `nvm` cache directory is world-accessible to allow use of `nvm install`

# [1.5.1] - 2023-11-20
* Change - Allow controlling the value of the `WP_HTTP_BLOCK_EXTERNAL` constant using the `SLIC_WP_HTTP_BLOCK_EXTERNAL` environment variable defined in the `.env.slic.run` configuration file; set to `true` by default to block all outgoing HTTP requests from WordPress.
* Change - Allow controlling  the value of the `DISABLE_WP_CRON` constant using the `SLIC_DISABLE_WP_CRON` environment variable defined in the `.env.slic.run` configuration file; set to `true` by default to disable the WordPress cron system.

# [1.5.0] - 2023-09-06
* Fix - Added `extra_hosts:"${host:-host}:host-gateway"` to slick-stack.yml for Linux compatibility, enabling Xdebug without modifying the XDH environment variable. [Ref](https://github.com/docker/for-linux/issues/264#issuecomment-785247571).

# [1.4.4] - 2023-08-15
* Change - Update base WordPress container from 6.1 to 6.2.
* Change - Add WP CLI to the `wordpress` container as `wp`.

# [1.4.3] - 2023-08-03

* Change - Configure `slic` and `wordpress` containers consistently with `php.ini` configuration file.
* Change - Set `xdebug.log_level=0` in `slic` and `wordpress` containers to avoid nags in separate process running.

## [1.4.2] - 2023-07-02

* Change - Enable xdebug coverage mode in all PHP based docker containers when xdebug is enabled via: `xdebug.mode=develop,debug,coverage`.

## [1.4.1] - 2023-06-21

* Change - Tag again to update WordPress version to 6.1 (the last WP version with a PHP 7.4 image).

## [1.4.0] - 2023-06-21

* Change - Update WordPress version to 6.1 (the last WP version with a PHP 7.4 image).

## [1.3.3] - 2023-04-04

* Fix - broken CRLF line breaks via slic real-time commands due to docker compose v2.2.x being used [Docker compose issue](https://github.com/docker/compose/issues/8833#issuecomment-953023240).

## [1.3.2] - 2023-04-03

* Updates slic-stack.site.yml for full site testing.
* Adds check for `site` target to reference `slic_wp_dir()` when running commands.

## [1.3.1] - 2023-03-31

* Change - Default to Composer version 2.

## [1.3.0] - 2023-03-30

* Change - Leverage Docker Compose syntax to manage network aliases and service dependencies. Clean up a remove code.
* Add the `dc` command to run Docker Compose commands in the stack.
* Support the `SLIC_DOCKER_COMPOSE_BIN` environment variable to allow for the use a different Docker Compose binary, the `docker compose` one is used by default.

## [1.2.4] - 2023-03-21

* Fix - [issue 142](https://github.com/stellarwp/slic/issues/142) ensure the hosts file in the docker container contains a tab character before trying to explode it.

## [1.2.3] - 2023-03-21

* Added - Pass through the [COMPOSER_AUTH](https://getcomposer.org/doc/03-cli.md#composer-auth) environment variable to the slic docker container to allow composer installs for private or otherwise protected dependencies. Especially useful when running in GitHub Actions.
* Added - The PHP exif extension to the slic container to allow `slic composer install` commands to succeed when this extension is required.

## [1.2.2] - 2023-02-24

* Change - No longer build gd in the slick docker container with AVIF, fixing external dependency on the currently down code.videolan.org, and speeding up builds - [more info](https://github.com/mlocati/docker-php-extension-installer#configuration).
* Change - use [`tmpfs`](https://docs.docker.com/storage/tmpfs/) for the `db` and `redis` services data directories to improve performance on Linux machines.

## [1.2.1] - 2023-02-22

* Changed - Add docker build caching using the [GitHub Cache Backend](https://docs.docker.com/build/ci/github-actions/examples/#github-cache) when publishing docker images via GitHub actions.

## [1.2.0] - 2023-02-21

* Change - Display composer version in `slic info`.
* Added - The `--skip-rebuild` option to the `slic php-version set` command to allow setting the PHP version without rebuilding the stack in order to speed up CI runs. Example: `slic php-version set 8.1 --skip-rebuild`
* Fix - `slic php-version help` will now properly show command help text.
* Fix - `slic php-version` will now properly use the default PHP version if the `SLIC_PHP_VERSION` env var is not set.

## [1.1.7] - 2023-02-21

* Change - The WordPress PHP7.3 image is only available in for WordPress 5.9.x.
* Change - Convert docker image builds into single matrix GitHub workflows.
* Added  - Add a PHP 8.2 slic/WordPress docker image build. **PHP8.2 is using WordPress version 6.1.1**.
* Change - Use docker build arguments when publishing docker containers.
* Change - Bump action docker/build-push-action@v3.1.1 to docker/build-push-action@v3.3.1
* Change - Bump action docker/metadata-action@v4.0.1 to docker/metadata-action@v4.3.0
* Change - Bump nvm version from v0.39.1 to v0.39.3 in slic docker image.

## [1.1.6] - 2023-01-03

* Fix - Modernize variable syntax to avoid PHP 8.2 deprecation notices.

## [1.1.5] - 2022-11-29

* Fix - Enable and disable XDebug correctly in WorPress and slic container removing restart requirement.
* Fix - Avoid issues with missing `/usr/sbin/sendmail` during WordPress installation. [#134]

## [1.1.4] - 2022-10-27

* Fix - Simplify the `restart_all_services()` code so that containers are all shut down and _then_ all started.

## [1.1.3] - 2022-10-10

* Fix - Add the `intl` PHP module to the container.

## [1.1.2] - 2022-09-07

* Fix - Ensure actions that need to execute after containers start actually wait until those containers start.

## [1.1.1] - 2022-09-06

- Feature - Added the `slic exec` command that allows bash command execution within the stack. [#31]
- Fix - Set the Composer cache directory default location correctly. [#78]
- Tweak - Add `SLIC_PHP_VERSION` to `slic info`.
- Tweak - Make the `Valid Targets:` output a bit less readable during `slic info` in favor of keeping the env values visible.

## [1.1.0] - 2022-09-05

- Feature - Prompt the user to stop containers before updating.
- Feature - Add the `slic php-version` command and allow for switching between PHP versioned containers.
- Fix - Allow `wordpress.test` to be resolvable during test execution by placing it in the `/etc/hosts` file of the `slic` and `chrome` containers.
- Fix - PHP 8.0+ compatibility fix for WordPress zip downloads.
- Fix - Prevent looping over the same test suite when executing `slic run`. [#118]
- Fix - Better handling of checking for services that are up.

## [1.0.5] - 2022-09-02

- Switch the `slic-stack.yml` to use the new Docker images for `slic` and `slic-wordpress`.

## [1.0.4] - 2022-09-02

- Generate Docker images for `slic` and `wordpress` containers.

## [1.0.3] - 2022.09-02

- Fixes for the run command when executed while the containers are not already running.
- Scaffold in a `/cache` directory to store test-related information.
- PHP 8 deprecation warning fix.

## [1.0.2] - 2022-09-02

- Ensure that the right flags are passed during docker-compose command execution when in non-interactive mode.

## [1.0.1] - 2022-09-01

- Big overhaul of documentation within the tool.
- Now saving the state of XDebug when running `slic shell` multiple times.
- Added XDebug 3 compatible `php.ini` settings.

## [1.0.0] - 2022-09-01

- Rebranded `tric` to `slic`.
- Consolidated many of the containers into a single `slic` container to simplify the stack and reduce the time to start up and shut down containers.
- Containers are no longer brought down upon completion of a command.
- Removed the `serve` command. Since containers remain up, the `up` command can be used to start the stack and make the local site available.
- Reworked how `slic` handles npm. It now uses `nvm` under the hood and automatically installs the version of node that is dictated by `.nvmrc`.
- Removed the `npm_lts` command. It is no longer needed after the reworking of the `npm` command.

## [0.6.0] - 2022-04-15

- Add the `ps` command to list the current containers information.
- Fix `mariadb` image to `10.7.3`.

## [0.5.35] - 2022-08-01

- Update the Composer container to use PHP 7.4.

## [0.5.34] - 2022-07-22

- Set `xdebug.log_level=0` in the stack configuration to avoid XDebug warnings from breaking code.

## [0.5.33] - 2022-04-15

- Add the `mysql` command to quickly open a `mysql` shell in the running database container of the `tric` stack.
- Add the `wp` command as alias of the `cli` command.

## [0.5.32] - 2022-03-18

- Set the version of the `lucatume/codeception` container to `cc3.1.0-v1.1.1`.

## [0.5.31] - 2022-03-14

- Version bump to pull the latest version of the fixed `codeception` container.

## [0.5.30] - 2022-03-10

- Use the `seleniarm/standalone-chromium` container for the `chrome` service on ARM architecture machines.
- Add the `pcntl` extension to the `codeception` container.

## [0.5.29] - 2021-11-29

- Add support for a Mailcatcher container (thanks @sc0ttkclark).

## [0.5.28] - 2021-10-06

- Updated the `node` version to `10.16.0`, this affects the `npm` command and any other command using the `npm` container.
- Added the `host-ip` command to get the host machine IP address containers will be able to use to connect to the host (e.g. when setting up XDebug on Linux).

## [0.5.27] - 2021-06-03

- Fixed an issue where the path to the WP directory would not build correctly on Windows systems.

## [0.5.26] - 2021-03-05

- Allow opening a shell in the `cli` service to run wp-cli commands just using `tric cli`.

## [0.5.25] - 2021-03-02

- Set `COMPOSER_HTACCESS_PROTECT=0` explicitly in the `docker-compose` configuration file to avoid an `.htaccess`
  file being created in the root directory of the current target.

## [0.5.24] - 2021-02-18

- Update the WordPress image version, in the `tric-stack.build.yml` file, from `5.5` to `5.6` to fix CI build issues.

## [0.5.23] - 2021-01-20

- Add an argument, to the `reset` command, to remove the default WordPress (`/_wordpress`) installation directory
  using `tric reset wp`.

## [0.5.22] - 2021-01-20

- Added the `composer-cache` command so the host machine's composer cache can be leveraged within tric containers.

## [0.5.21] - 2021-01-19

- Updated default WordPress image to `5.6-apache`.

## [0.5.20] - 2020-12-28

- Updated default repo org to `the-events-calendar`.

## [0.5.19] - 2020-12-11

- Fix an issue where commands that required a ready and available WordPress installation in the `tric` WordPress
  directory would not take care to scaffold and install it; e.g. `cli` or `site-cli`.

## [0.5.18] - 2020-12-07

- Fix an issue where the `USING_CONTAINERS` environment variable would be duplicated in environment files set up by
  `tric` init command.
- Fix an issue where a negative answer to build targets with sub-directories, e.g. TEC and ET, would result in the
  target being built anyway.

## [0.5.17] - 2020-12-04

- Fix the XDebug version and `wordpress` service `Dockerfile` to keep using version `2` and not update to version `3` on a rebuild (e.g. one triggered by `tric update` or `tric build-stack` commands).
- Add the `tric ssh` command to allow opening a shell in a **running** stack service; differently from the `shell` command, the `ssh` command will **not** start the service if it's not already running.

## [0.5.16] - 2020-11-26

- Add support for the `TRIC_HOST` environment variable. This will override the default host machine IP address lookup `tric` would perform on Linux or the hard-wired `host.docker.internal` hostname `tric` would use on Windows and Mac host to set the default `xdebug.remote_host` value.
- Default to the host machin IP address to set `xdebug.remote_host` only if the host has not been set  by means of a call to `tric xdebug host <host>` or by setting the `XDH` environment variable explicitly.
- Fix an issue that would reset run settings stored in the `.env.tric.run` file when using the `tric xdebug <key> <value>` command.
- Correctly handle Docker network removal in parallel tasks to avoid "error while removing network" errors.
- Add support for `tric run` to run all the available Codeception test suites from the target one after another.

## [0.5.15] - 2020-11-24

- Removed the 3s wait at start of the `codeception` service.
- Fixed (in the context of the [lucatume/dockerfiles](https://github.com/lucatume/dockerfiles) repository) an issue that would cause Codeception tests to exit `0` on failure and not `1` as expected.

## [0.5.14] - 2020-11-24

- Fixed volumes setup to make sure the volume, and the host file structure, created by WordPress container is owned by the current user and not `root`.
- Fixed and issue that would change, on Linux, the file modes of all the used plugins, to `a+rwx` when using the `run` command; fixes #36

## [0.5.13] - 2020-11-23

- Executing `tric use` without a target now attempts to set the current working directory as the target.
- `tric info` now outputs all valid targets, which is what `tric use` without a valid target used to do.
- The Composer prompt from `tric init` no longer appears if `composer.json` is not found, likewise for the NPM prompt if `package.json` is not found.
- Fixed an issue that would prevent the `npm` and `npm_lts` services from correctly returning their exit status.
- Add support for the `--pretty` flag to the `npm` and `npm_lts` commands to print a more human-readable output.

## [0.5.12] - 2020-11-11

- Fixed two issues in the `target` command where the command would fail if no previous target had been set.
- Set the start of the random network subnet pool for parallel processes used by the `target` command to a higher number to reduce the chance of running into overlapping pool issues.

## [0.5.11] - 2020-11-03

- Activate all debug options in the `wordpress` service.
- Use a custom WordPress image for the stack, based on the default `wordpress` one, but modified to support and use XDebug.

## [0.5.10] - 2020-10-14

- Fixed a smaller issue in the `target` command.

## [0.5.9] - 2020-10-07

- Add the `site-cli` command to start a wp-cli container on a running and ready WordPress stack.

## [0.5.8] - 2020-10-06

- Add a check to ensure a target is set for commands that require it.

## [0.5.7] - 2020-08-28

- Add the `npm_lts` service and service to the stack to run `npm` commands on the current LTS version of node.

## [0.5.6] - 2020-08-27

- Fixed an issue where the terminal columns and lines detection would cause issue in CI context.
- Fixed an issue where the `npm` image would not build correctly in CI context.

## [0.5.5] - 2020-08-19

- Add support for the `bash` sub-command to the `tric cli` command to allow opening a `bash` shell into the `cli` container to manage the WordPress installation currently being served by `tric serve`.


## [0.5.4] - 2020-08-18

- Add support for multiple commands in the `target` command to allow running a set of commands on a set of targets.

## [0.5.3] - 2020-08-13

- Update the WordPress version used in the stack to `5.5`.
- Fix handling of default answers in prompts.

## [0.5.2] - 2020-08-10

- Fix an issue where `docker-compose` would display an error due to missing default value for the `TRIC_CURRENT_PROJECT_SUBDIR` env var.

## [0.5.1] - 2020-08-04

- Fix an issue where the `function-mocker-cache` volume would be mounted with `root` ownership on Linux systems causing Function Mocker to fail while trying to set up cache in plugins that use it.

## [0.5.0] - 2020-07-30

- Added the `tric target` command to support running the same command against multiple targets.

## [0.4.9] - 2020-07-07

- Fix an issue where the `tric init` command would not correctly pick up the existing environment files if not running from the plugin directory.
- Added the `TRIC_INTERACTIVE`, `TRIC_BUILD_PROMPT` and `TRIC_BUILD_SUBDIR` env var to the `tric info` report.

## [0.4.8] - 2020-07-02

- Added `tric build-subdir` which allows you to control whether sub-directories (e.g. `common` in The Events Calendar) should be built during composer/npm commands or not.
- Fix an issue where the `build-prompt` status was reported incorrectly.

## [0.4.7] - 2020-07-01

- Fix an issue with file modes on the WordPress container that would make its `wp-content`  directory `root` owned on Linux hosts.

## [0.4.6] - 2020-06-30

- Fix parallel processing closure argument requirements.

## [0.4.5] - 2020-06-30

- Allow for additional git upstreams other than GitHub.

## [0.4.4] - 2020-06-29

- Fix and re-enable the parallel processing functionality.

## [0.4.3] - 2020-06-26

- Temporarily disabled the parallel processing functionality.

## [0.4.2] - 2020-06-26

- When running parallel processes, use random subnets to avoid container collision.

## [0.4.1] - 2020-06-25

- Add support for `DB_` prefixed database credentials and name in the original `.env.*` files.

## [0.4.0] - 2020-06-25

- Added `tric upgrade` which allows you to upgrade `tric` to the latest tagged release.

## [0.3.0] - 2020-06-25

- Prompt to `tric update` when container build version are out of sync from the tric version.
- Output npm error log when one is generated.
- Adjust pathing of subdirectories within the tric stack so that npm can find a `.git` directory when performing `npm install`.
- Suppress the `fixuid` command output in the npm `docker-entrypoint.sh`.
- Separated out poolable (passive) command functions from realtime command functions to prevent issues with interactivity.

## [0.2.0] - 2020-06-24

- Added phpcs and phpcbf commands.
- Added parallel processing of commands.
- Changed `tric build` to `tric build-stack`.

## [0.1.1] - 2020-05-26

- Ensure `.htaccess` file is present in `_wordpress`.

## [0.1.0] - 2020-05-25

- Initial version
