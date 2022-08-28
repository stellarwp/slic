# slic

The slic (**S**tellarWP **L**ocal **I**nteractive **C**ontainers) CLI command provides a containerized and consistent environment for running automated tests.

## Table of Contents

* [Getting started](#getting-started)
	* [Why use `slic`?](#why-use-slic)
	* [Why use Codeception?](#why-use-codeception)
	* [Requirements](#requirements)
	* [Installation](#installation)
	* [The most important command to know](#the-most-important-command-to-know)
* [Using `slic`](#using-slic)
	* [Tell `slic` how to find your project](#tell-slic-how-to-find-your-project)
	* [Preparing your project](#preparing-your-project)
	* [Adding tests](#adding-tests)
	* [Running tests](#running-tests)
* [Advanced topics](#advanced-topics)
	* [Making composer installs faster](#making-composer-installs-faster)
	* [Customizing `slic`'s `.env` file](#customizing-slics-env-file)
	* [Xdebug and `slic`](#xdebug-and-slic)


## Gettings started

### Why use `slic`?

One of the biggest stumbling blocks that engineers face when getting automated testing going for their projects is the complexity of setting up a testing environment. And as your team size grows, the struggles with consistent test running increase.

**`slic` automatically configures a Codeception testing environment so you don't have to.**

Plus, it provides a lot of handy development tools and utilities that you can use while developing your project _or_ during Continuous Integration builds!

### Why use Codeception?

[Codeception](https://codeception.com/) is a PHP testing framework that uses [PHPUnit](https://phpunit.de/) under the hood, adding all sorts of extra features to make testing PHP much easier. By using Codeception, you then get the ability to use [wp-browser](https://wpbrowser.wptestkit.dev/), a module that _greatly_ simplifies testing WordPress plugins, themes, and whole WP sites at all levels of testing.

» Learn more about [wp-browser here](https://wpbrowser.wptestkit.dev/) and get it set up on your project.

> You can see examples of what to toss in your `composer.json` in our [stellarwp/schema](https://github.com/stellarwp/schema/blob/main/composer.json) repository.

### Requirements

Docker.

That's the only prerequisite. Get that installed and running on your machine and you'll be good to go!

### Installation

#### 1. Clone the repo

> These instructions are assuming that you are cloning the `slic` repository in `~/git`. If you want it in a different location, feel free to tweak the example commands below.

```bash
cd ~/git
git clone git@github.com:stellarwp/slic.git
```

#### 2. Add `slic` to your `$PATH`

_Assuming you are cloning the `slic` repository in `~/git`:_

```bash
# Change ~/.bashrc if you aren't using zsh.
echo "export PATH=$HOME/git/slic:$PATH" >> ~/.zshrc
source ~/.zshrc
```

### The most important command to know

`slic` is well documented within the CLI utility itself. To see all of the available commands, run:

```bash
slic help
```

You can see details and usage on each command by running:

```bash
slic help <command>
```

## Using `slic`

The `slic` command has many subcommands. You can discover what those are by typing `slic` or `slic help`. If you want
more details on any of the subcommands, simply type: `slic [subcommand] help`.

### Tell `slic` how to find your project

The `slic` command needs a place to look for plugins, themes, and WordPress. By default, `slic` creates a `_plugins` and
`_wordpress` directory within the local checkout of `slic`. In most cases, however, developers like to run automated tests
against the paths where they are actively working on code – which likely lives elsewhere.

Good news! You can use the `slic here` sub-command to re-point `slic`'s paths so it looks in the places you wish. There
are two locations you can tell `slic` to look.

#### 1. Plugins Directory

If you want to defer all of the WP site configuration to a dynamically pulled codebase and _just_ worry about testing
plugins, you can run the `slic here` command right from the parent directory of your project. Doing so will restrict `slic` to running tests on subdirectories of where you ran the command.

Example:

```bash
# Change to your plugin containing dir (likely some path to wp-content/plugins)
cd /path/to/your/wp-content/plugins

slic here
```

![slic here](docs/images/slic-here.gif)

#### 2. WordPress Directory

The second option is to navigate to the root of your site (likely where `wp-config.php` lives) and run the `slic here`
command.

> Note: This is an opinionated option and there are some assumptions that are made:
>
> 1. That the WordPress directory _is_ the path you are indicating or in a sub-directory called `wp/`.
> 2. That the `wp-content/` (or `content/`) directory is a sub-directory of the location in which you are typing `slic here`.


```bash
# Change to your root directory of your site (where your wp-config.php file lives)
cd /path/to/your/site

slic here
```

By running `slic here` at the site level, this allows you to set plugins, themes, or the site itself as the location
from which to run tests. This also has the benefit of running tests within the WP version that your site uses.

![slic here](docs/images/slic-here-wp.gif)

### Preparing your project

### Point `slic` at your project

Before you can do anything productive with `slic`, you need to tell it which
project you wish to use and then you can initialize the project, run tests, and execute other commands to your heart's content!

```bash
slic use the-events-calendar
```

> For more information on this command, run `slic help use`.

![slic use](docs/images/slic-use.gif)

### Initialize your project

With your desired plugin containing directory set, you will need to initialize plugins so that they are prepped and ready
for `slic`-based automated test running. You do that using `slic init [plugin]`.

Example:

```bash
slic init event-tickets
```

![slic init](docs/images/slic-init.gif)

What this command does:

1. Generates a `.env.testing.slic` env file in the plugin.
2. Generates a `test-config.slic.php` file in the plugin.
3. Generates a `codeception.slic.yml` file in the plugin.
4. Prompts for confirmation on running `composer` and `npm` installs on the plugin.

### Adding tests

As mentioned above, you'll need to use Codeception for your automated testing and it is _highly_ recommended that you make use of [wp-browser](https://wpbrowser.wptestkit.dev/) - which adds a _lot_ of WordPress helper functions and utilities.

### Running tests

Ok. You have `slic` set up. It is pointing at your project. Your project has tests. Now you want to run one of your test suites. Let's pretend that your test suite is called `wpunit`.

You can run the full suite like so:

```bash
slic run wpunit
```

Or, if you want an even more efficient way to do it, you can do:

```bash
slic shell

# You'll get a prompt once you are thrown into the shell

> cr wpunit
```

## Advanced topics

### Making composer installs faster

By default, `slic` caches composer dependencies within the container
and, when the containers are destroyed, so is the cache. The good news
is that `slic` allows you to map your machine's composer cache directory into
the `slic` containers so that repeated `slic composer` commands can benefit from
the cache as well!

```bash
# Feel free to change the path to whatever is appropriate for your machine.
slic composer-cache set $HOME/.cache/composer
```

For more information on this topic, type `slic help composer-cache`.

![slic composer-cache](docs/images/slic-composer-cache.gif)

### Customizing `slic`'s `.env` file

The `slic` CLI command leverages `.env.*` files to dictate its inner workings. It loads `.env.*` files in the following order, the later files overriding the earlier ones:

1. [`.env.slic`](/.env.slic) - this is the default `.env` file and **should not be edited**.
2. `.env.slic.local` - this file doesn't exist by default, but if you wish to make overrides to the default, create it and add lines to your heart's content.
3. `.env.slic.run` - this file is generated by `slic` and includes settings that are set by specific `slic` commands.

### Xdebug and `slic`

#### Available Commands

##### `slic xdebug help`

List the available Xdebug commands.

##### `slic xdebug status`

See if Xdebug is enabled or disabled, the host information, and the path mapping to add to your IDE.

Note that this command cannot be ran within `slic shell` because you've SSH'd into the Codeception container which has no knowledge of *slic*.

#### Setup IDE for Xdebug

##### PhpStorm

Set your localhost plugin folder to map to `/var/www/html/wp-content/plugins`.

Video walk-throughs for [PhpStorm](https://drive.google.com/file/d/1sD8djXgmYWCUDCm_1XZNRx_GBbotmmiB/view?usp=sharing) and [VSCode](https://drive.google.com/file/d/1519M2SRVgWVgTm0Px6UKfBjoQgxCR7Cp/view?usp=sharing).

Screenshot from PhpStorm's video:

![PhpStorm XDebug settings](/docs/images/slic-Xdebug-PhpStorm.png "PhpStorm XDebug settings")

#### Enable/Disable Xdebug

1. `slic xdebug on`
1. `slic xdebug off`
1. Within `slic shell`:
    1. `slic xon`
    1. `slic xoff`



## Acknowledgements

Props to [@lucatume](https://github.com/lucatume), [@borkweb](https://github.com/borkweb), and [The Events Calendar](https://theeventscalendar.com) team for creating this tool and it's predecessor, `tric`.
