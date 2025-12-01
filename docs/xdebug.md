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

## WSL2

When using slic on WSL2 (Windows Subsystem for Linux), the default `host.docker.internal` configuration may not work correctly because Docker containers need to connect to the WSL2 host where your code editor is running, not the Windows host.

**Important:** All commands in this section should be run in your WSL2/Ubuntu terminal, not in Windows PowerShell or Command Prompt.

### Step 1: Find your WSL2 IP address

First, identify your WSL2 IP address:

```bash
hostname -I | awk '{print $1}'
```

This will output your WSL2 IP address (e.g., `172.24.206.58`).

### Step 2: Configure Xdebug to use the WSL2 IP

Configure slic's Xdebug to use your WSL2 IP address instead of the default `host.docker.internal`:

```bash
slic xdebug host $(hostname -I | awk '{print $1}')
```

Or manually set it if you know your WSL2 IP:

```bash
slic xdebug host 172.24.206.58
```

**Note:** WSL2 IP addresses can change after restarting WSL2 or Windows, though they often remain stable. If breakpoints stop working, check if your IP has changed and reconfigure if necessary (see [Troubleshooting](#troubleshooting) below).

### Step 3: Restart slic containers

After changing the Xdebug host, restart the slic containers to apply the changes:

```bash
slic restart
slic xdebug on
```

### Step 4: Verify the configuration

Verify that Xdebug is configured correctly:

```bash
slic xdebug status
```

You should see your WSL2 IP address in the "Remote host" field.

### Troubleshooting

If breakpoints still don't work:

1. **Check if your WSL2 IP changed**: WSL2 IP addresses can change after restarting WSL2 or Windows (though they often remain stable). If breakpoints stop working, verify your current IP and reconfigure if it has changed:
   ```bash
   slic xdebug host $(hostname -I | awk '{print $1}')
   slic restart
   slic xdebug on
   ```
   
   You can verify your current WSL2 IP with:
   ```bash
   hostname -I | awk '{print $1}'
   ```

2. **Verify your editor is listening**: Check your editor's debug console to see if there are any connection attempts from Xdebug. In VS Code, go to View → Output → Debug Console. In PHPStorm, check the Debug tool window.

3. **Check Xdebug is enabled**: Make sure Xdebug is enabled in slic:
   ```bash
   slic xdebug on
   ```

4. **Verify the port**: Ensure port 9001 is not blocked by a firewall and that your code editor is listening on that port.