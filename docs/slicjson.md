# Using a `slic.json` file

You can add a `slic.json` file to your project root to ensure that slic is setup correctly when you `slic use` your project.

Here's an example `slic.json` file:

```json
{
	"phpVersion": "8.2"
}
```

## `phpVersion`

When you specify a `phpVersion` in your `slic.json` file, slic will automatically switch to that PHP version when you run `slic use` in your project if that isn't the current PHP version in slic.
