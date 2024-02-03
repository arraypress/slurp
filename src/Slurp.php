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
 * - Basic inclusion: `$slurp->include('classes');`
 * - Recursive inclusion: `$slurp->include('classes', true);`
 * - Conditional inclusion with callback: `$slurp->include(['classes' => function($filePath) { return ...; }]);`
 * - Dumping loaded files for debugging: `$slurp->dumpFiles('debug.txt');`
 * - Displaying loaded files: `$loadedFiles = $slurp->displayFiles();`
 *
 * @package     ArrayPress/Slurp
 * @copyright   Copyright (c) 2024, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\Utils;

use DirectoryIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
			$this->setBaseDir( $baseDir );
			$this->globalCallback = $globalCallback;
			$this->excludedFiles  = $excludedFiles;
		}

		/**
		 * Sets a new base directory for file inclusion.
		 * Validates the new base directory before setting it, ensuring it exists and is a directory.
		 * This method allows changing the base directory after the object has been instantiated.
		 *
		 * @param string $baseDir New base directory path.
		 *
		 * @throws InvalidArgumentException If the provided base directory is invalid.
		 */
		public function setBaseDir( string $baseDir ): void {
			$this->validateDir( $baseDir ); // Validate the new base directory
			$this->baseDir = self::trailingslashit( $baseDir ); // Set and normalize the new base directory
		}

		/**
		 * Validates the specified directory.
		 * Checks if the provided directory path is not empty and exists as a directory.
		 * Throws an InvalidArgumentException if the validation fails.
		 *
		 * @param string $dir The directory path to validate.
		 *
		 * @throws InvalidArgumentException If the directory is invalid (either empty or not existing).
		 */
		private function validateDir( string $dir ): void {
			if ( empty( $dir ) || ! is_dir( $dir ) ) {
				throw new InvalidArgumentException( 'Invalid base directory provided' );
			}
		}

		/**
		 * Adds filenames or an array of filenames to the list of excluded files.
		 * This method allows for dynamically excluding additional files from being included.
		 * If a string is provided, it is added to the list of exclusions. If an array is provided,
		 * it merges the array with the existing exclusions, ensuring all values are unique.
		 * Throws an InvalidArgumentException if the exclusions parameter is neither a string nor an array.
		 *
		 * @param string|array $exclusions The filename(s) to exclude.
		 *
		 * @throws InvalidArgumentException If exclusions is not a string or an array of strings.
		 */
		public function addExclusion( $exclusions ): void {
			if ( is_string( $exclusions ) ) {
				$this->excludedFiles[] = $exclusions;
			} elseif ( is_array( $exclusions ) ) {
				$this->excludedFiles = array_unique( array_merge( $this->excludedFiles, $exclusions ) );
			} else {
				throw new InvalidArgumentException( 'Exclusions must be a string or an array of strings.' );
			}
		}

		/**
		 * Set the list of filenames to be excluded from inclusion.
		 *
		 * @param array $files Array of filenames to exclude.
		 *
		 * @return void
		 * @throws InvalidArgumentException If any of the filenames is not a string.
		 */
		public function setExcluded( array $files ): void {
			foreach ( $files as $file ) {
				if ( ! is_string( $file ) ) {
					throw new InvalidArgumentException( 'All excluded files must be strings.' );
				}
			}
			$this->excludedFiles = $files;
		}

		/**
		 * Set the default global callback for file inclusion.
		 *
		 * @param callable|null $callback The callback function to set.
		 *
		 * @return void
		 * @throws InvalidArgumentException If the provided callback is not callable or null.
		 */
		public function setCallback( ?callable $callback ): void {
			if ( ! is_null( $callback ) && ! is_callable( $callback ) ) {
				throw new InvalidArgumentException( 'Provided callback is not callable.' );
			}
			$this->globalCallback = $callback;
		}

		/**
		 * Includes PHP files from specified directories.
		 * Handles the inclusion of PHP files from a given directory or an array of directories.
		 * Supports recursion and conditional inclusion using callbacks. Throws an InvalidArgumentException
		 * if the provided callback is not callable.
		 *
		 * Examples:
		 * - Include all files in a directory: $slurp->include('path/to/dir');
		 * - Include files recursively: $slurp->include('path/to/dir', true);
		 * - Include files based on a callback condition: $slurp->include(['path/to/dir' => function($file) { return strpos($file, 'test') !== false; }]);
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
		protected function processDirectory( string $relativeDir, bool $recursive, ?callable $callback ): void {
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
			} elseif ( ! $this->endsWith( $dumpFileName, '.txt' ) ) { // Use the custom endsWith function
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
		 * Checks if a string ends with a given substring.
		 *
		 * @param string $haystack The string to search in.
		 * @param string $needle   The substring to search for.
		 *
		 * @return bool Returns true if $haystack ends with $needle, false otherwise.
		 */
		private function endsWith( string $haystack, string $needle ): bool {
			$length = strlen( $needle );
			if ( $length == 0 ) {
				return true;
			}

			return ( substr( $haystack, - $length ) === $needle );
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