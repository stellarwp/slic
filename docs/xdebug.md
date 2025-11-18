# Configuring Xdebug

Use `slic xdebug status` to find the configuration details for the project that you have run `slic use` on. This command will give you the path mappings and the port number that you need.

* [PHPStorm](#phpstorm)
* [VSCode](#vscode)

## PHPStorm

[Video walk-through](https://drive.google.com/file/d/1sD8djXgmYWCUDCm_1XZNRx_GBbotmmiB/view?usp=sharing)


### Set appropriate debug ports

In PHPStorm settings:

1. Search for `debug`
2. Select `PHP` > `Debug`
3. Ensure that the Debug port is set to whatever `slic xdebug status` returns. (typically `9001`)

### Set up server configuration

In PHPStorm settings:

1. Search for `server`
2. Select `PHP` > `Servers`
3. Click the `+` button to add a new server
4. Set the `Name` to `slic`
5. Set the `Host` to whatever `slic xdebug status` returns. (typically `http://localhost:8888` ... yes, put the whole thing in the `Host` field)
6. Set the `Port` to `80`
7. Check the `Use path mappings` checkbox
8. Find the `wp-content/plugins` directory and set the `Absolute path on the server` to `/var/www/html/wp-content/plugins`
9. If you've added the `slic` directory to your workspace, find the `slic/_wordpress` directory and set the `Absolute path on the server` to `/var/www/html`

Screenshot from PhpStorm's video:

![PhpStorm XDebug settings](/docs/images/slic-Xdebug-PhpStorm.png "PhpStorm XDebug settings")

## VSCode

[Video walk-through](https://drive.google.com/file/d/1519M2SRVgWVgTm0Px6UKfBjoQgxCR7Cp/view?usp=sharing)

Here's a standard configuration for Xdebug in VSCode or VSCode-like editors. _(Note: Be sure to use `slic xdebug status` to get the correct paths and port number.)_

```json
{
	"version": "0.2.0",
	"configurations": [
		{
			"name": "Slic: Listen for Xdebug",
			"type": "php",
			"request": "launch",
			"port": 9001,
			"pathMappings": {
				"/var/www/html/wp-content/plugins": "${workspaceFolder}/<PATH_TO_WP_CONTENT_DIR>/plugins",
				"/var/www/html": "${workspaceFolder}/slic/_wordpress"
			},
			"ignore": [
				"**/vendor/**/*.php"
			],
			"stopOnEntry": false
		}
	]
}
```

In this `launch.json` file, there are two `pathMappings` entries:

1. The first one maps the `slic` plugins directory (left side) to your local WP's plugins directory (right side).
2. The second one is technically optional, but it assumes you've added the `slic` directory to your VSCode workspace and maps the `slic` WP root (left side) to your local `slic` directory's WP root (right side).

## Multi-Stack XDebug Configuration

When working with multiple slic stacks simultaneously, each stack gets its own unique XDebug configuration to prevent conflicts and enable debugging across multiple projects at the same time.

### How Stack-Specific Configuration Works

Each slic stack is assigned two unique identifiers:

1. **XDebug Port**: A unique port in the range of 49000-59000
2. **Server Name (IDE Key)**: A unique identifier based on the XDK environment variable

#### Understanding the XDK Environment Variable

The `XDK` environment variable is used as the **root** to build the IDE key/server name for each stack. The actual IDE key format is:

```
${XDK:-slic}_<stack_hash>
```

This means:
- If you set `XDK=my_project`, the IDE key becomes `my_project_a1b2c3d4` (where `a1b2c3d4` is the stack hash)
- If `XDK` is not set, it defaults to `slic`, creating IDE keys like `slic_a1b2c3d4`
- This allows you to namespace your debugging sessions by project or organization

You can set the XDK value using:
```bash
slic xdebug key my_project
```

Or by adding it to your `.env.slic.local` file:
```
XDK=my_project
```

Both the port and server name values are deterministically generated from the stack's absolute path using MD5 hashing. This means:
- The same stack will always get the same port and server name
- Different stacks will have different ports and server names
- No manual configuration is needed - it's automatic

### Benefits of Multi-Stack Configuration

- **No Port Conflicts**: Each stack has its own XDebug port, so you can debug multiple projects simultaneously
- **Isolated Debugging Sessions**: Switch between debugging different projects without reconfiguring your IDE
- **Consistent Configuration**: The same stack always uses the same port and server name, even after restarts
- **Parallel Development**: Work on multiple plugins or themes across different stacks without interference

### Viewing Your Stack's Configuration

Use `slic xdebug status` to see your current stack's XDebug configuration:

```bash
slic xdebug status
```

This will display:
- The XDebug port assigned to your stack
- The server name for your stack
- Path mappings for your IDE configuration

### IDE Setup for Multiple Stacks

When configuring your IDE to work with multiple slic stacks, you'll need to create a separate debug configuration for each stack.

#### PHPStorm Multi-Stack Setup

For each stack you're working with:

1. Create a new server configuration (PHP > Servers)
2. Use the stack-specific server name from `slic xdebug status` (e.g., `slic_a1b2c3d4`)
3. Add the stack-specific port from `slic xdebug status` to your debug ports list
4. Configure the same path mappings as described in the [PHPStorm section](#phpstorm) above

#### VSCode Multi-Stack Setup

Create multiple debug configurations in your `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Slic: Stack 1 (Event Tickets)",
            "type": "php",
            "request": "launch",
            "port": 49123,
            "pathMappings": {
                "/var/www/html/wp-content/plugins": "${workspaceFolder}/event-tickets",
                "/var/www/html": "${workspaceFolder}/event-tickets/slic/_wordpress"
            },
            "ignore": [
                "**/vendor/**/*.php"
            ],
            "stopOnEntry": false
        },
        {
            "name": "Slic: Stack 2 (The Events Calendar)",
            "type": "php",
            "request": "launch",
            "port": 52456,
            "pathMappings": {
                "/var/www/html/wp-content/plugins": "${workspaceFolder}/the-events-calendar",
                "/var/www/html": "${workspaceFolder}/the-events-calendar/slic/_wordpress"
            },
            "ignore": [
                "**/vendor/**/*.php"
            ],
            "stopOnEntry": false
        }
    ]
}
```

### Example: Multiple Stacks Configuration

Here's an example showing how two different stacks would have different configurations:

**Stack 1: Event Tickets** (`/Users/developer/projects/event-tickets`)
```bash
$ cd /Users/developer/projects/event-tickets
$ slic xdebug status
XDebug is enabled
Server name: slic_a1b2c3d4
XDebug port: 49123
```

**Stack 2: The Events Calendar** (`/Users/developer/projects/the-events-calendar`)
```bash
$ cd /Users/developer/projects/the-events-calendar
$ slic xdebug status
XDebug is enabled
Server name: slic_e5f6g7h8
XDebug port: 52456
```

With this setup:
- You can debug Event Tickets code by starting the "Stack 1" debug configuration (port 49123)
- You can debug The Events Calendar code by starting the "Stack 2" debug configuration (port 52456)
- Both can run simultaneously without any conflicts
- Each stack maintains its own independent debugging session

### Worktree Path Mappings

When working with git worktrees in slic, you'll see an additional path mapping in the output of `slic xdebug status`. This is crucial for IDE debugging to work correctly with worktree stacks.

#### What are Worktree Stacks?

Git worktrees allow you to have multiple working directories for the same repository, each checked out to different branches. Slic supports this workflow by creating "worktree stacks" that each have their own Docker containers and XDebug configuration.

#### Worktree Path Mapping Output

For a worktree stack, `slic xdebug status` will display **three** path mappings instead of the usual two:

```bash
# Standard mappings (same for all stacks)
Path mapping (host => server): /path/to/wp-content/plugins => /var/www/html/wp-content/plugins
Path mapping (host => server): /path/to/_wordpress => /var/www/html

# Additional worktree mapping (only for worktree stacks)
Path mapping (host => server): /path/to/my-plugin/feature-branch => /var/www/html/wp-content/plugins/my-plugin
```

#### Why the Third Mapping is Important

The worktree-specific mapping ensures your IDE can correctly map breakpoints and step through code in the worktree directory. Without this mapping:
- Breakpoints set in worktree files won't be hit
- The debugger won't be able to find source files
- Stepping through code will fail

#### Example: Plugin Worktree

Let's say you're working on the "event-tickets" plugin with a worktree for a feature branch:

```bash
$ cd /Users/developer/projects/event-tickets/fix-123
$ slic xdebug status

Stack: event-tickets@fix-123
Project: event-tickets

XDebug status is: on
Remote host: host.docker.internal
Remote port: 49123
IDE Key (server name): slic_a1b2c3d4

Set up, in your IDE, a server with the following parameters to debug PHP requests:
IDE key, or server name: slic_a1b2c3d4
Host: http://localhost:8001
Path mapping (host => server): /Users/developer/projects/event-tickets/_plugins => /var/www/html/wp-content/plugins
Path mapping (host => server): /Users/developer/projects/event-tickets/_wordpress => /var/www/html
Path mapping (host => server): /Users/developer/projects/event-tickets/fix-123 => /var/www/html/wp-content/plugins/event-tickets
```

The third mapping (`/Users/developer/projects/event-tickets/fix-123 => /var/www/html/wp-content/plugins/event-tickets`) tells your IDE that files in the worktree directory correspond to the plugin directory inside the container.

#### Example: Theme Worktree

For a theme worktree, the mapping points to the themes directory instead:

```bash
Path mapping (host => server): /Users/developer/projects/my-theme/new-design => /var/www/html/wp-content/themes/my-theme
```

#### Configuring Your IDE for Worktrees

**PHPStorm:**
When adding path mappings for a worktree stack, add all three mappings to your server configuration:

1. Plugins directory: `/path/to/_plugins` → `/var/www/html/wp-content/plugins`
2. WordPress root: `/path/to/_wordpress` → `/var/www/html`
3. Worktree directory: `/path/to/worktree-dir` → `/var/www/html/wp-content/plugins/your-plugin` (or `/themes/your-theme`)

**VSCode:**
Add all three mappings to your `pathMappings` in `launch.json`:

```json
{
    "name": "Slic: Worktree (fix-123)",
    "type": "php",
    "request": "launch",
    "port": 49123,
    "pathMappings": {
        "/var/www/html/wp-content/plugins": "${workspaceFolder}/event-tickets/_plugins",
        "/var/www/html": "${workspaceFolder}/event-tickets/_wordpress",
        "/var/www/html/wp-content/plugins/event-tickets": "${workspaceFolder}/event-tickets/fix-123"
    }
}
```

### Tips for Multi-Stack Debugging

1. **Always check your current stack**: Run `slic xdebug status` to confirm which stack you're working with
2. **Copy path mappings exactly**: Use the exact paths from `slic xdebug status` output when configuring your IDE
3. **Include all three mappings for worktrees**: Don't forget the worktree-specific mapping when debugging worktree stacks
4. **Name your configurations clearly**: Use descriptive names in your IDE debug configurations to easily identify which stack they're for
5. **Keep configurations in sync**: If you change path mappings or update slic, remember to update all your stack configurations
6. **Use workspace folders**: In IDEs that support it (like VSCode), use workspace folders to manage multiple stacks in a single window