# Increasing the speed of composer

By default, `slic` caches composer dependencies within the container
and, when the containers are destroyed, so is the cache.

## Do not despair!

`slic` allows you to map your machine's composer cache directory into
the `slic` containers so that repeated `slic composer` commands can benefit from
composer cache as well! Simply:

```bash
slic composer-cache set /path/to/composer/cache

# @borkweb's command that he uses:
slic composer-cache set /home/matt/.cache/composer
```

## Removing the composer cache dir mapping

You can disable the cache dir mapping via the following:

```bash
slic composer-cache unset
```

## Discovering what the mapping is set to

You can see what the composer cache directory mapping is set to via:

```bash
slic composer-cache

# OR via:

slic info
```

*Note: When composer-cache is unset, it defaults to `/tmp`, though that mapped path is never used by composer.*
