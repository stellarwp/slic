# tric

The tric (Modern **Tri**be **C**ontainers) CLI command provides a containerized and consistent environment for running automated tests.

## Installation

1. Clone this repo
2. Follow the [Setup Instructions](docs/setup.md)

## Usage

The `tric` command has many subcommands. You can discover what those are by typing `tric` or `tric help`. If you want
more details on any of the subcommands, simply type: `tric [subcommand] help`.

### Telling `tric` Where to Look

The `tric` command needs a place to look for plugins, themes, and WordPress. By default, `tric` creates a `_plugins` and
`_wordpress` directory within the local checkout of `tric`. In most cases, developers like to run automated tests
against the paths where they are actively working on code–which likely lives elsewhere.

Good news! You can use the `tric here` sub-command to re-point `tric`'s paths so it looks in the places you wish. There
are two locations you can tell `tric` to look.

#### WordPress Directory

The first option is to navigate to the root of your site (likely where `wp-config.php` lives) and run the `tric here`
command.

```bash
# Change to your root directory of your site (where your wp-config.php file lives)
cd /path/to/your/site

tric here
```

By running `tric here` at the site level, this allows you to set plugins, themes, or the site itself as the location
from which to run tests. This also has the benefit of running tests within the WP version that your site uses.

##### Some Notes

Note: This is a somewhat opinionated option as there are some assumptions that are made:

1. That the WordPress directory _is_ the path you are indicating or in a sub-directory called `wp/`.
2. That the `wp-content/` (or `content/`) directory is a sub-directory of the location in which you are typing `tric here`.

#### Plugins Directory

If you want to defer all of the WP site configuration to a dynamically pulled codebase and _just_ worry about testing
plugins, you can run the `tric here` command right from the plugins directory. Doing so will restrict `tric` to running
tests on plugins _only_ and ignore themes and site-level tests.

```bash
# Change to your plugin containing dir (likely some path to wp-content/plugins)
cd /path/to/your/wp-content/plugins

tric here
```

![tric here](docs/images/tric-here.gif)

### Initializing a Plugin

With your desired plugin containing directory set, you will need to initialize plugins so that they are prepped and ready
for `tric`-based automated test running. You do that using `tric init [plugin]`.

Example:

```bash
tric init event-tickets
```

![tric init](docs/images/tric-init.gif)

What this command does:

1. The plugin is **cloned** if it does not already exist in the plugin directory.
2. Generates a `.env.testing.tric` env file in the plugin.
3. Generates a `test-config.tric.php` file in the plugin.
4. Generates a `codeception.tric.yml` file in the plugin.
5. Prompts for confirmation on running `composer` and `npm` installs on the plugin (and its common dir if present).

### Using a Plugin and Running Tests

Ok. You have `tric` set up. You've initialized your plugins. Now you want to run some tests. You need to tell `tric` which
plugin you wish to use and then you can run tests to your heart's content!

```bash
tric use event-tickets
tric run wpunit
```

#### `tric use [plugin]`

The `tric use [plugin]` sub-command sets which plugin codeception will point to for test running. If you don't pass a
target, the current working directory will be tried.

If you are unsure which plugins are available for use, you can execute `tric info`.

If you don't remember which plugin you are currently using, you can run `tric using`.

There are a few flavors of `tric use`:

* `tric use` – Attempts to set the current working directory as the current `tric` target (codeception, composer, npm, etc commands will run against that plugin).
* `tric use [plugin]` – Sets a specific plugin (regardless of the current working directory) as the current `tric` target.
* `tric use [plugin]/common` – Sets a plugin as the current `tric` target to the `common/` directory of the plugin.
* `tric using` – Tells you which plugin you are currently "using" (i.e. the last plugin on which you ran `tric use [plugin]`).

**NOTE: you cannot `tric use [plugin]` on multiple plugins at once. The `tric` command relies on its `.env.tric.run` file
to dictate which plugin it is pointing at.**

#### `tric run [testsuite]`

The `tric run [testsuite]` does precisely what you would expect. It runs the test suite against the plugin that is currently
being targeted by `tric use [plugin]`. This command is essentially a `codecept run` command, so you can pass all of the
typical Codeception arguments for `codecept run`.

### Killing Tests or Stopping `tric`

If you find yourself wanting to bring down the containers–whether to save on resources, bail out of tests, etc–you can
do so with the `tric down` sub-command. Running `tric-down` will shut down the containers regardless of what is being
run with them.

_Note: you may need to open a new shell window to run that command if another `tric` command is in progress._

### Other commands worth knowing

Honestly, all of them are worth knowing. But here are a few important ones worth remembering:

* `tric cli` – run WP CLI commands within the container stack.
* `tric composer` – run composer commands against the current plugin target.
* `tric debug` – activates/deactivates debug output.
* `tric info` – displays current `tric` environment settings.
* `tric npm` – run npm commands against the current plugin target.
* `tric shell` – drop into bash in the containerized environment.
* `tric target` – run the same command against multiple targets. ![Example](docs/images/tric-target-example.png "tric target example")
* `tric xdebug [status|on|off]` – shows/sets xdebug status and info.
