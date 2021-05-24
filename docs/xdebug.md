# Xdebug in `tric`

## Available Commands

### `tric xdebug help`

List the available Xdebug commands.

### `tric xdebug status`

See if Xdebug is enabled or disabled, the host information, and the path mapping to add to your IDE.

Note that this command cannot be ran within `tric shell` because you've SSH'd into the Codeception container which has no knowledge of *tric*.

#### Setup IDE for Xdebug

##### PhpStorm

Set your localhost plugin folder to map to `/var/www/html/wp-content/plugins`.

Video walk-throughs for [PhpStorm](https://drive.google.com/file/d/1sD8djXgmYWCUDCm_1XZNRx_GBbotmmiB/view?usp=sharing) and [VSCode](https://drive.google.com/file/d/1519M2SRVgWVgTm0Px6UKfBjoQgxCR7Cp/view?usp=sharing).

Screenshot from PhpStorm's video:

![PhpStorm XDebug settings](images/tric-Xdebug-PhpStorm.png "PhpStorm XDebug settings")

### Enable/Disable Xdebug

1. `tric xdebug on`
1. `tric xdebug off`
1. Within `tric shell`:
    1. `tric xon`
    1. `tric xoff`

