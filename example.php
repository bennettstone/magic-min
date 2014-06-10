<?php
/**
 * example.php
 *
 * Demonstrates compression functions and minification usage
 * for stylesheets and javascript files
 *
 * @author Bennett Stone
 * @version 2.5
 * @date 02-Jun-2013
 * @updated 15-Jun-2013
 * @package MagicMin
 **/

//Include the caching/minification class
require( 'class.magic-min.php' );

//Initialize the class with image encoding, gzip, a timer, and use the google closure API
$vars = array( 
    'encode' => true, 
    'timer' => true, 
    'gzip' => true, 
    'closure' => true, 
    'remove_comments' => false
);
$minified = new Minifier( $vars );
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="EN" lang="EN" dir="ltr">
<head profile="http://gmpg.org/xfn/11">
    <title>Example Usage | Caching and Minification Class</title>

    <?php
    /*
    
    <!--Output a new merged stylesheet with only the $included_styles included in order-->
    <?php
    $included_styles = array(
        'css/bootstrap.css', 
        'https://raw.github.com/zurb/reveal/master/reveal.css', 
        'css/base/jquery-ui.css'  
    );
    ?>
    <link rel="stylesheet" href="<?php $minified->merge( 'css/awesome.min.css', 'css', $included_styles ); ?>" />
    
    
    <!--Output a default minified stylesheet: will output as css/bootstrap.min.css-->
    <link rel="stylesheet" href="<?php $minified->minify( 'css/bootstrap.css' ); ?>" />
    
    
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
    

    <!--Include ONLY a specified list of files IN ORDER-->
    <?php
    $include_only = array(
        'http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js', 
        'http://code.jquery.com/jquery-migrate-1.2.1.min.js', 
        'js/autogrow.js', 
        'js/jquery.reveal.js', 
        'js/bootstrap.js'
    );
    ?>
    <script src="<?php $minified->merge( 'js/compressor.min.js', 'js', $include_only ); ?>"></script>
        
    */
    ?>
</head>
<body>

    <h1>TESTFILE</h1>

    <a href="#" class="new-modal">Click Me For A Modal</a>

    <div id="myModal" class="reveal-modal">
         <h1>Modal Title</h1>
         <p>Any content could go in here.</p>
         <a class="close-reveal-modal">&#215;</a>
    </div>

    <textarea name="whatever"></textarea>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('textarea').TextAreaExpander(100);
         $('.new-modal').click(function(e) {
              e.preventDefault();
    	  $('#myModal').reveal();
         });
    });
    </script>

<?php
//Output actions associated with the minification to the console
$minified->logs();
?>
</body>
</html>