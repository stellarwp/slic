# Xdebug in `tric`

## Available Commands

### `tric xdebug help`

List the available Xdebug commands.

### `tric xdebug status`

See if Xdebug is enabled or disabled, the host information, and the path mapping to add to your IDE.

Note that this command cannot be ran within `tric shell` because you've SSH'd into the Codeception container which has no knowledge of *tric*.

#### Setup IDE for Xdebug

Video walk-throughs for [PhpStorm](https://drive.google.com/open?id=190vwEbkSw_aT7ZR6IvwMs50ZU2IQxLWD&authuser=luca%40tri.be&usp=drive_fs) and [VSCode](https://drive.google.com/open?id=19QeuODnskaFYDCCsB5mvfquecXBAytyM&authuser=luca%40tri.be&usp=drive_fs) (links require Modern Tribe login).

Screenshot from PhpStorm's video:

![PhpStorm XDebug settings](images/tric-Xdebug-PhpStorm.png "PhpStorm XDebug settings")


### Enable/Disable Xdebug

1. `tric xdebug on`
1. `tric xdebug off`
1. Within `tric shell`:
    1. `tric xon`
    1. `tric xoff`

