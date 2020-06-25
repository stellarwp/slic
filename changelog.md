# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.1] - 2020-06-25

### Changed

- Add support for `DB_` prefixed database credentials and name in the original `.env.*` files.

## [0.4.0] - 2020-06-25
### Added

- Added `tric upgrade` which allows you to upgrade `tric` to the latest tagged release.

## [0.3.0] - 2020-06-25
### Added

- Prompt to `tric update` when container build version are out of sync from the tric version.
- Output npm error log when one is generated.

### Changed

- Adjust pathing of subdirectories within the tric stack so that npm can find a `.git` directory when performing `npm install`.
- Suppress the `fixuid` command output in the npm `docker-entrypoint.sh`.
- Separated out poolable (passive) command functions from realtime command functions to prevent issues with interactivity.

## [0.2.0] - 2020-06-24
### Added

- Added phpcs and phpcbf commands.
- Added parallel processing of commands.

### Changed

- Changed `tric build` to `tric build-stack`.

## [0.1.1] - 2020-05-26
### Changed

- Ensure `.htaccess` file is present in `_wordpress`.

## [0.1.0] - 2020-05-25
### Added

- Initial version
