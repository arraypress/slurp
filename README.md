# Slurp Library

The Slurp library provides a powerful and efficient solution for dynamically including PHP files in WordPress or general
PHP projects. It supports recursive file inclusion, conditional inclusion via callbacks, and offers robust debugging
capabilities by tracking and logging included files. Ideal for organized and efficient file management in plugin or
application development.

## Features ##

* **Dynamic File Inclusion:** Automatically include PHP files from specified directories, streamlining project setup and
  maintenance.
* **Recursive Inclusion:** Capable of including files from subdirectories recursively, ensuring no file is left behind.
* **Conditional Inclusion:** Utilize callbacks to conditionally include files based on custom logic, such as user roles
  or environment settings.
* **Global Callbacks:** Set a global callback for file inclusion, applying a single condition across all included files.
* **Exclusion Capability:** Specify files to exclude from inclusion, allowing for precise control over which files are
  loaded.
* **Debugging Support:** Dump a list of all included files to a file, aiding in debugging and ensuring transparency in
  file management.
* **Flexible Usage:** Designed for both WordPress and general PHP projects, offering versatility across different
  development environments.
* **Helper Function:** Provides a `slurp` helper function to simplify usage within WordPress, making it easy to
  integrate into plugins or themes.

## Minimum Requirements ##

* **PHP:** 7.4

## Installation ##

Slurp is a developer library, not a plugin, which means you need to include it somewhere in your own
project.

You can use Composer:

```bash
composer require arraypress/slurp
```

#### Basic File Inclusion

```php
// Require the Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// 
Use ArrayPress\Utils\Slurp;

$slurp = new Slurp( __DIR__ ); // Create a Slurp instance for a specific directory.
$slurp->include( 'subdirectory' ); // Include all PHP files from the specified directory.
```

#### Recursive File Inclusion

```php
$slurp->include( 'subdirectory', true ) ; // Include files from a directory and all its subdirectories.
```

#### Multiple Directory Inclusion

```php
$slurp->include( [ 'subdirectory1', 'subdirectory2' ] );
```

#### Conditional File Inclusion with Callback

```php
$slurp->include( [
    'subdirectory' => function( $filePath ) {
        // Only include files that contain 'module' in their name.
        return strpos( $filePath, 'module' ) !== false;
    }
] );
```

#### Conditional File Inclusion with Global Callback

```php
// Instantiate the Slurp class, setting the base directory and providing a global callback.
// The global callback uses is_admin() to determine if the file should be included,
// which is useful for admin-only features.
$slurp = new Slurp( __DIR__, function( $filePath ) {
    return is_admin(); // Global callback to include files only in the WordPress admin area.
} );

// Include PHP files from a specific directory. Thanks to the global callback,
// files will only be included if is_admin() returns true.
$slurp->include( [ 'admin', 'reports' ] );
```

#### Excluding Specific Files

```php
// Exclude specific files from being included.
$slurp = new Slurp( __DIR__, null, [ 'index.php', 'exclude.php' ] );
$slurp->include(); // Include all other PHP files from the directory.
```

Or

```php
$slurp->set_excluded( ['exclude.php'] ); // Exclude specific files from being included.
```

#### Overriding Global Callback

```php
$slurp->set_callback( function( $filePath ) {
    return is_admin(); // Global callback to include files only in the WordPress admin area.
} ); // Include all other PHP files from the directory.
```

#### Dumping Loaded Files for Debugging

```php
$slurp->dumpFiles( 'debug.txt' ); // Dump the list of included files to a text file for debugging.
```

#### Retrieving List of Loaded Files

```php
$loadedFiles = $slurp->getFiles(); // Get an array of all the PHP files that have been included.
echo '<pre>';
print_r( $loadedFiles );
echo '</pre>'; // Display the loaded files (for example purposes).
```

#### Including Files from Multiple Directories

```php
// Assuming the Slurp class is already autoloaded via Composer
$slurp = new Slurp( __DIR__ );

// Include files from multiple directories.
$directories = ['directory1', 'directory2', 'directory3'];
$slurp->include( $directories );

// You can also include files recursively from these directories
$slurp->include( $directories, true );

// Or include files based on a callback condition for each directory
$slurp->include( [
    'directory1' => function( $file ) { return strpos( $file, 'condition1' ) !== false; },
    'directory2' => function( $file ) { return strpos( $file, 'condition2' ) !== false; },
    'directory3' => null // Include all files from directory3
] );
```

#### Using the Helper Function in WordPress

##### Example 1: Including Files for Admin Pages Only

This example demonstrates how to include files from a specified directory only if the WordPress admin interface is being
accessed. This can be useful for loading admin-specific functionalities.

```php
add_action( 'admin_init', function() {
    $slurp = slurp(
        __DIR__ . '/admin', // Base directory targeted towards admin-related files
        '', // Include directly from the base directory
        false, // Non-recursive inclusion
        function ( $filePath ) {
            return is_admin(); // Global callback to ensure files are included only in admin context
        }
    );
    // Assume further setup or actions are taken with $slurp if needed
} );
```

##### Example 2: Including Plugin Core Files with Recursion

This example shows how to include all PHP files within the 'includes' directory of a plugin, doing so recursively to
ensure that files in subdirectories are also loaded.

```php
$slurp = slurp(
    __DIR__, // Plugin root directory
    'dev-tools', // Subdirectory containing development tools
    true, // Recursive inclusion to get all tools
    function ( $filePath ) {
        return defined('WP_DEBUG') && WP_DEBUG; // Only include if WP_DEBUG is true
    }
);
```

##### Example 3: Conditional Inclusion Based on Site Context

This example uses a global callback to conditionally include files based on whether the site is in a development
environment. This is useful for loading debug tools or additional resources that should not be present in production.

```php
$slurp = slurp(
    __DIR__, // Plugin root directory
    'dev-tools', // Subdirectory containing development tools
    true, // Recursive inclusion to get all tools
    function ( $filePath ) {
        return defined('WP_DEBUG') && WP_DEBUG; // Only include if WP_DEBUG is true
    }
);
// Development tools are loaded if the site is in debug mode
```

##### Example 4: Excluding Specific Files from Inclusion

In some cases, you might want to exclude specific files from being included, such as example files or documentation.
This example demonstrates how to use the `excludedFiles` parameter for this purpose.

```php
$slurp = slurp(
    __DIR__ . '/includes', // Base directory for inclusion
    '', // No specific subdirectory, include directly from the base
    false, // Non-recursive inclusion
    null, // No global callback, include all files
    [ 'example.php', 'readme.md' ] // Exclude example and readme files from inclusion
);
// Only desired files are included, excluding the specified ones
```

##### Example 5: Error Handling with Callback

This example demonstrates how to use the `errorCallback` parameter for error handling, which can be particularly useful
for logging errors or handling them in a specific manner within your WordPress environment.

```php
$slurp = slurp(
    __DIR__ . '/critical', // Base directory for critical functionalities
    '', // Include directly from the base directory
    false, // Non-recursive inclusion
    null, // No global callback, include all files
    [], // No files are excluded
    function ( $e ) {
        // Log error or handle it accordingly
        error_log( 'Error loading critical files: ' . $e->getMessage() );
    }
);
// Critical functionalities are attempted to be loaded, with error handling in place
```

## Contributions

Contributions to this library are highly appreciated. Raise issues on GitHub or submit pull requests for bug
fixes or new features. Share feedback and suggestions for improvements.

## License: GPLv2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.