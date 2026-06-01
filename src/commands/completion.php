<?php
/**
 * Handles the `completion` command.
 *
 * @var bool     $is_help  Whether we're handling an `help` request on this command or not.
 * @var string   $cli_name The current name of slic CLI binary.
 * @var \Closure $args     The argument map closure, as produced by the `args` function.
 */

namespace StellarWP\Slic;

if ( $is_help ) {
	$help = <<< HELP
	SUMMARY:

		Manages shell completion for {$cli_name}. Supports installation, display, and cache management
		for bash, zsh, and fish shells.

	USAGE:

		<yellow>{$cli_name} completion [install|show|cache-clear] [bash|zsh|fish]</yellow>

	SUBCOMMANDS:

		(no arguments)
		Auto-detects your shell and displays installation instructions.

		<light_cyan>install [bash|zsh|fish]</light_cyan>
		Automatically installs completions for the specified shell (or auto-detected shell).
		- bash: Appends source line to ~/.bashrc or ~/.bash_profile
		- zsh: Adds fpath and source to ~/.zshrc
		- fish: Creates symlink in ~/.config/fish/completions/
		Checks if already installed to avoid duplicates and asks for confirmation.

		<light_cyan>show [bash|zsh|fish]</light_cyan>
		Outputs the shell completion script content for manual installation.

		<light_cyan>cache-clear</light_cyan>
		Clears the completion cache directory (~/.slic/cache/completions/).

	EXAMPLES:

		<light_cyan>{$cli_name} completion</light_cyan>
		Shows installation instructions for your current shell.

		<light_cyan>{$cli_name} completion install</light_cyan>
		Auto-detects shell and installs completions.

		<light_cyan>{$cli_name} completion install bash</light_cyan>
		Installs bash completions.

		<light_cyan>{$cli_name} completion show zsh</light_cyan>
		Displays the zsh completion script.

		<light_cyan>{$cli_name} completion cache-clear</light_cyan>
		Clears the completion cache.
	HELP;

	echo colorize( $help );
	return;
}

// Parse subcommand and shell arguments
$completion_args = args( [ 'subcommand', 'shell' ], $args( '...' ), 0 );
$subcommand      = $completion_args( 'subcommand', null );
$shell_arg       = $completion_args( 'shell', null );

/**
 * Detects the current shell from the SHELL environment variable.
 *
 * @return string|null The detected shell name (bash, zsh, fish) or null if not detected.
 */
function detect_shell() {
	$shell_path = getenv( 'SHELL' );

	if ( empty( $shell_path ) ) {
		return null;
	}

	$shell_name = basename( $shell_path );

	// Map shell names to supported types
	$supported_shells = [ 'bash', 'zsh', 'fish' ];

	foreach ( $supported_shells as $supported ) {
		if ( strpos( $shell_name, $supported ) !== false ) {
			return $supported;
		}
	}

	return null;
}

/**
 * Gets the path to the slic completions directory.
 *
 * @return string The absolute path to the completions directory.
 */
function get_completions_dir() {
	return SLIC_ROOT_DIR . '/completions';
}

/**
 * Gets the path to the completion script for a specific shell.
 *
 * @param string $shell The shell type (bash, zsh, fish).
 *
 * @return string|null The absolute path to the completion script, or null if not found.
 */
function get_completion_script_path( $shell ) {
	$paths = [
		'bash' => get_completions_dir() . '/bash/slic.bash',
		'zsh'  => get_completions_dir() . '/zsh/_slic',
		'fish' => get_completions_dir() . '/fish/slic.fish',
	];

	if ( ! isset( $paths[ $shell ] ) ) {
		return null;
	}

	$path = $paths[ $shell ];

	return file_exists( $path ) ? $path : null;
}

/**
 * Displays installation instructions for a specific shell.
 *
 * @param string $shell The shell type (bash, zsh, fish).
 */
function show_install_instructions( $shell ) {
	$completions_dir = get_completions_dir();

	echo colorize( PHP_EOL . "<yellow>Installation instructions for {$shell}:</yellow>" . PHP_EOL . PHP_EOL );

	switch ( $shell ) {
		case 'bash':
			echo colorize( "Add the following to your <light_cyan>~/.bashrc</light_cyan> or <light_cyan>~/.bash_profile</light_cyan>:" . PHP_EOL . PHP_EOL );
			echo colorize( "<light_cyan># slic completions</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>if command -v slic &> /dev/null; then</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    _slic_path=\$(command -v slic 2>/dev/null)</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    if [[ -L \"\$_slic_path\" ]]; then</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>        _slic_path=\$(realpath \"\$_slic_path\" 2>/dev/null || readlink -f \"\$_slic_path\" 2>/dev/null || readlink \"\$_slic_path\" 2>/dev/null)</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    fi</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    source \"\$(dirname \"\$_slic_path\")/completions/bash/slic.bash\"</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>fi</light_cyan>" . PHP_EOL );
			break;

		case 'zsh':
			echo colorize( "Add the following to your <light_cyan>~/.zshrc</light_cyan>:" . PHP_EOL . PHP_EOL );
			echo colorize( "<light_cyan># slic completions</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>if command -v slic &> /dev/null; then</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    _slic_path=\$(command -v slic 2>/dev/null)</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    if [[ -L \"\$_slic_path\" ]]; then</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>        _slic_path=\$(realpath \"\$_slic_path\" 2>/dev/null || readlink -f \"\$_slic_path\" 2>/dev/null || readlink \"\$_slic_path\" 2>/dev/null)</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    fi</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    fpath=(\"\$(dirname \"\$_slic_path\")/completions/zsh\" \$fpath)</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    autoload -Uz compinit && compinit</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>fi</light_cyan>" . PHP_EOL );
			break;

		case 'fish':
			echo colorize( "Option 1: Create a symlink (recommended):" . PHP_EOL . PHP_EOL );
			echo colorize( "<light_cyan>mkdir -p ~/.config/fish/completions</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>ln -s {$completions_dir}/fish/slic.fish ~/.config/fish/completions/slic.fish</light_cyan>" . PHP_EOL );
			echo colorize( PHP_EOL . "Option 2: Add to your <light_cyan>~/.config/fish/config.fish</light_cyan>:" . PHP_EOL . PHP_EOL );
			echo colorize( "<light_cyan># slic completions</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>if type -q slic</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>    source (dirname (realpath (which slic)))/completions/fish/slic.fish</light_cyan>" . PHP_EOL );
			echo colorize( "<light_cyan>end</light_cyan>" . PHP_EOL );
			break;
	}

	echo PHP_EOL;
	echo colorize( "After adding the configuration, reload your shell or run:" . PHP_EOL );

	switch ( $shell ) {
		case 'bash':
			echo colorize( "<light_cyan>source ~/.bashrc</light_cyan>" . PHP_EOL );
			break;
		case 'zsh':
			echo colorize( "<light_cyan>source ~/.zshrc</light_cyan>" . PHP_EOL );
			break;
		case 'fish':
			echo colorize( "<light_cyan>source ~/.config/fish/config.fish</light_cyan>" . PHP_EOL );
			break;
	}
}

/**
 * Checks if completions are already installed for a specific shell.
 *
 * @param string $shell The shell type (bash, zsh, fish).
 *
 * @return bool True if completions are already installed.
 */
function is_installed( $shell ) {
	$home = getenv( 'HOME' );

	if ( empty( $home ) ) {
		return false;
	}

	switch ( $shell ) {
		case 'bash':
			$files = [ "$home/.bashrc", "$home/.bash_profile" ];
			foreach ( $files as $file ) {
				if ( file_exists( $file ) ) {
					$content = file_get_contents( $file );
					if ( strpos( $content, 'slic.bash' ) !== false || strpos( $content, 'completions/bash' ) !== false ) {
						return true;
					}
				}
			}
			return false;

		case 'zsh':
			$file = "$home/.zshrc";
			if ( file_exists( $file ) ) {
				$content = file_get_contents( $file );
				return strpos( $content, '_slic' ) !== false || strpos( $content, 'completions/zsh' ) !== false;
			}
			return false;

		case 'fish':
			// Check for symlink or file in fish completions directory
			// Use is_link() to detect symlinks even if broken
			$fish_completion = "$home/.config/fish/completions/slic.fish";
			if ( file_exists( $fish_completion ) || is_link( $fish_completion ) ) {
				return true;
			}
			// Check config.fish
			$config_file = "$home/.config/fish/config.fish";
			if ( file_exists( $config_file ) ) {
				$content = file_get_contents( $config_file );
				return strpos( $content, 'slic.fish' ) !== false || strpos( $content, 'completions/fish' ) !== false;
			}
			return false;
	}

	return false;
}

/**
 * Installs completions for a specific shell.
 *
 * @param string $shell The shell type (bash, zsh, fish).
 */
function install_completions( $shell ) {
	$home = getenv( 'HOME' );

	if ( empty( $home ) ) {
		echo colorize( "<red>Error: HOME environment variable not set.</red>" . PHP_EOL );
		exit( 1 );
	}

	// Check if already installed
	if ( is_installed( $shell ) ) {
		echo colorize( "<yellow>Completions for {$shell} appear to be already installed.</yellow>" . PHP_EOL );
		echo colorize( "If they're not working, try running <light_cyan>{$GLOBALS['cli_name']} completion cache-clear</light_cyan>" . PHP_EOL );
		return;
	}

	$completions_dir = get_completions_dir();

	// Verify completion script exists
	$script_path = get_completion_script_path( $shell );
	if ( empty( $script_path ) ) {
		echo colorize( "<red>Error: Completion script for {$shell} not found.</red>" . PHP_EOL );
		echo colorize( "Expected at: " . get_completions_dir() . "/{$shell}/" . PHP_EOL );
		exit( 1 );
	}

	echo colorize( PHP_EOL . "<yellow>This will install {$shell} completions for slic.</yellow>" . PHP_EOL );

	switch ( $shell ) {
		case 'bash':
			$target_file = file_exists( "$home/.bashrc" ) ? "$home/.bashrc" : "$home/.bash_profile";
			echo colorize( "This will add completion source code to: <light_cyan>{$target_file}</light_cyan>" . PHP_EOL );
			break;
		case 'zsh':
			$target_file = "$home/.zshrc";
			echo colorize( "This will add completion configuration to: <light_cyan>{$target_file}</light_cyan>" . PHP_EOL );
			break;
		case 'fish':
			$target_dir  = "$home/.config/fish/completions";
			$target_file = "$target_dir/slic.fish";
			echo colorize( "This will create a symlink at: <light_cyan>{$target_file}</light_cyan>" . PHP_EOL );
			break;
	}

	echo PHP_EOL;

	$confirm = ask( "Continue with installation?", 'yes' );

	if ( ! $confirm ) {
		echo colorize( "<yellow>Installation cancelled.</yellow>" . PHP_EOL );
		return;
	}

	echo PHP_EOL;

	switch ( $shell ) {
		case 'bash':
			// Check write permissions before modifying file
			if ( file_exists( $target_file ) && ! is_writable( $target_file ) ) {
				echo colorize( "<red>Error: {$target_file} is not writable.</red>" . PHP_EOL );
				echo colorize( "Please check file permissions and try again." . PHP_EOL );
				exit( 1 );
			}

			$config = "\n# slic completions\n";
			$config .= "if command -v slic &> /dev/null; then\n";
			$config .= "    _slic_path=\$(command -v slic 2>/dev/null)\n";
			$config .= "    if [[ -L \"\$_slic_path\" ]]; then\n";
			$config .= "        _slic_path=\$(realpath \"\$_slic_path\" 2>/dev/null || readlink -f \"\$_slic_path\" 2>/dev/null || readlink \"\$_slic_path\" 2>/dev/null)\n";
			$config .= "    fi\n";
			$config .= "    source \"\$(dirname \"\$_slic_path\")/completions/bash/slic.bash\"\n";
			$config .= "fi\n";

			if ( file_put_contents( $target_file, $config, FILE_APPEND ) === false ) {
				echo colorize( "<red>Error: Failed to write to {$target_file}</red>" . PHP_EOL );
				exit( 1 );
			}

			echo colorize( "<green>✓ Bash completions installed successfully!</green>" . PHP_EOL );
			echo colorize( "Run <light_cyan>source {$target_file}</light_cyan> to activate them in this session." . PHP_EOL );
			break;

		case 'zsh':
			// Check write permissions before modifying file
			if ( file_exists( $target_file ) && ! is_writable( $target_file ) ) {
				echo colorize( "<red>Error: {$target_file} is not writable.</red>" . PHP_EOL );
				echo colorize( "Please check file permissions and try again." . PHP_EOL );
				exit( 1 );
			}

			$config = "\n# slic completions\n";
			$config .= "if command -v slic &> /dev/null; then\n";
			$config .= "    _slic_path=\$(command -v slic 2>/dev/null)\n";
			$config .= "    if [[ -L \"\$_slic_path\" ]]; then\n";
			$config .= "        _slic_path=\$(realpath \"\$_slic_path\" 2>/dev/null || readlink -f \"\$_slic_path\" 2>/dev/null || readlink \"\$_slic_path\" 2>/dev/null)\n";
			$config .= "    fi\n";
			$config .= "    fpath=(\"\$(dirname \"\$_slic_path\")/completions/zsh\" \$fpath)\n";
			$config .= "    autoload -Uz compinit && compinit\n";
			$config .= "fi\n";

			if ( file_put_contents( $target_file, $config, FILE_APPEND ) === false ) {
				echo colorize( "<red>Error: Failed to write to {$target_file}</red>" . PHP_EOL );
				exit( 1 );
			}

			echo colorize( "<green>✓ Zsh completions installed successfully!</green>" . PHP_EOL );
			echo colorize( "Run <light_cyan>source {$target_file}</light_cyan> to activate them in this session." . PHP_EOL );
			break;

		case 'fish':
			// Create completions directory if it doesn't exist
			if ( ! is_dir( $target_dir ) ) {
				if ( ! mkdir( $target_dir, 0755, true ) ) {
					echo colorize( "<red>Error: Failed to create directory {$target_dir}</red>" . PHP_EOL );
					exit( 1 );
				}
			}

			// Check write permissions for the target directory
			if ( ! is_writable( $target_dir ) ) {
				echo colorize( "<red>Error: Directory {$target_dir} is not writable.</red>" . PHP_EOL );
				echo colorize( "Please check directory permissions and try again." . PHP_EOL );
				exit( 1 );
			}

			// Check if symlink or file already exists
			if ( file_exists( $target_file ) || is_link( $target_file ) ) {
				echo colorize( PHP_EOL . "<yellow>A file or symlink already exists at {$target_file}</yellow>" . PHP_EOL );
				$replace = ask( "Do you want to replace it?", 'yes' );

				if ( ! $replace ) {
					echo colorize( "<yellow>Installation cancelled.</yellow>" . PHP_EOL );
					return;
				}

				// Remove existing file/symlink
				if ( ! unlink( $target_file ) ) {
					echo colorize( "<red>Error: Failed to remove existing file at {$target_file}</red>" . PHP_EOL );
					exit( 1 );
				}
			}

			// Create symlink
			if ( ! symlink( $script_path, $target_file ) ) {
				echo colorize( "<red>Error: Failed to create symlink at {$target_file}</red>" . PHP_EOL );
				exit( 1 );
			}

			echo colorize( "<green>✓ Fish completions installed successfully!</green>" . PHP_EOL );
			echo colorize( "Completions should be available immediately in new fish sessions." . PHP_EOL );
			break;
	}
}

/**
 * Shows the completion script for a specific shell.
 *
 * @param string $shell The shell type (bash, zsh, fish).
 */
function show_completion_script( $shell ) {
	$script_path = get_completion_script_path( $shell );

	if ( empty( $script_path ) ) {
		echo colorize( "<red>Error: Completion script for {$shell} not found.</red>" . PHP_EOL );
		exit( 1 );
	}

	echo colorize( "<yellow>Completion script for {$shell}:</yellow>" . PHP_EOL );
	echo colorize( "<yellow>File: {$script_path}</yellow>" . PHP_EOL . PHP_EOL );
	echo file_get_contents( $script_path );
}

/**
 * Clears the completion cache.
 */
function clear_completion_cache() {
	// Use the same cache path logic as CompletionCache.php
	$base_dir = getenv( 'SLIC_CACHE_DIR' );
	if ( false === $base_dir || empty( $base_dir ) ) {
		$home = getenv( 'HOME' );
		if ( empty( $home ) ) {
			echo colorize( "<red>Error: HOME environment variable not set.</red>" . PHP_EOL );
			exit( 1 );
		}
		$base_dir = $home . '/.slic/cache';
	}
	$cache_dir = $base_dir . '/completions';

	if ( ! is_dir( $cache_dir ) ) {
		echo colorize( "<yellow>Completion cache directory does not exist.</yellow>" . PHP_EOL );
		echo colorize( "Cache dir: {$cache_dir}" . PHP_EOL );
		return;
	}

	echo colorize( "Clearing completion cache at: <light_cyan>{$cache_dir}</light_cyan>" . PHP_EOL );

	// Remove all files in the cache directory
	$files = glob( $cache_dir . '/*' );
	$count = 0;

	if ( ! empty( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
				$count++;
			}
		}
	}

	echo colorize( "<green>✓ Cleared {$count} cached completion file(s).</green>" . PHP_EOL );
}

// Main command logic
if ( $subcommand === 'cache-clear' ) {
	clear_completion_cache();
	return;
}

if ( $subcommand === 'show' ) {
	$shell = $shell_arg;

	if ( empty( $shell ) ) {
		$shell = detect_shell();

		if ( empty( $shell ) ) {
			echo colorize( "<red>Error: Could not auto-detect shell.</red>" . PHP_EOL );
			echo colorize( "Please specify shell explicitly: <light_cyan>{$cli_name} completion show [bash|zsh|fish]</light_cyan>" . PHP_EOL );
			exit( 1 );
		}

		echo colorize( "Auto-detected shell: <yellow>{$shell}</yellow>" . PHP_EOL );
	}

	if ( ! in_array( $shell, [ 'bash', 'zsh', 'fish' ] ) ) {
		echo colorize( "<red>Error: Unsupported shell '{$shell}'.</red>" . PHP_EOL );
		echo colorize( "Supported shells: bash, zsh, fish" . PHP_EOL );
		exit( 1 );
	}

	show_completion_script( $shell );
	return;
}

if ( $subcommand === 'install' ) {
	$shell = $shell_arg;

	if ( empty( $shell ) ) {
		$shell = detect_shell();

		if ( empty( $shell ) ) {
			echo colorize( "<red>Error: Could not auto-detect shell.</red>" . PHP_EOL );
			echo colorize( "Please specify shell explicitly: <light_cyan>{$cli_name} completion install [bash|zsh|fish]</light_cyan>" . PHP_EOL );
			exit( 1 );
		}

		echo colorize( "Auto-detected shell: <yellow>{$shell}</yellow>" . PHP_EOL );
	}

	if ( ! in_array( $shell, [ 'bash', 'zsh', 'fish' ] ) ) {
		echo colorize( "<red>Error: Unsupported shell '{$shell}'.</red>" . PHP_EOL );
		echo colorize( "Supported shells: bash, zsh, fish" . PHP_EOL );
		exit( 1 );
	}

	install_completions( $shell );
	return;
}

// Default: show installation instructions
$shell = detect_shell();

if ( empty( $shell ) ) {
	echo colorize( "<yellow>Could not auto-detect your shell.</yellow>" . PHP_EOL );
	echo colorize( PHP_EOL . "Please use one of the following commands:" . PHP_EOL );
	echo colorize( "  <light_cyan>{$cli_name} completion install bash</light_cyan>" . PHP_EOL );
	echo colorize( "  <light_cyan>{$cli_name} completion install zsh</light_cyan>" . PHP_EOL );
	echo colorize( "  <light_cyan>{$cli_name} completion install fish</light_cyan>" . PHP_EOL );
	exit( 0 );
}

echo colorize( "Detected shell: <yellow>{$shell}</yellow>" . PHP_EOL );

if ( is_installed( $shell ) ) {
	echo colorize( PHP_EOL . "<green>✓ Completions for {$shell} are already installed!</green>" . PHP_EOL );
	echo colorize( PHP_EOL . "If completions are not working, try:" . PHP_EOL );
	echo colorize( "  1. <light_cyan>{$cli_name} completion cache-clear</light_cyan> - Clear the completion cache" . PHP_EOL );
	echo colorize( "  2. Reload your shell configuration" . PHP_EOL );
} else {
	show_install_instructions( $shell );
	echo colorize( PHP_EOL . "Or run <light_cyan>{$cli_name} completion install</light_cyan> to install automatically." . PHP_EOL );
}
