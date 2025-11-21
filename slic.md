# Products local testing environment

The purpose of this environment is to provide Products developers with a local testing environment that is identical to the one running in CI.
The same local testing environment *can be* used as a local development, but that is not its main goal.

The entrypoint to anything you will need to do will be the `slic` binary, located in the root directory.

## Requirements

The stack runs based on [Docker](https://www.docker.com/) and [docker compose](https://docs.docker.com/compose/): you will need both installed and available to be able to use the local testing environment.
If your Docker installation requires root access, please follow [this guide](https://docs.docker.com/install/linux/linux-postinstall/) to make sure root access is not required to run `docker` commands.

You should be able to run the following command without issues and without requiring root access:

```bash
docker run hello-world
```

If this is not the case, please take the time to read Docker and docker compose documentation and fix the issues you encounter.

## Where to get help

First and foremost the most up-to-date documentation should be the output of the `slic help` command and the `help` sub-commands of each `slic` command.
Second, look at the code: this tools is written in PHP, the same language as WordPress and, as such, should be easy to inspect and update.

## Preparing the plugins

The stack does not come with any plugin you need to work on, so you will have to do some work to set them up one by one, as required by your work.

The stack will look for plugins in the `_plugins` directory, so change directory to that and clone the plugin(s) you need to work on:

```bash
cd _plugins
git clone git@github.com:the-events-calendar/the-events-calendar.git
cd the-events-calendar
git checkout release/B20.03
git submodule update --recursive --init
```

The last part is to build the required Composer and `npm` dependencies.
To avoid misaligned Composer dependencies, use the `composer` service provided in the stack to update the PHP dependencies:

```bash
slic use the-events-calendar
slic composer install
```

Give the whole process some time to complete and do the same for each plugin you need to work on.

`npm` dependencies are not covered yet.

## Running tests

To run tests using this local testing environment use the `slic` binary contained in the root directory.

Run the `slic` command without any arguments to get an help page and a list of commands.

To run the `wpunit` suite of `the-events-calendar` plugin, as an example, use:

```bash
slic use the-events-calendar
slic run wpunit
```

Since the command might potentially run on any of the plugins maintained by the Products team, you'll need to tell the `slic` command what plugin you're currently working on with the `use` sub-command.
You can change the plugin you want to run tests on by using the `slic use` command again at any moment. If you want, now to run Event Tickets tests, you could just use:

```bash
slic use event-tickets
slic run integration
```

### Running a specific test case

If you need to run a specific test case in a suite only, you can do this using the same command you would use in Codeception:

```bash
slic use event-calendar-pro
slic run tests/integration/SomeTest.php
```

### Running a specific test method

If you need to run a specific test method, part of a test case, you can do this using the same command you would use in Codeception:

```bash
slic use event-calendar-pro
slic run tests/integration/SomeTest.php:test_foo_is_not_bar
```

## Using the shell

If you want more hands-on control on the stack, or need to access its inner workings, you can open a shell into the `codeception` container with the `shell` command:

```bash
slic shell
```

The shell will open, by default, in the container `/plugins` directory. The directory is mapped to the `_plugins` directory on the host.
Once the shell opens you will be able to use any Codeception command available, e.g.:

```
cd /plugins/the-events-calendar
vendor/bin/codecept run wpunit
```

## Debugging tests with XDebug

The stack will run, by default and for performance reasons, without XDebug activated.
You will need to debug the tests, plugin or WordPress code during your work and XDebug is the only reliable option to do so.

If the stack is currently running you will need to tear down the stack first:


Then activate the stack debug mode:

```bash
slic xdebug on
```
> Note: activating and deactivating the debug mode will tear down, stop and remove, the stack containers and, with them, any modification you've made to the stack. If you have valuable information (you should not) inthe stack, then save it first.

The next time you spin up the stack to run tests or to have the WordPress installation locally served, you will be able to debug the code line-by-line using the following settings in your IDE:

* Listen for XDebug connections on port 9001
* Set up path mappings:
	* `_wordpress` -> `/var/www/html`
	* `_plugins` -> `/plugins`
* If your IDE allows it, then set the server name and host to `slic`, this is the IDE configuration key XDebug will use when communicating with your host machine.

Please refer to your IDE of choice to know how to set up these values.

> Note if you cannot set the above values in your "IDE", then your "IDE" is not an "IDE": it's a powerful and cute text editor.

When you are done with debugging and want to deactivate it, just run this command:

```bash
slic xdebug off
```

You can get more information about the available XDebug options using the `slic xdebug help` command.

## Using the stack as a local development environment

This is not the stack main purpose, but it can be done as long as you're willing to work on a site served on `http://localhost`.

You can start the stack, and have it configured for you, running the following command:

```bash
slic serve 8888
```

The above command will start the stack, install a fresh copy of WordPress, set it up to look for plugins in the `_plugins` directory, and be served at `http://localhost:8888`.
The stack will also create and scaffold the WordPress installation ins the `_wordpress` directory if it does not exist already.

You can stop the stack by running the following command:

```bash
slic down
```

### Using wp-cli in the stack

If you are using the stack as a local development environment, then you might need to interact with it using wp-cli.
In that case you can use the `slic cli` sub-command to run any command on the stack:

```bash
slic cli plugin list --status=active
slic cli plugin activate the-events-calendar event-tickets
```

If you need to run any command that would modify a plugin filesystem, remember that, in the container, the plugins are located in the `/plugins` directory.
As an example, if I wanted to dump the current database contents to the `tests/_data/dump.sql` file of `the-events-calendar` plugin, I would run this command:

```bash
slic cli db export /plugins/the-events/calendar/tests/_data/dump.sql
```

## Git Worktree Support

The stack supports git worktrees with dedicated slic stacks, allowing you to work on multiple branches simultaneously with isolated environments.

### Creating a worktree

Create a new worktree for a feature branch:

```bash
slic worktree add fix/issue-123
```

This will:
- Create a git worktree in a sibling directory
- Set up a dedicated slic stack for the worktree
- Allow you to run tests and develop in isolation

### Listing worktrees

View all worktrees and their associated stacks:

```bash
slic worktree list
```

### Merging a worktree

When you're done working on a feature branch, merge it back and clean up:

```bash
# From the base stack directory (not the worktree directory)
slic worktree merge fix/issue-123
```

**Important:** This command must be run from the base stack directory, not from within the worktree directory being merged. The worktree directory will be removed by git during the merge process.

The merge command will:
1. Checkout the base branch in the target repository
2. Merge the worktree branch into the base branch
3. Remove the git worktree directory
4. Delete the local worktree branch
5. Stop Docker containers
6. Unregister the slic stack

Use `-y` or `--yes` to skip confirmation prompts:

```bash
slic worktree merge fix/issue-123 -y
```

If the merge encounters conflicts, the command will stop and provide instructions for manual resolution.

### Removing a worktree without merging

To remove a worktree without merging its changes:

```bash
slic worktree remove fix/issue-123
```

### Synchronizing worktrees

Clean up stale entries when worktrees have been manually removed:

```bash
slic worktree sync
```

## How the stack works, an overview

The stack services are defined by the `slic-stack.yml` file.
This is a YAML format docker compose configuration file the `slic` binary will use to run the `docker compose` command.
The main services defined there are:

* `wordpress` - this uses the `wordpress:latest` image, the official Docker image for WordPress. When running the container will fill the `_wordpress` directory with the contents of the WordPress installation that is currently serving the container. Furthermore the WordPress container is configured to look for plugins in the `/plugins` directory, that directory is a shared volume that you can find in the `dev/test/plugins` directory.
* `db` - this is an image that is providing the database for the installation. For performance and isolation reasons the database is **not** persisted across test runs and any data stored in the database will be lost when the container is stopped and removed.
* `cli` - this uses the `wordpress:cli` image to provide wp-cli commands for the stack.
* `codeception` - this service contains the Codeception runner and support code, the image is a custom one adapted to WordPress usage.
* `chrome` - this image provides the Chrome instance that will run the acceptance tests requiring JavaScript support.

The `slic` command, and the interactive shell, will mostly interact and run commands into the `codeception` image.
