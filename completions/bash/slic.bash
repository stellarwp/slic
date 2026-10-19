#!/usr/bin/env bash
# Bash completion script for slic
#
# This script provides tab completion for the slic CLI tool.
# It delegates completion generation to a PHP script.
#
# Installation:
#   Add this to ~/.bashrc or ~/.bash_profile:
#   source /path/to/slic/completions/bash/slic.bash
#
# Or use: slic completion install bash

_slic_completions() {
    local cur prev words cword
    # _init_completion comes from the bash-completion package; without it, take the word under the cursor as is.
    if declare -F _init_completion >/dev/null; then
        _init_completion || return
    else
        cur="${COMP_WORDS[COMP_CWORD]}"
    fi

    # Get the directory where slic is installed
    local slic_cmd
    slic_cmd=$(command -v slic 2>/dev/null)

    if [[ -z "$slic_cmd" ]]; then
        return 0
    fi

    # Resolve symlinks to get the actual slic directory
    if [[ -L "$slic_cmd" ]]; then
        # Try realpath first (more portable), then readlink -f (GNU), then readlink (basic)
        slic_cmd=$(realpath "$slic_cmd" 2>/dev/null || readlink -f "$slic_cmd" 2>/dev/null || readlink "$slic_cmd" 2>/dev/null)
    fi

    local slic_dir
    slic_dir=$(dirname "$slic_cmd")

    # Call the PHP completion script; words go one per line so a word can contain spaces
    local completions
    completions=$(IFS=$'\n'; php "${slic_dir}/src/completions/complete.php" \
        --line="${COMP_LINE}" \
        --point="${COMP_POINT}" \
        --words="${COMP_WORDS[*]}" \
        --cword="${COMP_CWORD}" 2>/dev/null)

    # Convert the one-per-line completions to an array, escaping each one for the command line
    if [[ -n "$completions" ]]; then
        local IFS=$'\n' i
        COMPREPLY=($(compgen -W "$completions" -- "$cur"))
        for i in "${!COMPREPLY[@]}"; do
            COMPREPLY[i]=$(printf '%q' "${COMPREPLY[i]}")
        done
    fi
}

# Register the completion function for slic
complete -F _slic_completions slic
