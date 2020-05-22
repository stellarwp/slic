# tric

The tric (Modern **Tri**be **C**ontainers) CLI command provides a containerized and consistent environment for running automated tests.

## Installation

1. Clone this repo
2. Follow the [Setup Instructions](docs/setup.md)

## Usage

The `tric` command has many subcommands. You can discover what those are by typing `tric` or `tric help`. If you want
more details on any of the subcommands, simply type: `tric [subcommand] help`.

### Plugin Directory

The `tric` command needs a plugin directory in which to read/write. By default, `tric` creates a `_plugins` directory
within this cloned repo. In most cases, developers like to run automated tests against the plugin paths where they are
actively working on code–which likely lives elsewhere.

Good news! You can re-point the plugin directory that is used to a different path with the `tric here` sub-command.

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

The `tric use [plugin]` sub-command sets which plugin codeception will point to for test running. If you are unsure what
plugins are available for use, you can execute `tribe use`. If you don't remember which plugin you are currently using,
you can run `tribe using`. There are a few flavors of `tribe use`:

* `tribe use [plugin]` – Sets a plugin as the current `tric` target (codeception, composer, npm, etc commands will run against that plugin).
* `tribe use [plugin]/common` – Sets a plugin as the current `tric` target to the common/ directory of the plugin.
* `tric use` – Lists out the plugins in `tric`'s plugin path.
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
* `tric xdebug [status|on|off]` – shows/sets xdebug status and info.
