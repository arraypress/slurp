<?php
/**
 * Slurp Functions
 *
 * @package     ArrayPress/Slurp
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace {

	use ArrayPress\Utils\Slurp;

	if ( ! function_exists( 'slurp' ) ) {
		/**
		 * Function to instantiate the Slurp class and include files with optional recursion.
		 *
		 * @param array|string  $filesDirs      Array or string of directory paths to include.
		 * @param string        $baseDir        Base directory for file inclusion.
		 * @param bool          $recursive      Whether to include files recursively.
		 * @param callable|null $globalCallback A global callback function for file inclusion conditions.
		 * @param array         $excludedFiles  Array of filenames to exclude from inclusion.
		 * @param callable|null $errorCallback  A callback function for error handling.
		 *
		 * @return Slurp|null The initialized Slurp instance or null on failure.
		 */
		function slurp( $filesDirs = [], string $baseDir = __DIR__, bool $recursive = false, ?callable $globalCallback = null, array $excludedFiles = [ 'index.php' ], ?callable $errorCallback = null ): ?Slurp {
			try {
				$slurp = new Slurp( $baseDir, $globalCallback, $excludedFiles );

				// Normalize $filesDirs to an array if it's a string.
				$dirs = is_string( $filesDirs ) ? [ $filesDirs ] : $filesDirs;

				// Include files from the specified directories.
				foreach ( $dirs as $dir ) {
					$slurp->include( $dir, $recursive );
				}

				return $slurp;
			} catch ( Exception $e ) {
				if ( $errorCallback && is_callable( $errorCallback ) ) {
					call_user_func( $errorCallback, $e );
				}

				// Return null on failure if error callback is provided.
				return null;
			}
		}
	}
}