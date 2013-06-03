Magic Minifier
=========

MagicMin is a PHP based javascript and stylesheet minification and merging class.

This class is designed to generate minified, merged, and automatically updating files to simplify the process of minified file usage and updating when going between production and development environments.

This class has ~~two~~ three primary functions:

1. Minification of single files
    * $class->minify( 'sourcefile', 'outputfile', 'version' );
2. Merging and minifying the contents of whole directories
    * $class->merge( 'outputfile', 'directory', 'type [css, js]', array( 'items to exclude' ), array( 'files to merge and minify in order' ) );
    * The output and minification may be specifically ordered by using the fifth and last parameter, with files passed as an array
    * **Only the files necessary to be prioritized here, and not every file in the directory**
3. Encoding image file data, replacing external image references within CSS
    * Only applies to CSS files
    * Default is false (image references are retained) (See "Basic Usage, item #1")
    
**This class uses filemtime to determine if and when the minified version should be recreated, and will only create a new minified file IF a file selected for inclusion in the minify or merge functions is newer than the previously created minified file**

Files that contain ".min." in the filename will not have their contents minified, but will still have their contents returned and added to compiled files as normal, as it SHOULD be assumed that those files have already been minified.

Full usage examples are included in example.php, and this package is included with the jqueryui styles in /base, as well as a few misc javascript and bootstrap files for testing.

##Basic Usage
First, include and initiate the class.  The class has been updated to use an array with up to 3 key -> value pairs, all accept boolean values or can be omitted entirely:

1. Base64 encoded images (**local or remote**) can automatically replace file references during generation.  This applies only to CSS files.
    * 'encode' => true/false (default is false)
    * url() type file paths beginning with "http" or "https" are retrieved and encoded using cURL as opposed to file_get_contents for local files
2. Echo the resulting generated file path, or return to use as a variable
    * 'echo' => true/false (default is true)
3. Output the total execution time
    * 'timer' => true/false (default is false)
    * Set as part of __destruct to log to the javascript console, adjust as necessary

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

##Usage within wordpress

Since many MVC frameworks such as wordpress use full URIs to stylesheets and javascript files, it is helpful to first create a variable containing the absolute path to the header.php file, and ensure that the Minifier initiation is set with echo to false.

For example (within header.php):

```php
$dir = dirname( __FILE__ );
require_once( $dir .'/includes/class.magic-min.php' );
$min = new Minifier( false );
```

Then perform a str_replace operation to regenerate the URI to the stylesheets or javascript files:

```php
<link rel="stylesheet" href="<?php echo str_replace( $dir, get_bloginfo('template_directory'), $min->merge( $dir . '/css/master.min.css', $dir . '/css', 'css' ) ); ?>" />
```

Which would in turn output the correct URI to the stylesheet:

```html
<link rel="stylesheet" href="http://yourwebsite.com/wp-content/themes/yourtheme/css/master.min.css" />
```