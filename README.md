# Slurp Library

The Slurp library provides a powerful and efficient solution for dynamically including PHP files in WordPress or general PHP projects. It supports recursive file inclusion, conditional inclusion via callbacks, and offers robust debugging capabilities by tracking and logging included files. Ideal for organized and efficient file management in plugin or application development.
## Installation and set up

The extension in question needs to have a `composer.json` file, specifically with the following:

```json 
{
  "require": {
    "arraypress/slurp": "*"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/arraypress/slurp"
    }
  ]
}
```

Once set up, run `composer install --no-dev`. This should create a new `vendors/` folder
with `arraypress/slurp/` inside.

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

## License

This library is licensed under
the [GNU General Public License v2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html).