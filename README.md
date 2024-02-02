# Slurp Library

The Slurp library provides a powerful and efficient solution for dynamically including PHP files in WordPress or general
PHP projects. It supports recursive file inclusion, conditional inclusion via callbacks, and offers robust debugging
capabilities by tracking and logging included files. Ideal for organized and efficient file management in plugin or
application development.

## Features ##

* **Custom Post States:** Define your own indicators for posts.
* **Flexible Integration:** Seamlessly add this library to your WordPress project.
* **Callable Function:** Use a callable function to retrieve option values.
* **Quick Reference:** Instantly recognize the state of each post.

## Minimum Requirements ##

* **PHP:** 7.4

## Installation ##

Slurp is a developer library, not a plugin, which means you need to include it somewhere in your own
project.

You can use Composer:

```bash
composer require arraypress/slurp
```

## Basic Usage ##

To utilize this functionality, you first define an associative array that maps option keys to labels for the post
states. You can optionally specify a callable function responsible for retrieving option values, such as WordPress'
built-in `get_option` function. Here's how you can set it up:

```php
// Require the Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

// Configure post states with an associative array, mapping option keys to labels.
// Optionally, specify a callable function like `get_option` to retrieve option values.
// It's important to ensure `get_option` returns a valid WordPress post ID to match with the admin posts list.
register_post_states( [
    'landing_page'  => __( 'Landing Page', 'text-domain' ),
    'featured_post' => __( 'Featured Post', 'text-domain' )
] );
```

When the `Post_States_Manager` is initialized with the provided options, it hooks into
WordPress `'display_post_states'` filter. This integration allows it to append the custom state labels to the
appropriate posts in the admin list view, based on the configuration provided.

Ensure that this function returns a valid post ID to correctly associate custom state labels with the appropriate posts.

## Advanced Usage ##

For more advanced customization, you can set a specific callable function to fetch option values instead of the
default `get_option`. This is particularly useful if your WordPress setup utilizes a custom options management system,
such as `edd_get_option` from Easy Digital Downloads or any other custom-built mechanism.

Here's how to define a custom getter function for the `Post_States_Manager`:

```php
$options_map = [
    'landing_page'  => __( 'Landing Page', 'text-domain' ),
    'featured_post' => __( 'Featured Post', 'text-domain' ),
    // Additional custom states as necessary
];

// Define your custom getter function, such as 'edd_get_option' or another custom function.
$custom_option_getter = 'edd_get_option';

// Initialize the Post_States_Manager with your custom getter.
register_post_states( $options_map, $custom_option_getter );
```

When you provide your custom getter, `Post_States_Manager` will use this function for all option value retrievals. This
seamless integration allows for a consistent data handling process that aligns with your website's specific
configuration.

Remember to ensure that the custom getter function you provide meets the expected signature and functionality
as `get_option`, to prevent any incompatibilities or errors in the post states management.

### Error Handling

The `register_post_states` function also accepts a third parameter: an error callback function. This callback is invoked
if an exception occurs during the initialization of the `Post_States_Manager`. This allows for graceful handling of
initialization errors and ensures a smooth user experience.

```php
register_post_states( $options_map, 'get_option', function( $exception ) {
    edd_debug_log_exception( $exception );
});
```

### Using the Slurp Library

#### Basic File Inclusion

```php
require_once dirname( __FILE__ ) . '/vendor/autoload.php'; // Include Composer-generated autoload file.
$slurper = new \ArrayPress\Utils\Slurp('/path/to/directory'); // Create a Slurp instance for a specific directory.
$slurper->include('subdirectory'); // Include all PHP files from the specified directory.
```

#### Recursive File Inclusion

```php
$slurper->include('subdirectory', true); // Include files from a directory and all its subdirectories.
```

#### Conditional File Inclusion with Callback

```php
$slurper->include([
    'subdirectory' => function($filePath) {
        // Only include files that contain 'module' in their name.
        return strpos($filePath, 'module') !== false;
    }
]);
```

#### Using the Helper Function in WordPress

```php
$slurper = \ArrayPress\Utils\slurp('/path/to/wordpress/plugin', 'includes'); // Use the helper function within a WordPress plugin.
$slurper->include(); // Include files from the 'includes' subdirectory of the plugin.
```

#### Excluding Specific Files

```php
$slurper = new \ArrayPress\Utils\Slurp('/path/to/directory', null, ['exclude.php']); // Exclude specific files from being included.
$slurper->include(); // Include all other PHP files from the directory.
```

#### Dumping Loaded Files for Debugging

```php
$slurper->dumpFiles('debug.txt'); // Dump the list of included files to a text file for debugging.
```

#### Retrieving List of Loaded Files

```php
$loadedFiles = $slurper->getFiles(); // Get an array of all the PHP files that have been included.
echo '<pre>';
print_r($loadedFiles);
echo '</pre>'; // Display the loaded files (for example purposes).
```

#### Including Files from Multiple Directories

```php
// Assuming the Slurp class is already autoloaded via Composer
$slurper = new \ArrayPress\Utils\Slurp('/path/to/base/directory');

// Include files from multiple directories.
$directories = ['directory1', 'directory2', 'directory3'];
$slurper->include($directories);

// You can also include files recursively from these directories
$slurper->include($directories, true);

// Or include files based on a callback condition for each directory
$slurper->include([
    'directory1' => function($file) { return strpos($file, 'condition1') !== false; },
    'directory2' => function($file) { return strpos($file, 'condition2') !== false; },
    'directory3' => null // Include all files from directory3
]);
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