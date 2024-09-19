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