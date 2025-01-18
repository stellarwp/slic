# Using a `.slicrc` file

You can add a `.slicrc` file to your project root to ensure that slic is setup correctly when you `slic use` your project.

Here's an example `.slicrc` file:

```json
{
	"php-version": "8.2"
}
```

## `php-version`

When you specify a `php-version` in your `.slicrc` file, slic will automatically switch to that PHP version when you run `slic use` in your project if that isn't the current PHP version in slic.
