# Increasing the speed of composer

By default, `tric` caches composer dependencies within the container
and, when the containers are destroyed, so is the cache.

## Do not despair!

`tric` allows you to map your machine's composer cache directory into
the `tric` containers so that repeated `tric composer` commands can benefit from
composer cache as well! Simply:

```bash
tric composer-cache set /path/to/composer/cache

# @borkweb's command that he uses:
tric composer-cache set /home/matt/.cache/composer
```

## Removing the composer cache dir mapping

You can disable the cache dir mapping via the following:

```bash
tric composer-cache unset
```

## Discovering what the mapping is set to

You can see what the composer cache directory mapping is set to via:

```bash
tric composer-cache

# OR via:

tric info
```

*Note: When composer-cache is unset, it defaults to `/tmp`, though that mapped path is never used by composer.*
