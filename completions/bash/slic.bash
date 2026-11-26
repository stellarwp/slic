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

    # Resolve symlinks to get the actual slic directory; a relative link target is relative to the link's directory.
    # Plain readlink works everywhere, unlike realpath or readlink -f.
    local slic_link
    while [[ -L "$slic_cmd" ]]; do
        slic_link=$(readlink "$slic_cmd")
        [[ "$slic_link" == /* ]] || slic_link="$(dirname "$slic_cmd")/$slic_link"
        slic_cmd=$slic_link
    done

    local slic_dir
    slic_dir=$(dirname "$slic_cmd")

    # Call the PHP completion script; words go one per line so a word can contain spaces
    local completions
    completions=$(IFS=$'\n'; php "${slic_dir}/src/completions/complete.php" \
        --line="${COMP_LINE}" \
        --point="${COMP_POINT}" \
        --words="${COMP_WORDS[*]}" \
        --cword="${COMP_CWORD}" 2>/dev/null)

    # Keep the one-per-line completions that start with the current word, escaping each one for the command line.
    # Not compgen -W: it expands $(...) in a candidate, such as a branch or folder name, and runs it.
    # Bash 3 keeps --stack=<path> as one word, but readline only replaces the text after the =: drop the rest.
    local word="${COMP_WORDS[COMP_CWORD]}" before_equals=""
    [[ "$COMP_WORDBREAKS" == *=* && "$word" == *=* ]] && before_equals="${word%"${word##*=}"}"
    if [[ -n "$completions" ]]; then
        local completion
        COMPREPLY=()
        while IFS= read -r completion; do
            completion="${completion#"$before_equals"}"
            [[ "$completion" == "${cur#"$before_equals"}"* ]] && COMPREPLY+=("$(printf '%q' "$completion")")
        done <<< "$completions"
    fi
}

# Register the completion function for slic
complete -F _slic_completions slic
