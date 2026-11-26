# Fish completion script for slic
#
# This script provides tab completion for the slic CLI tool.
# It delegates completion generation to a PHP script.
#
# Installation:
#   Copy or symlink this file to ~/.config/fish/completions/slic.fish
#   Or use: slic completion install fish

function __slic_completions
    # Find the slic command
    set -l slic_cmd (command -v slic 2>/dev/null)

    if test -z "$slic_cmd"
        return 0
    end

    # Resolve symlinks to get the actual slic directory
    if test -L "$slic_cmd"
        set slic_cmd (realpath "$slic_cmd" 2>/dev/null; or readlink -f "$slic_cmd" 2>/dev/null; or readlink "$slic_cmd" 2>/dev/null)
    end

    set -l slic_dir (dirname "$slic_cmd")

    # Get the current command line tokens (already completed)
    set -l tokens (commandline -opc)

    # Get the current token being typed
    set -l current (commandline -ct)

    # Call the PHP completion script with the words one per line, the current token last, as bash and zsh do.
    # commandline -o has already unquoted the words, so --decoded tells the script not to unquote them again.
    php "$slic_dir/src/completions/complete.php" --decoded \
        --words=(string join -- \n $tokens $current | string collect) \
        --cword=(count $tokens) 2>/dev/null
end

# Register completions for slic command
# -f: don't complete files
# -a: provide completions from the function
complete -c slic -f -a "(__slic_completions)"
