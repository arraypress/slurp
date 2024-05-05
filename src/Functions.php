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

namespace ArrayPress\Utils\Slurp;

use ArrayPress\Utils\Slurp\Classes\Slurp;
use Exception;
use function add_action;
use function call_user_func;
use function is_callable;
use function is_string;

if ( ! function_exists( 'slurp' ) ) {
	/**
	 * Function to instantiate the Slurp class and include files with optional recursion.
	 *
	 * @param string        $baseDir        Base directory for file inclusion.
	 * @param array|string  $filesDirs      Array or string of directory paths to include.
	 * @param bool          $recursive      Whether to include files recursively.
	 * @param callable|null $globalCallback A global callback function for file inclusion conditions.
	 * @param array         $excludedFiles  Array of filenames to exclude from inclusion.
	 * @param callable|null $errorCallback  A callback function for error handling.
	 *
	 * @return Slurp|null The initialized Slurp instance or null on failure.
	 */
	function slurp( string $baseDir = __DIR__, $filesDirs = [], bool $recursive = false, ?callable $globalCallback = null, array $excludedFiles = [ 'index.php' ], ?callable $errorCallback = null ): ?Slurp {
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

if ( ! function_exists( 'slurp_hooked' ) ) {
	/**
	 * Hook the slurp function into a specified WordPress action or filter.
	 *
	 * @param string        $hook           The name of the WordPress hook (action or filter).
	 * @param string        $baseDir        Base directory for file inclusion.
	 * @param array|string  $filesDirs      Array or string of directory paths to include.
	 * @param bool          $recursive      Whether to include files recursively.
	 * @param callable|null $globalCallback A global callback function for file inclusion conditions.
	 * @param array         $excludedFiles  Array of filenames to exclude from inclusion.
	 * @param int           $priority       Priority at which the function should be executed.
	 * @param int           $acceptedArgs   The number of arguments the function accepts.
	 */
	function slurp_hooked( string $hook, string $baseDir = __DIR__, $filesDirs = [], bool $recursive = false, ?callable $globalCallback = null, array $excludedFiles = [ 'index.php' ], int $priority = 10, int $acceptedArgs = 1 ): void {
		add_action( $hook, function () use ( $baseDir, $filesDirs, $recursive, $globalCallback, $excludedFiles ) {
			slurp( $baseDir, $filesDirs, $recursive, $globalCallback, $excludedFiles );
		}, $priority, $acceptedArgs );
	}
}