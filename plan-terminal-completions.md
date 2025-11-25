# Terminal Completions Implementation Plan for slic

## Overview

This plan outlines the implementation of dynamic terminal completions for the slic CLI tool. The goal is to create a two-layer system where shell scripts (bash/zsh/fish) delegate completion generation to a PHP script, which uses caching for performance.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                   Terminal (bash/zsh/fish)                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│              Shell Completion Script (thin layer)               │
│  - Receives COMP_WORDS, COMP_CWORD from shell                   │
│  - Calls PHP completion script with current line/word info      │
│  - Returns completions to shell                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                  PHP Completion Script                          │
│  - Parses command line context                                  │
│  - Determines what to complete (command, subcommand, argument)  │
│  - Uses caching for expensive operations                        │
│  - Returns space-separated completions                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     File-based Cache                            │
│  - ~/.slic/cache/completions/                                   │
│  - TTL-based invalidation                                       │
│  - Separate cache files for different completion types          │
└─────────────────────────────────────────────────────────────────┘
```

## File Structure

```
slic/
├── completions/
│   ├── bash/
│   │   └── slic.bash           # Bash completion script
│   ├── zsh/
│   │   └── _slic               # Zsh completion script
│   └── fish/
│       └── slic.fish           # Fish completion script
├── src/
│   ├── commands/
│   │   └── completion.php      # 'slic completion' helper command
│   ├── completions/
│   │   ├── complete.php        # Main completion entry point
│   │   ├── CompletionCache.php # File-based cache class
│   │   └── completers/         # Completer classes by command
│   │       ├── CommandCompleter.php
│   │       ├── UseCompleter.php
│   │       ├── StackCompleter.php
│   │       ├── XdebugCompleter.php
│   │       ├── WorktreeCompleter.php
│   │       └── ...
```

## Commands Analysis

### Commands with Static Completions (no arguments or fixed options)

| Command | Subcommands/Options |
|---------|---------------------|
| `airplane-mode` | `on`, `off`, `status` |
| `build-prompt` | `on`, `off`, `status` |
| `build-subdir` | `on`, `off`, `status` |
| `cache` | `on`, `off`, `status` |
| `debug` | `on`, `off`, `status` |
| `interactive` | `on`, `off`, `status` |
| `xdebug` | `on`, `off`, `status`, `port`, `host`, `key` |
| `php-version` | `set`, `reset` |

### Commands with Dynamic Completions

| Command | Dynamic Arguments |
|---------|-------------------|
| `use` | List of valid targets (plugins, themes, site) AND subdirectories (e.g., `event-tickets/common`) |
| `stack` | Subcommands: `list`, `stop`, `info`; Stack paths for `stop`/`info` |
| `worktree` | Subcommands: `add`, `list`, `merge`, `remove`, `sync`; Branch names |
| `run` | Test suites: `wpunit`, `functional`, `acceptance`, `integration`, etc. |
| `composer` | Standard composer commands |
| `npm` | Standard npm commands |
| `wp` / `cli` | wp-cli commands |
| `dc` | docker compose commands |

### Commands with No Completion Needed

| Command | Reason |
|---------|--------|
| `help` | No arguments |
| `here` | No arguments (uses current directory) |
| `info` | No arguments |
| `logs` | No arguments |
| `ps` | No arguments |
| `restart`, `start`, `stop`, `up`, `down` | No arguments |
| `shell`, `ssh` | No arguments |
| `update`, `upgrade` | No arguments |
| `config` | No arguments |
| `host-ip` | No arguments |
| `reset` | No arguments |
| `using` | No arguments |

### Global Options

- `--stack=<path>` or `--stack <path>`: Should complete with registered stack paths
- `-q`: Quiet mode (no completion needed)

## Implementation Details

### 1. Shell Scripts (Thin Layer)

#### Bash (`completions/bash/slic.bash`)

```bash
_slic_completions() {
    local cur="${COMP_WORDS[COMP_CWORD]}"
    local slic_dir="$(dirname "$(readlink -f "$(which slic)")")"

    # Call PHP script with completion context
    COMPREPLY=($(php "${slic_dir}/src/completions/complete.php" \
        --line="${COMP_LINE}" \
        --point="${COMP_POINT}" \
        --words="${COMP_WORDS[*]}" \
        --cword="${COMP_CWORD}" 2>/dev/null))
}

complete -F _slic_completions slic
```

#### Zsh (`completions/zsh/_slic`)

```zsh
#compdef slic

_slic() {
    local slic_dir="$(dirname "$(readlink -f "$(which slic)")")"
    local completions

    completions=($(php "${slic_dir}/src/completions/complete.php" \
        --line="${BUFFER}" \
        --point="${CURSOR}" \
        --words="${words[*]}" \
        --cword="${CURRENT}" 2>/dev/null))

    _describe 'slic' completions
}

_slic "$@"
```

#### Fish (`completions/fish/slic.fish`)

```fish
# Fish completion script for slic
function __slic_completions
    set -l slic_cmd (command -v slic)
    set -l slic_dir (dirname (realpath $slic_cmd))

    # Get the current command line tokens
    set -l tokens (commandline -opc)
    set -l current (commandline -ct)

    # Call PHP script with completion context
    php "$slic_dir/src/completions/complete.php" \
        --shell=fish \
        --words="$tokens" \
        --current="$current" 2>/dev/null
end

# Register completions for slic command
complete -c slic -f -a "(__slic_completions)"
```

### 2. PHP Completion Script (`src/completions/complete.php`)

Main entry point that:
1. Parses command line arguments
2. Determines completion context (which command, position)
3. Delegates to appropriate completer
4. Returns space-separated completions

### 3. Completion Cache (`src/completions/CompletionCache.php`)

File-based cache with:
- Location: `~/.slic/cache/completions/` or `$SLIC_CACHE_DIR/completions/`
- Cache keys include: `commands`, `targets`, `stacks`, `worktrees`, `branches`
- TTL configuration:
  - Commands list: 24 hours (static, rarely changes)
  - Targets: 5 minutes (filesystem based)
  - Stacks: 1 minute (can change frequently)
  - Git branches: 2 minutes (can change during development)

### 4. Completer Classes

Each completer is responsible for a specific command or group of related commands:

#### `CommandCompleter.php`
- Returns list of all available commands
- Cached for 24 hours

#### `UseCompleter.php`
- Returns list of valid targets from `get_valid_targets()`
- Also returns subdirectories within plugins (e.g., `event-tickets/common`)
- Scans plugin directories for subdirectories containing `composer.json` or standard test directories
- Cached for 5 minutes

#### `StackCompleter.php`
- Returns stack subcommands (`list`, `stop`, `info`)
- Returns registered stack IDs for stop/info
- Uses existing `slic_stacks_list()` function

#### `XdebugCompleter.php`
- Returns: `on`, `off`, `status`, `port`, `host`, `key`
- For `port`/`host`/`key`: no further completion

#### `WorktreeCompleter.php`
- Returns subcommands: `add`, `list`, `merge`, `remove`, `sync`
- For `add`/`merge`/`remove`: suggests git branches
- Uses `git branch -a` for branch names

#### `ToggleCompleter.php` (shared)
- Used by: `airplane-mode`, `build-prompt`, `build-subdir`, `cache`, `debug`, `interactive`
- Returns: `on`, `off`, `status`

#### `PhpVersionCompleter.php`
- Returns: `set`, `reset` for first argument
- For `set`: suggests common PHP versions (7.4, 8.0, 8.1, 8.2, 8.3)

## Caching Strategy

### Cache File Format

```json
{
  "created_at": 1700000000,
  "ttl": 300,
  "data": ["target1", "target2", "..."]
}
```

### Cache Keys and TTLs

| Cache Key | TTL | Description |
|-----------|-----|-------------|
| `commands` | 86400 (24h) | List of all slic commands |
| `targets` | 300 (5min) | Valid use targets |
| `stacks` | 60 (1min) | Registered stacks |
| `branches` | 120 (2min) | Git branches for worktree |
| `static_*` | 86400 (24h) | Static subcommands per command |

### Cache Invalidation

- Automatic: TTL-based expiration
- Manual: `slic completion cache-clear` command (optional feature)
- Smart: File modification time checks for filesystem-based completions

## The `slic completion` Command

A helper command that simplifies shell completion setup:

### Usage

```bash
# Show installation instructions for detected shell
slic completion

# Install completions for specific shell
slic completion install [bash|zsh|fish]

# Show the completion script content (for manual setup)
slic completion show [bash|zsh|fish]

# Clear completion cache
slic completion cache-clear
```

### Subcommands

#### `slic completion` (no arguments)
- Auto-detects current shell from `$SHELL` environment variable
- Prints installation instructions for that shell
- Shows what line to add to shell config file

#### `slic completion install <shell>`
- Attempts to automatically install completions:
  - **bash**: Appends source line to `~/.bashrc` or `~/.bash_profile`
  - **zsh**: Adds fpath and source to `~/.zshrc`
  - **fish**: Creates symlink in `~/.config/fish/completions/`
- Checks if already installed to avoid duplicates
- Asks for confirmation before modifying files

#### `slic completion show <shell>`
- Outputs the shell-specific completion script content
- Useful for users who want to customize or manually install

#### `slic completion cache-clear`
- Clears the completion cache directory
- Useful when completions seem stale

## Installation Instructions

### Automatic Installation (Recommended)

```bash
slic completion install
```

This will detect your shell and set up completions automatically.

### Manual Installation

#### Bash

Add to `~/.bashrc` or `~/.bash_profile`:

```bash
# slic completions
if command -v slic &> /dev/null; then
    source "$(dirname "$(readlink -f "$(which slic)")")/completions/bash/slic.bash"
fi
```

#### Zsh

Add to `~/.zshrc`:

```zsh
# slic completions
if command -v slic &> /dev/null; then
    fpath=("$(dirname "$(readlink -f "$(which slic)")")/completions/zsh" $fpath)
    autoload -Uz compinit && compinit
fi
```

Or create symlink:

```bash
ln -s /path/to/slic/completions/zsh/_slic ~/.zsh/completions/_slic
```

#### Fish

Copy or symlink the completion file:

```fish
# Create completions directory if it doesn't exist
mkdir -p ~/.config/fish/completions

# Symlink the completion file
ln -s /path/to/slic/completions/fish/slic.fish ~/.config/fish/completions/slic.fish
```

Or source directly in `~/.config/fish/config.fish`:

```fish
# slic completions
if type -q slic
    source (dirname (realpath (which slic)))/completions/fish/slic.fish
end
```

## Implementation Phases

### Phase 1: Core Infrastructure
1. Create `CompletionCache.php` class
2. Create `complete.php` entry point
3. Create bash completion script
4. Create zsh completion script
5. Create fish completion script
6. Implement `CommandCompleter` for top-level commands

### Phase 2: Essential Completers
1. `UseCompleter` - most frequently used, with subdirectory support
2. `StackCompleter` - for multi-stack management
3. `ToggleCompleter` - shared by many commands
4. `XdebugCompleter` - specific xdebug options

### Phase 3: Advanced Completers
1. `WorktreeCompleter` - git branch integration
2. `PhpVersionCompleter` - version suggestions
3. Global `--stack` option completion

### Phase 4: Helper Command & Documentation
1. Create `slic completion` command with install/show/cache-clear subcommands
2. Update README with installation instructions
3. Test on macOS and Linux
4. Handle edge cases (spaces in paths, special characters)

## Testing Plan

1. Manual testing with:
   - Tab completion at various positions
   - Partial word completion
   - Commands with/without arguments
   - Multi-word arguments

2. Test scenarios:
   - `slic <TAB>` → all commands
   - `slic use <TAB>` → all targets including subdirectories
   - `slic use event-<TAB>` → filtered targets starting with "event-"
   - `slic use event-tickets/<TAB>` → subdirectories within event-tickets
   - `slic stack <TAB>` → subcommands
   - `slic stack stop <TAB>` → stack IDs
   - `slic xdebug <TAB>` → options
   - `slic worktree add <TAB>` → branch names
   - `slic --stack=<TAB>` → stack paths
   - `slic --stack <TAB>` → stack paths
   - `slic completion <TAB>` → install, show, cache-clear

## Performance Considerations

1. **Minimize PHP startup time**: Use lightweight includes, only load what's needed
2. **File-based cache**: Avoid database or complex storage
3. **Early exit**: Return as soon as completions are determined
4. **Lazy loading**: Only load completers that are needed
5. **Parallel-safe**: File locking for cache writes

## Resolved Design Decisions

1. **Fish shell support**: Yes, included along with bash and zsh
2. **Helper command**: Yes, `slic completion` with install/show/cache-clear subcommands
3. **Subdirectory completion for `use`**: Yes, will show `plugin/subdir` format
4. **Cache location**: Will use `~/.slic/cache/completions/` by default, configurable via `SLIC_CACHE_DIR` env var

## Open Questions

1. Should we add completion for file paths in certain commands (e.g., `--stack=/path`)?
2. Should branch completion for worktree commands include remote branches or only local?

## Dependencies

- No new PHP dependencies required
- Uses existing slic functions for target/stack discovery
- Requires `readlink` or equivalent for script path resolution

## Integration with slic Help

The `completion` command should be added to the **Advanced** commands section in the help output:

```
<light_cyan>completion</light_cyan>  Manage shell completion (install, show, cache-clear).
```

## Summary

This implementation provides:

1. **Dynamic completions** via a PHP backend that can be updated without shell reconfiguration
2. **Caching** for fast completion responses
3. **Multi-shell support** for bash, zsh, and fish
4. **Easy installation** via `slic completion install`
5. **Subdirectory support** for the `use` command
6. **Extensibility** through completer classes for each command type
