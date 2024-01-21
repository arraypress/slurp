<?php
/**
 * Slurp Class
 *
 * Handles dynamic inclusion of PHP files from specified directories with
 * support for optional recursion and conditional inclusion via callbacks.
 * It tracks and provides access to all included files. This class is
 * especially useful in WordPress and PHP projects for organized and
 * efficient file management. It features debugging capabilities, such as
 * dumping the list of included files into a specified file.
 *
 * Usage:
 * - Basic inclusion: `$slurper->include('classes');`
 * - Recursive inclusion: `$slurper->include('classes', true);`
 * - Conditional inclusion with callback: `$slurper->include(['classes' => function($filePath) { return ...; }]);`
 * - Dumping loaded files for debugging: `$slurper->dumpFiles('debug.txt');`
 * - Displaying loaded files: `$loadedFiles = $slurper->displayFiles();`
 *
 * @package     ArrayPress/Utils/Slurp
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\Utils;

use InvalidArgumentException,
	RecursiveIteratorIterator,
	RecursiveDirectoryIterator,
	DirectoryIterator;

if ( ! class_exists( 'Slurp' ) ) :

	class Slurp {

		/**
		 * Base directory for file inclusion.
		 * Represents the root directory from which files will be included.
		 * It's set in the constructor and used as the reference point for including files.
		 *
		 * @var string
		 */
		private string $baseDir;

		/**
		 * Array of loaded file paths.
		 * Maintains a record of all the PHP files that have been included using the class.
		 * This array is useful for debugging and tracking which files have been processed.
		 *
		 * @var array
		 */
		private array $loadedFiles = [];

		/**
		 * Global callback for file inclusion.
		 * This callback, if set, will be applied as a default condition for including files.
		 * It can be overridden by specific callbacks provided in the include method.
		 *
		 * @var callable|null
		 */
		private $globalCallback = null;

		/**
		 * List of filenames to be excluded from inclusion.
		 * This property holds an array of filenames that will be skipped during the file inclusion process.
		 * It is particularly useful to prevent the inclusion of unnecessary or system files like 'index.php'.
		 *
		 * @var array
		 */
		private array $excludedFiles;

		/**
		 * Constructor for the Slurp class.
		 * Initializes the Slurp class with the base directory for file inclusion, an optional global callback,
		 * and an array of filenames to exclude. The global callback is applied as a default condition for including files
		 * and can be overridden by specific callbacks provided in the include method. The excludedFiles array allows
		 * specifying filenames that should be skipped during the inclusion process.
		 *
		 * @param string        $baseDir        Base directory for file inclusion.
		 * @param callable|null $globalCallback Optional global callback for conditional file inclusion.
		 * @param array         $excludedFiles  Array of filenames to exclude from inclusion.
		 *
		 * @throws InvalidArgumentException If the provided base directory is invalid.
		 */
		public function __construct( string $baseDir = __DIR__, ?callable $globalCallback = null, array $excludedFiles = [ 'index.php' ] ) {
			if ( empty( $baseDir ) || ! is_dir( $baseDir ) ) {
				throw new InvalidArgumentException( 'Invalid base directory provided' );
			}
			$this->baseDir        = self::trailingslashit( $baseDir );
			$this->globalCallback = $globalCallback;  // Set the global callback
			$this->excludedFiles  = $excludedFiles;
		}

		/**
		 * Includes PHP files from specified directories.
		 * Handles the inclusion of PHP files from a given directory or an array of directories.
		 * Supports recursion and conditional inclusion using callbacks. Throws an InvalidArgumentException
		 * if the provided callback is not callable.
		 *
		 * Examples:
		 * - Include all files in a directory: $slurper->include('path/to/dir');
		 * - Include files recursively: $slurper->include('path/to/dir', true);
		 * - Include files based on a callback condition: $slurper->include(['path/to/dir' => function($file) { return strpos($file, 'test') !== false; }]);
		 *
		 * @param string|array|null $dirs      Single directory, an array of directories, or directory-callback pairs, or null for the base directory.
		 * @param bool              $recursive Whether to include files recursively.
		 */
		public function include( $dirs = null, bool $recursive = false ): void {
			if ( is_null( $dirs ) ) {
				$dirs = [ '' => $this->globalCallback ];
			} elseif ( is_string( $dirs ) ) {
				$dirs = [ $dirs => $this->globalCallback ];
			}

			foreach ( $dirs as $dir => $callback ) {
				$callback = $callback ?? $this->globalCallback;

				if ( ! is_callable( $callback ) && ! is_null( $callback ) ) {
					throw new InvalidArgumentException( 'Provided callback is not callable' );
				}

				$this->processDirectory( $dir, $recursive, $callback );
			}
		}

		/**
		 * Process a directory for file inclusion.
		 *
		 * @param string        $relativeDir Relative directory path.
		 * @param bool          $recursive   Whether to include files recursively.
		 * @param callable|null $callback    Optional callback for conditional inclusion.
		 */
		private function processDirectory( string $relativeDir, bool $recursive, ?callable $callback ): void {
			$dir = $this->baseDir . $relativeDir;

			if ( ! is_dir( $dir ) ) {
				return;
			}

			$iterator = $recursive
				? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir ) )
				: new DirectoryIterator( $dir );

			foreach ( $iterator as $fileInfo ) {
				if ( $fileInfo->isFile() && $fileInfo->getExtension() === 'php' ) {
					$filePath = $fileInfo->getPathname();
					$fileName = $fileInfo->getFilename();

					// Skip if file is in the excluded list
					if ( in_array( $fileName, $this->excludedFiles ) ) {
						continue;
					}

					if ( is_null( $callback ) || $callback( $filePath ) ) {
						require_once $filePath;
						$this->loadedFiles[] = $filePath;
					}
				}
			}
		}


		/**
		 * Retrieve the list of all loaded files.
		 *
		 * @return array List of loaded file paths.
		 */
		public function getFiles(): array {
			return $this->loadedFiles;
		}

		/**
		 * Dumps the list of all loaded files to a file in the base directory.
		 * Generates a random file name if none is provided. Validates the file extension
		 * and checks if the location is writable.
		 *
		 * @param string $dumpFileName Name of the file to dump the list of loaded files.
		 *
		 * @throws InvalidArgumentException If the file extension is not .txt or the location is not writable.
		 */
		public function dumpFiles( string $dumpFileName = '' ): void {
			// Generate a random file name if none is provided
			if ( empty( $dumpFileName ) ) {
				$dumpFileName = 'loaded_files_' . bin2hex( random_bytes( 8 ) ) . '.txt';
			} elseif ( ! str_ends_with( $dumpFileName, '.txt' ) ) {
				throw new InvalidArgumentException( 'The dump file name must end with .txt' );
			}

			$dumpFilePath = $this->baseDir . $dumpFileName;

			// Check if the location is writable
			if ( ! is_writable( dirname( $dumpFilePath ) ) ) {
				throw new InvalidArgumentException( 'The specified directory is not writable' );
			}

			file_put_contents( $dumpFilePath, print_r( $this->loadedFiles, true ) );
		}

		/**
		 * Adds a trailing slash to a file path.
		 *
		 * @param string $path File path.
		 *
		 * @return string Path with trailing slash.
		 */
		private static function trailingslashit( string $path ): string {
			return rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		}

	}
endif;