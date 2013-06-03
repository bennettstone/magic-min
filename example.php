<?php
/**
 * example.php
 *
 * Demonstrates compression functions and minification usage
 * for stylesheets and javascript files
 *
 * @author Bennett Stone
 * @version 2.0
 * @date 02-Jun-2013
 * @updated 03-Jun-2013
 * @package MagicMin
 **/

//Include the caching/minification class
require( 'class.magic-min.php' );

//Initialize the class with image encoding
$vars = array( 
    'encode' => true
);
$minified = new Minifier( $vars );
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="EN" lang="EN" dir="ltr">
<head profile="http://gmpg.org/xfn/11">
    <title>Example Usage | Caching and Minification Class</title>
    

    <!--Output a default minified stylesheet: will output as css/bootstrap.min.css-->
    <link rel="stylesheet" href="<?php $minified->minify( 'css/bootstrap.css' ); ?>" />
    
    
    <!--Output a minified javascript file: will output as js/autogrow.min.js-->
    <script src="<?php $minified->minify( 'js/autogrow.js' ); ?>"></script>
    
    <!--Output a minified javascript file with completely different name, and ?v=1.8 param-->
    <script src="<?php $minified->minify( 'js/jquery.reveal.js', 'js/jquery-magicreveal.min.js', '1.8' ); ?>"></script>
    
    <!--Retrieve the contents of all javascript files in the /js directory as master.min.js (excluding a couple AND making sure bootstrap and validate are first and second)-->
    <?php
    $exclude = array( 
        'js/autogrow.js', 
        'js/jquery.reveal.js'
    );
    $prioritize = array(
        'js/bootstrap.js', 
        'js/jquery.validate.js'
    );
    ?>
    <script src="<?php $minified->merge( 'js/packed.min.js', 'js', 'js', $exclude, $prioritize ); ?>"></script>
    
    
    <!--Get all the stylesheets in a directory and make a single minified stylesheet (excluding the ones used above)-->
    <?php
    $exclude_styles = array(
        'css/bootstrap.css', 
        'css/bootstrap.min.css'
    );
    ?>
    <link rel="stylesheet" href="<?php $minified->merge( 'css/master.min.css', 'css', 'css', $exclude_styles ); ?>" />
    
    <!--Get all the stylesheets in the /base directory and compile them-->
    <link rel="stylesheet" href="<?php $minified->merge( 'css/base/bennett-min.css', 'css/base', 'css' ); ?>" />
    

</head>
<body>

</body>
</html>