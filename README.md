Magic Minifier
=========

MagicMin is a PHP based javascript and stylesheet minification and merging class.

This class is designed to generate minified, merged, and automatically updating files to simplify the process of minified file usage and updating when going between production and development environments.

This class has four primary functions:

1. Minification of single files
    * $class->minify( 'sourcefile', 'outputfile', 'version' );
2. Merging and minifying the contents of whole directories
    * $class->merge( 'outputfile', 'directory', 'type [css, js]', array( 'items to exclude' ), array( 'files to merge and minify in order' ) );
    * The output and minification may be specifically ordered by using the fifth and last parameter, with files passed as an array
    * **Only the files necessary to be prioritized here, and not every file in the directory**
3. Encoding image file data, replacing external image references within CSS
    * Only applies to CSS files
    * Default is false (image references are retained) (See "Basic Usage, item #1")
4. Providing generated assets using gzip with specified cache control
    * zlib Must exist and be enabled, otherwise no gzip will be used
    * Default expires set to 30 days (60 x 60 x 24 x 31)
    
**This class uses filemtime to determine if and when the minified version should be recreated, and will only create a new minified file IF a file selected for inclusion in the minify or merge functions is newer than the previously created minified file**

Files that contain ".min." in the filename will not have their contents minified, but will still have their contents returned and added to compiled files as normal, as it SHOULD be assumed that those files have already been minified.

Full usage examples are included in example.php, and this package is included with the jqueryui styles in /base, as well as a few misc javascript and bootstrap files for testing.

Regular expressions and str_replace operators were removed 15-Jun-2013 for javascript minification due to inconsistencies, and have been replaced with:

1. [JsMin](https://github.com/rgrove/jsmin-php) as the default
    * If the jsmin.php file is not found in the same directory as class.magic-min.php, it will be retrieved from github and created in the same directory automatically
    * JsMin is the default minification package used for javascript
2. [Google Closure](https://developers.google.com/closure/compiler/)
    * To use google closure, add 'closure' => true to the class initation

##Basic Usage
First, include and initiate the class.  The class has been updated to use an array with up to 7 key -> value pairs, all accept boolean values or can be omitted entirely:

1. Base64 encoded images (**local or remote**) can automatically replace file references during generation.  This applies only to CSS files.
    * 'encode' => true/false (default is false)
    * url() type file paths beginning with "http" or "https" are retrieved and encoded using cURL as opposed to file_get_contents for local files
2. Echo the resulting generated file path, or return to use as a variable
    * 'echo' => true/false (default is true)
3. Output the total execution time
    * 'timer' => true/false (default is false)
    * Set as part of __destruct to log to the javascript console, adjust as necessary
4. Output minified/merged assets using gzip with cache control
    * 'gzip' => true/false (default is false)
5. Use the Google Closure API as opposed to jsmin (jsmin is default)
    * 'closure' => true (default is false)
6. Retain or remove comments within file contents (Thanks to [muertet](https://github.com/muertet) for this one)
    * 'remove_comments' => true/false (defaults to true)
7. Generate hashed filenames based on file generation time.  Allows for better cachebusting without the use of query string version numbers
    * 'hashed_filenames' => false/true (defaults to false)
8. Automatically output the results of operations taken by MagicMin to the javascript console.  Same as calling $minified->logs(), but without needing to add an explicit call to the "logs()" function
    * 'output_log' => false/true (defaults to false)

```php
require( 'class.magic-min.php' );

//Default usage will echo from function calls and leave images untouched
$minified = new Minifier();

//Return data without echo
$minified = new Minifier( array( 'echo' => false ) );

//Echo the resulting file path, while base64_encoding images and including as part of css
$minified = new Minifier( array( 'encode' => true ) );

//Return only AND encode/include graphics as part of css (gasp)
$minified = new Minifier( array( 'encode' => true, 'echo' => false ) );

//Include images as part of the css, and gzip
$minified = new Minifier( array( 'encode' => true, 'gzip' => true ) );

//Use google closure API, and gzip the resulting file
$minified = new Minifier( array( 'gzip' => true, 'closure' => true ) );

//Use google closure API, gzip the resulting file, hash filenames, output to console
$minified = new Minifier( 
    array(
        'gzip' => true, 
        'closure' => true, 
        'hashed_filenames' => true, 
        'output_log' => true
    )
);
```

Output a single minified stylesheet

```html
<link rel="stylesheet" href="<?php $minified->minify( 'css/style.css' ); ?>" />
```

Output a single minified javascript file (will output as js/autogrow.min.js)

```html
<script src="<?php $minified->minify( 'js/autogrow.js' ); ?>"></script>
```

Merge and minify the contents of an entire directory

```html
<script src="<?php $minified->merge( 'js/packed.min.js', './js', 'js' ); ?>"></script>

<link rel="stylesheet" href="<?php $minified->merge( 'css/master.min.css', './css', 'css' ); ?>" />
```

##Advanced Usage

Output a minified stylesheet with a specified filename and ?v= param 

```html
<link rel="stylesheet" href="<?php $minified->minify( 'css/style.css', 'css/style-compressed.min.css', '1.1' ); ?>" />

//Will output:
<link rel="stylesheet" href="css/style-compressed.min.css?v=1.1" />
```

Output a minified javascript file with [filename].min.js name, and ?v=1.8 param

```html
<script src="<?php $minified->minify( 'js/jquery.reveal.js', '', '1.8' ); ?>"></script>

//Will output:
<script src="js/jquery.reveal.min.js?v=1.8"></script>
```

Retrieve the contents of all javascript files in the /js directory as master.min.js (excluding the ones above)

```php
<?php
$exclude = array( 
    'js/autogrow.js', 
    'js/autogrow.min.js', 
    'js/jquery.reveal.js', 
    'js/jquery.reveal.min.js'
);
?>
<script src="<?php $minified->merge( 'js/packed.min.js', 'js', 'js', $exclude ); ?>"></script>
```

Get all the stylesheets in a directory and make a single minified stylesheet (excluding the ones used above)

```php
<?php
$exclude_styles = array(
    'css/style.css', 
    'css/style-compressed.min.css'
);
?>
<link rel="stylesheet" href="<?php $minified->merge( 'css/master.min.css', 'css', 'css', $exclude_styles ); ?>" />
```

###Viewing actions taken by MagicMin

To provide more transparency, the "logs()" function has been added, and can output console.log messages to the page where MagicMin is used to be viewed in the source code of the page, or within the javascript console.

If used, this function should only be requested after all other output has taken place by the magicmin script, ie...

```html
<html>
    <head>
        
        <?php $minified = new Minifier( array( 'gzip' => true, 'timer' => true ) ); ?>
        
        <script src="<?php $minified->merge( 'js/my-new-file.min.js', 'javascript-directory', 'js' ); ?>"></script>
    </head>
    <body>
    
        <h1>your content here</h1>
    
        <?php $minified->logs(); ?>
    
    </body>
</html>
```

Which would output to the console something such as:

```text
Minifier Log : Timer enabled
Minifier Log : Gzip enabled
Minifier Log: timer : MagicMin processed and loaded in 0.00019097328186 seconds
```

**This is the same as adding the 'output_log' => true param when initializing MagicMin**

###Specifying the order of included files when using "merge"

Since it's simply not practical to assume glob() is going to determine dependencies, a fifth parameter exists for the merge() function.

In order to specify the order, you **only need to specify the files that need to be ordered**.  You **do not need to specify all the files in the directory**, the function will automatically prioritize the files passed into the $order parameter.

```php
<?php
//Only exclude autogrow.js
$exclude = array(
    'js/autogrow.js'
);

//Put jquery and validate before anything else
$order = array(
    'js/jquery.min.js', 
    'js/jquery.validate.js'
);
<script src="<?php $minified->merge( 'js/magically-ordered.min.js', 'js', 'js', $exclude, $order ); ?>"></script>
```

###Including ONLY specified files in order, disregarding all others

As of 13-Jun-2013, the 3rd parameter of the ->merge() function will now accept an array of files to include.  Files are included in the order specified in the array.

When using an array as the 3rd parameter (as opposed to the "css" or "js" file type), **DO NOT include further parameters as this will create possible conflict.**

```php
<?php
$include_only = array(
    'css/base/jquery.ui.all.css', 
    'css/base/jquery.ui.base.css', 
    'css/base/jquery.ui.spinner.css'
);
?>
<link rel="stylesheet" href="<?php $minified->merge( 'css/base/specified-files.css', 'css/base', $include_only ); ?>" />
```

**NOTE: Files must be the same type (css or js) as different file types will not play nice in the output**

##Usage within wordpress

Since many MVC frameworks such as wordpress use full URIs to stylesheets and javascript files, it is helpful to first create a variable containing the absolute path to the header.php file, and ensure that the Minifier initiation is set with echo to false.

For example (within header.php):

```php
$dir = dirname( __FILE__ );
require_once( $dir .'/includes/class.magic-min.php' );
$min = new Minifier( array( 'echo' => false ) );
```

Then perform a str_replace operation to regenerate the URI to the stylesheets or javascript files:

```php
<link rel="stylesheet" href="<?php echo str_replace( $dir, get_bloginfo('template_directory'), $min->merge( $dir . '/css/master.min.css', $dir . '/css', 'css' ) ); ?>" />
```

Which would in turn output the correct URI to the stylesheet:

```html
<link rel="stylesheet" href="http://yourwebsite.com/wp-content/themes/yourtheme/css/master.min.css" />
```


###Changelog

**3.0.3**
* Added output_log to configuration options to output to console automatically
* Altered google closure compiler request data to degrade gracefully when Closure Compiler returns errors, returning non-minified original file contents rather than writing a google error message to a JavaScript file

**3.0.2**
* Bugfix to force arrays in minified_filedata requests

**3.0.1**
* Added support for protocol agnostic URIs such as '//ajax.googleapis.com/ajax/libs/...'
* Added plug and play function to simplify merge functionality
* Bugfix for merge functionality when using file list
  * Altered minified_filedata to stored full list of files contained in minified package
  * Added support for automatic re-minification when the number of files passed to the "merge" function changes