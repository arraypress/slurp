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
use function array_fill_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function in_array;
use function is_array;
use function is_callable;
use function is_dir;
use function is_null;
use function is_string;
use function is_writable;
use function print_r;
use function random_bytes;
use function realpath;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

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
		 * A list of directories that are allowed for file inclusion.
		 *
		 * @var array
		 */
		private array $allowedBaseDirs = [];

		/**
		 * Maintains a list of directories allowed for file inclusion to ensure security.
		 *
		 * @var array
		 */
		private array $whitelist = [];

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
		 * Automatically detects if the provided path is a file and uses its directory.
		 * This method allows changing the base directory after the object has been instantiated.
		 *
		 * @param string $baseDir New base directory path or file path.
		 *
		 * @throws InvalidArgumentException If the provided base directory is invalid.
		 */
		public function setBaseDir( string $baseDir ): void {
			// Check if the path is a file, if so, get the directory of the file
			if ( ! is_dir( $baseDir ) ) {
				$baseDir = dirname( $baseDir );
			}

			// Validate the new base directory
			$this->validateDir( $baseDir );

			// Normalize and set the new base directory
			$this->baseDir = self::trailingSlashIt( $baseDir );
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

		/** Exclusions ****************************************************************/

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
		 * Returns the list of filenames currently excluded from inclusion.
		 * This method provides access to the filenames that have been marked to be skipped during the file inclusion process.
		 * It's useful for debugging or managing the exclusion list dynamically.
		 *
		 * @return array An array of filenames that are excluded from inclusion.
		 */
		public function getExcluded(): array {
			return $this->excludedFiles;
		}

		/** Allowed Base Directories **************************************************/

		/**
		 * Adds a directory to the list of allowed base directories for file inclusion.
		 * This method checks if the specified directory exists and normalizes its path
		 * before adding it to the list of allowed directories. If the directory does not exist,
		 * an InvalidArgumentException is thrown.
		 *
		 * @param string $dir The directory to be added to the list of allowed base directories.
		 *
		 * @throws InvalidArgumentException If the specified directory does not exist.
		 */
		public function addAllowedBaseDir( string $dir ) {
			if ( is_dir( $dir ) ) {
				$this->allowedBaseDirs[] = realpath( $dir );
			} else {
				throw new InvalidArgumentException( "Directory $dir does not exist." );
			}
		}

		/**
		 * Checks if a given path is within the allowed base directories.
		 * This method resolves the real path of the given file or directory and checks
		 * if it starts with any of the allowed base directories' paths. This is used to
		 * ensure that file inclusion is restricted to approved directories, enhancing security.
		 *
		 * @param string $path The path to check against the list of allowed base directories.
		 *
		 * @return bool True if the path is within one of the allowed base directories, false otherwise.
		 */
		private function isWithinAllowedDirs( string $path ): bool {
			$realPath = realpath( $path );
			foreach ( $this->allowedBaseDirs as $allowedDir ) {
				if ( strpos( $realPath, $allowedDir ) === 0 ) {
					return true;
				}
			}

			return false;
		}

		/** Whitelist *****************************************************************/

		/**
		 * Adds a directory to the whitelist, ensuring files can be included from this location.
		 *
		 * @param string $path The directory path to add to the whitelist.
		 */
		public function addToWhitelist( string $path ) {
			$this->whitelist[] = realpath( $path );
		}

		/**
		 * Checks if a given path is in the whitelist, allowing for file inclusion.
		 *
		 * @param string $path The file or directory path to check against the whitelist.
		 *
		 * @return bool Returns true if the path is in the whitelist, false otherwise.
		 */
		private function isInWhitelist( string $path ): bool {
			return in_array( realpath( $path ), $this->whitelist );
		}

		/** Core **********************************************************************/

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
			} elseif ( is_array( $dirs ) && array_values( $dirs ) === $dirs ) { // Check if it's an indexed array
				// Convert indexed array to an associative array with global callback
				$dirs = array_fill_keys( $dirs, $this->globalCallback );
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
			$dir = $this->sanitizePath( $dir );

			if ( ! is_dir( $dir ) ) {
				return;
			}

			// Perform checks only if allowedBaseDirs or whitelist has been populated
			if ( ! empty( $this->allowedBaseDirs ) && ! $this->isWithinAllowedDirs( $dir ) ) {
				throw new InvalidArgumentException( "Attempting to include files from an unauthorized directory: $dir" );
			}

			if ( ! empty( $this->whitelist ) && ! $this->isInWhitelist( $dir ) ) {
				throw new InvalidArgumentException( "Attempting to include files from an unauthorized directory: $dir" );
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

		/** Debugging *****************************************************************/

		/**
		 * Retrieve the list of all loaded files.
		 *
		 * @return array List of loaded file paths.
		 */
		public function getFiles(): array {
			return $this->loadedFiles;
		}

		/**
		 * Generates a file name for dumping loaded files.
		 * If a file name is provided, it validates the file extension.
		 * If no file name is provided, it generates a random file name with a .txt extension.
		 *
		 * @param string $fileName Optional. The base name for the dump file.
		 *
		 * @return string The generated or validated file name with a .txt extension.
		 * @throws InvalidArgumentException If the file name extension is not .txt.
		 */
		private function generateDumpFileName( string $fileName = '' ): string {
			if ( empty( $fileName ) ) {
				return 'loaded_files_' . bin2hex( random_bytes( 8 ) ) . '.txt';
			} elseif ( ! $this->endsWith( $fileName, '.txt' ) ) {
				throw new InvalidArgumentException( 'The dump file name must end with .txt' );
			}

			return $fileName;
		}

		/**
		 * Dumps the list of all loaded files to a file in the base directory.
		 * Uses the generateDumpFileName method to handle file name generation.
		 * Checks if the location is writable before dumping the files.
		 *
		 * @param string $dumpFileName Optional. Name of the file to dump the list of loaded files.
		 *
		 * @throws InvalidArgumentException If the location is not writable or the file name is invalid.
		 */
		public function dumpFiles( string $dumpFileName = '' ): void {
			$dumpFileName = $this->generateDumpFileName( $dumpFileName );
			$dumpFilePath = $this->baseDir . $dumpFileName;

			// Check if the location is writable
			if ( ! is_writable( dirname( $dumpFilePath ) ) ) {
				throw new InvalidArgumentException( 'The specified directory is not writable' );
			}

			file_put_contents( $dumpFilePath, print_r( $this->loadedFiles, true ) );
		}

		/** Helpers *******************************************************************/

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
		 * Adds a trailing slash to a file path for uniformity.
		 *
		 * Ensures that all paths have a consistent ending with a trailing slash,
		 * which is useful for constructing file paths that are meant to be directories.
		 * This method normalizes the path by adding a DIRECTORY_SEPARATOR at the end if it's not already present.
		 *
		 * @param string $path The file or directory path to normalize.
		 *
		 * @return string The normalized path with a trailing slash.
		 */
		private static function trailingSlashIt( string $path ): string {
			return rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		}

		/**
		 * Sanitizes a file path to ensure it is safe and normalized.
		 *
		 * This method normalizes directory separators to use forward slashes and removes potentially unsafe or
		 * unnecessary path segments like '../' or './'. It's designed to prevent directory traversal issues and
		 * ensure a consistent path format that can be safely used within the application.
		 *
		 * @param string $path The original file path to be sanitized.
		 *
		 * @return string The sanitized file path with normalized directory separators and removed unsafe segments.
		 */
		private function sanitizePath( string $path ): string {
			// Normalize directory separators and remove any ../ or ./ sequences
			$path = str_replace( [ '../', './' ], '', $path );

			// Replace backslashes (Windows paths) with forward slashes and ensure a consistent structure
			return str_replace( '\\', '/', $path );
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

	}
endif;