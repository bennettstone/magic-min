<?php
/**
 * minifier-function.inc.php
 * Provides aux support to simplify magic min usage
 * See https://github.com/bennettstone/magic-min for more configuration options
 *
 * @version 1.0
 * @date 07-Oct-2014
 * @package MagicMin
 **/


/**
 * Function to simplify replacements of absolute vs. relative paths when using magicmin
 *
 * @access public
 * @param string $output_filename
 * @param array $files
 * @param string $output_directory (just the 'css', 'js' etc... bit)
 * @param string $type (js, css)
 * @return string
 */
function magic_min_merge( $output_filename, $files = array(), $output_directory = 'css' )
{
    if( empty( $files ) )
    {
        return '';
    }
    
    require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'class.magic-min.php' );
    
    $output_type = strtolower( pathinfo( basename( $output_filename ), PATHINFO_EXTENSION ) );
    
    $minified = new Minifier( 
        array( 
            'closure' => true, 
            'echo' => false, 
            'timer' => false, 
            'hashed_filenames' => true, 
            'remove_comments' => true
        )
    );

    switch( $output_type )
    {
        case 'css':
            return '<link rel="stylesheet" href="'. 
                $minified->merge( 
                    $output_directory.'/'.$output_filename, 
                    $output_directory, 
                    $files, 
                    $output_type 
                ) .'" />' . PHP_EOL;
            
        break;
        case 'js':
        
        default:
            return '<script src="'. 
                $minified->merge( 
                    $output_directory.'/'.$output_filename, 
                    $output_directory, 
                    $files, 
                    $output_type
                ) .'"></script>' . PHP_EOL;
            
        break;
    }
}