<?php
/**
 * Slurp Functions
 *
 * @package     ArrayPress/Utils/Slurp
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\Utils;

use Exception;

if ( ! function_exists( 'slurp' ) ) {
	/**
	 * Creates a Slurp instance with try-catch error handling.
	 *
	 * Initializes the Slurp class to include files from a specified directory
	 * within a WordPress plugin, with options for subdirectories, global callbacks,
	 * and file exclusions. Returns null on failure, typically when WordPress functions
	 * aren't available or on other exceptions.
	 *
	 * Example:
	 * $slurper = slurp('/path/to/plugin', 'includes');
	 * $slurper->include('subdir');
	 *
	 * @param string        $pluginFile     Full path to the main plugin file.
	 * @param string        $subDir         Subdirectory path relative to the plugin directory.
	 * @param callable|null $globalCallback Optional global callback for conditional file inclusion.
	 * @param array         $excludedFiles  Array of filenames to exclude from inclusion.
	 *
	 * @return Slurp|null The initialized Slurp instance or null on failure.
	 */
	function slurp( string $pluginFile, string $subDir = 'includes', ?callable $globalCallback = null, array $excludedFiles = [ 'index.php' ] ): ?Slurp {
		try {
			// Check if WordPress functions are available
			if ( ! function_exists( 'plugin_dir_path' ) || ! function_exists( 'trailingslashit' ) ) {
				throw new Exception( 'WordPress functions are not available. Make sure this code is executed within a WordPress environment.' );
			}

			$baseDir = trailingslashit( plugin_dir_path( $pluginFile ) ) . $subDir;

			return new Slurp( $baseDir, $globalCallback, $excludedFiles );
		} catch ( Exception $e ) {
			// Handle the exception or log it if needed
			return null; // Return null on failure
		}
	}
}
