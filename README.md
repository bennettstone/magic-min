MagicMin
=========

MagicMin is a PHP based javascript and stylesheet minification class designed to generate minified, merged, and automatically updating files to simplify the process of minified file usage and updating when going between production and development environments.

This class has two primary functions:

1. Minification of single files
    * $class->minify( 'sourcefile', 'outputfile', 'version' );
2. Merging and minifying the contents of whole directories
    * $class->merge( 'outputfile', 'directory', 'type [css, js]', array( 'items to exclude' ) );
    
**This class uses filemtime to determine if and when the minified version should be recreated, and will only create a new minified file IF a file selected for inclusion in the minify or merge functions is newer than the previously created minified file**

##Usage
First, include and initiate the class

```php
require( 'class.magic-min.php' );
$minified = new Minifier();
```
Output a minified stylesheet with a specified filename and ?v= param 

```html
<link rel="stylesheet" href="<?php $minified->minify( 'css/style.css', 'css/style-compressed.min.css', '1.1' ); ?>" />
```

Output a minified javascript file (will output as js/autogrow.min.js)

```html
<script src="<?php $minified->minify( 'js/autogrow.js' ); ?>"></script>
```

Output a minified javascript file with [filename].min.js name, and ?v=1.8 param

```html
<script src="<?php $minified->minify( 'js/jquery.reveal.js', '', '1.8' ); ?>"></script>
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