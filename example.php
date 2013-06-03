<?php
/**
 * example.php
 *
 * Demonstrates compression functions and minification usage
 * for stylesheets and javascript files
 *
 * @author Bennett Stone
 * @version 1.0
 * @date 02-Jun-2013
 * @package MagicMin
 **/

//Include the caching/minification class
require( 'class.magic-min.php' );

//Initialize the class
$minified = new Minifier();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="EN" lang="EN" dir="ltr">
<head profile="http://gmpg.org/xfn/11">
    <title>Example Usage | Caching and Minification Class</title>
    
    <!--Output a minified stylesheet with a specified filename and ?v= param-->
    <link rel="stylesheet" href="<?php $minified->minify( 'css/style.css', 'css/style-compressed.min.css', '1.1' ); ?>" />
    
    <!--Output a minified javascript file: will output as js/autogrow.min.js-->
    <script src="<?php $minified->minify( 'js/autogrow.js' ); ?>"></script>
    
    <!--Output a minified javascript file with [filename].min.js name, and ?v=1.8 param-->
    <script src="<?php $minified->minify( 'js/jquery.reveal.js', '', '1.8' ); ?>"></script>
    
    <!--Retrieve the contents of all javascript files in the /js directory as master.min.js (excluding the ones above)-->
    <?php
    $exclude = array( 
        'js/autogrow.js', 
        'js/jquery.reveal.js'
    );
    ?>
    <script src="<?php $minified->merge( 'js/packed.min.js', 'js', 'js', $exclude, array( 'js/bootstrap.js', 'js/jquery.validate.js' ) ); ?>"></script>
    
    <!--Get all the stylesheets in a directory and make a single minified stylesheet (excluding the ones used above)-->
    <?php
    $exclude_styles = array(
        'css/style.css', 
        'css/style-compressed.min.css'
    );
    ?>
    <link rel="stylesheet" href="<?php $minified->merge( 'css/master.min.css', 'css', 'css', $exclude_styles ); ?>" />

</head>
<body>


</body>
</html>