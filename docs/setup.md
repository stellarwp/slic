# `slic` Setup

## Prerequisites

1. Docker (installed and running)

## Setup

1. Clone this repository to your machine. Should be a common location, not within a single WP site's directory (e.g. `~/git/slic`)
1. Ensure the `slic` command is runnable by adding it to your path or symlinking `slic` into a directory that is in your path.

### Add to your path

Edit your `.bashrc` or `.zshrc` or equivalent file in your home directory and add:

```bash
export PATH=$PATH:/PATH/TO/slic

# An example entry in .zshrc is where this repo is cloned to ~/git/slic:
# export PATH=$PATH:$HOME/git/slic
```

### Symlink `slic`

```bash
cd /usr/local/bin
ln -s /PATH/TO/slic
```
