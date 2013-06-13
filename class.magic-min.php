<?php
/*------------------------------------------------------------------------------
** File:        class.magic-min.php
** Class:       MagicMin
** Description: Javascript and CSS minification/merging class to simplify movement from development to production versions of files
** Version:     2.1
** Updated:     01-Jun-2013
** Author:      Bennett Stone
** Homepage:    www.phpdevtips.com 
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2013 BENNETT STONE
**
** The source code included in this package is free software; you can
** redistribute it and/or modify it under the terms of the GNU General Public
** License as published by the Free Software Foundation. This license can be
** read at:
**
** http://www.opensource.org/licenses/gpl-license.php
**
** This program is distributed in the hope that it will be useful, but WITHOUT 
** ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
** FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. 
**------------------------------------------------------------------------------
**
** Usage:
** $min = new Minifier();
** <script src="<?php $min->minify( '[source filename]', '[output filename (optional)]', '[version (optional)]' ); ?>"></script>
** <link rel="stylesheet" media="all" href="<?php $min->minify( 'css/copy-from.css', 'css/test-prod.minified.css', '1.8' ); ?>">
**
** Usage example for merge and minify:
** $min->merge( 'output filename and location', 'directory', 'type (js or css)', array( of items to exclude ) );
** $min->merge( 'js/one-file-merged.js', 'js', 'js', array( 'js/inline-edit.js', 'js/autogrow.js' ) );
**
** Normalized output example using merge and minify:
** <script src="<?php $min->merge( 'js/production.min.js', 'js', 'js' ); ?>"></script>
**------------------------------------------------------------------------------ */

class Minifier {
    
    public $content;
    public $output_file;
    public $extension;
    private $type;
    //Return or echo the values
    private $print = true;
    //base64 images from CSS and include as part of the file?
    private $merge_images = false;
    //Max image size for inclusion
    const IMAGE_MAX_SIZE = 5;
    //For script execution time (src: http://bit.ly/18O3VWw)
    private $mtime;
    private $timer = false;
    //Output as php with gzip?
    private $gzip = false;
    
    
    /**
     * Construct function
     */
    public function __construct( $vars = array() )
    {
        //Return vs echo (echo default)
        if( isset( $vars['echo'] ) && $vars['echo'] == true )
        {
            $this->print = $vars['echo'];   
        }
        //base64 images and include as part of CSS (default is false)
        if( isset( $vars['encode'] ) && $vars['encode'] == true )
        {
            $this->merge_images = $vars['encode'];   
        }
        //Output a timer (defaut is false)
        if( isset( $vars['timer'] ) && $vars['timer'] == true )
        {
            $this->timer = true;
            $this->mtime = microtime( true );   
        }
        //Output files as php with gZip (default is false)
        if( isset( $vars['gzip'] ) && $vars['gzip'] == true )
        {
            $this->gzip = true;
        }
    }
	
	
    /**
     * Function to seek out and replace image references within CSS with base64_encoded data streams
     * Used in minify_contents function IF global for $this->merge_images
     * This function will retrieve the contents of local OR remote images, and is based on 
     * Matthias Mullie <minify@mullie.eu>'s function, "importFiles" from the JavaScript and CSS minifier
     * http://www.phpclasses.org/package/7519-PHP-Optimize-JavaScript-and-CSS-files.html
     *
     * @access private
     * @param string $source_file (used for location)
     * @param string $contents
     * @return string $updated_style
     */
    private function merge_images( $source_file, $contents )
    {
        $this->directory = dirname( $source_file ) .'/';

        if( preg_match_all( '/url\((["\']?)((?!["\']?data:).*?\.(gif|png|jpg|jpeg))\\1\)/i', $contents, $this->matches, PREG_SET_ORDER ) )
        {
            $this->find = array();
            $this->replace = array();

            foreach( $this->matches as $this->graphic )
            {

                $this->extension = pathinfo( $this->graphic[2], PATHINFO_EXTENSION );

                $this->image_file = '';

                //See if the file is remote or local
                if( preg_match( "/(http|https)/", $this->graphic[2] ) )
                {

                    //It's remote, and CURL is pretty fast
                    $ch = curl_init();
                    curl_setopt( $ch, CURLOPT_URL, $this->graphic[2] );
                    curl_setopt( $ch, CURLOPT_NOBODY, 1 );
                    curl_setopt( $ch, CURLOPT_FAILONERROR, 1 );
                    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

                    //And it WAS remote, and it DOES exist
                    if( curl_exec( $ch ) !== FALSE )
                    {

                        //Get the image file
                        $cd = curl_init( $this->graphic[2] );
                        curl_setopt( $cd, CURLOPT_HEADER, 0 );
                        curl_setopt( $cd, CURLOPT_RETURNTRANSFER, 1 );
                        curl_setopt( $cd, CURLOPT_BINARYTRANSFER, 1 );
                        $this->image_file = curl_exec( $cd );
                        //Get the remote filesize
                        $this->filesize = curl_getinfo( $cd, CURLINFO_CONTENT_LENGTH_DOWNLOAD );
                        curl_close( $cd );
                        
                        if( $this->filesize <= Minifier::IMAGE_MAX_SIZE * 1024 )
                        {
                            //Assign the find and replace
                            $this->find[] = $this->graphic[0];
                            $this->replace[] = 'url(data:'.$this->extension.';base64,'.base64_encode( $this->image_file ).')';   
                        }

                    } //End file exists
                    curl_close( $ch );
                
                } //End remote file
                
                elseif( file_exists( $this->directory . $this->graphic[2] ) )
                {
                    //File DOES exist locally, get the contents
                    
                    //Check the filesize
                    $this->filesize = filesize( $this->directory . $this->graphic[2] );
                    
                    if( $this->filesize <= Minifier::IMAGE_MAX_SIZE * 1024 )
                    {
                        //File is within the filesize requirements so add it
                        $this->image_file = file_get_contents( $this->directory . $this->graphic[2] );

                        //Assign the find and replace
                        $this->find[] = $this->graphic[0];
                        $this->replace[] = 'url(data:'.$this->extension.';base64,'.base64_encode( $this->image_file ).')';   
                    }
                
                } //End local file

            }

            //Find and replace all the images with the base64 data
            $this->updated_style = str_replace( $this->find, $this->replace, $contents );
            
            return $this->updated_style;

        } //End if( regex for images)
        else
        {
            //No images found in the sheet, just return the contents
            return $contents;
        }  
    }
	
    /**
     * Private function to handle minification of file contents
     * Supports CSS and JS files
     *
     * @access private
     * @param string $src_file
     * @return string $content
     */
    private function minify_contents( $src_file )
    {
        $this->source = file_get_contents( $src_file );
	    
        $this->type = strtolower( pathinfo( $src_file, PATHINFO_EXTENSION ) );
	    
        $this->output = '';
	    
        //If the filename indicates that the contents are already minified, we'll just return the contents
        if( preg_match( '/.min./i', $src_file ) )
        {
            return $this->source;
        }
        else
        {   
            if( !empty( $this->type ) && $this->type == 'css' )
            {
                $this->content = $this->source;
                //If the param is set to merge images into the css before minifying...
                if( $this->merge_images )
                {
                    $this->content = $this->merge_images( $src_file, $this->content );   
                }
                
                /* remove comments */
                $this->content = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $this->content );
                /* remove tabs, spaces, newlines, etc. */
                $this->content = str_replace( array("\r\n","\r","\n","\t",'  ','    ','     '), '', $this->content );
                /* remove other spaces before/after ; */
                $this->content = preg_replace( array('(( )+{)','({( )+)'), '{', $this->content );
                $this->content = preg_replace( array('(( )+})','(}( )+)','(;( )*})'), '}', $this->content );
                $this->content = preg_replace( array('(;( )+)','(( )+;)'), ';', $this->content );
            }
            if( !empty( $this->type ) && $this->type == 'js' )
            {
                $this->content = $this->source;
                /* remove comments */
                $this->content = preg_replace( "/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/", "", $this->content );
                /* remove tabs, spaces, newlines, etc. */
                $this->content = str_replace( array("\r\n","\r","\t","\n",'  ','    ','     '), '', $this->content );
                /* remove other spaces before/after ) */
                $this->content = preg_replace( array('(( )+\))','(\)( )+)'), ')', $this->content );
            }
            
            //Add to the output and return it
            $this->output .= $this->content;
            return $this->output;   
        }
    }
	
	
    /**
     * Private function to strip directory names from TOC output
     * Used for make_min()
     *
     * @access private
     * @param array $input
     * @return array $output
     */
    private function strip_directory( $input )
    {
        return basename( $input );
    }
    
	
	
    /**
     * Private function to create file with, or without minified contents
     *
     * @access private
     * @param string path to, and name of source file
     * @param string path to, and name of new minified file
     * @return string new filename/location (same as path to variable)
     */
    private function make_min( $src_file, $new_file )
    {

        //Output gzip data as needed, but default to none
        //Lengthy line usage is intentional to provide cleanly formatted fwrite contents
        $this->prequel = '';
        if( $this->gzip )
        {
            $this->prequel = '<?php' . PHP_EOL;
            $this->prequel .= 'if( extension_loaded( "zlib" ) )' . PHP_EOL;
            $this->prequel .= '{' . PHP_EOL;
            $this->prequel .= '    ob_start( "ob_gzhandler" );' . PHP_EOL;
            $this->prequel .= '}' . PHP_EOL;
            $this->prequel .= 'else' . PHP_EOL;
            $this->prequel .= '{' . PHP_EOL;
            $this->prequel .= '    ob_start();' . PHP_EOL;
            $this->prequel .= '}' . PHP_EOL;
            
            //Get the actual file type for header
            $this->extension = strtolower( pathinfo( $new_file, PATHINFO_EXTENSION ) );
            
            //Rewrite the file name to indicate php
            $new_file = $new_file. '.php';
            
            if( $this->extension == 'css' )
            {
                $this->prequel .= 'header( \'Content-type: text/css; charset: UTF-8\' );' . PHP_EOL;   
            }
            if( $this->extension == 'js' )
            {
                $this->prequel .= 'header( \'Content-type: application/javascript; charset: UTF-8\' );' . PHP_EOL;   
            }
            
            //Close out the php row so we can continue with normal content
            $offset = 60 * 60 * 24 * 31;
            $this->prequel .= 'header( \'Content-Encoding: gzip\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Cache-Control: max-age=' . $offset.'\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Expires: ' . gmdate( "D, d M Y H:i:s", time() + $offset ) . ' GMT\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Last-Modified: ' . gmdate( "D, d M Y H:i:s", filemtime( __FILE__ ) ) . ' GMT\' );' . PHP_EOL;
            $this->prequel .= '?>' . PHP_EOL;
            
        } //End if( $this->gzip )
        
        //Single files
        if( !is_array( $src_file ) )
        {
            $this->filetag = '/**' . PHP_EOL;
            $this->filetag .= ' * Filename: '. basename( $src_file ) . PHP_EOL;
            $this->filetag .= ' * Generated '.date('Y-m-d'). ' at '. date('h:i:s A') . PHP_EOL;
            $this->filetag .= ' */' . PHP_EOL;
            $this->content = $this->prequel . $this->filetag . $this->minify_contents( $src_file );  
        }
        else
        {
            //Strip the directory names from the $src_file array for security
            $filenames = array_map( array( 'Minifier', 'strip_directory' ), $src_file );
            
            //Make a temporary var to store the data and write a TOC
            $this->compiled = '/**' . PHP_EOL;
            $this->compiled .= ' * Table of contents: ' . PHP_EOL;
            $this->compiled .= ' * '. implode( PHP_EOL. ' * ', $filenames ) . PHP_EOL;
            $this->compiled .= ' * Generated: ' . date( 'Y-m-d h:i:s' ). PHP_EOL;
            $this->compiled .= ' */' . PHP_EOL;
            
            //Loop through an array of files to write to the new file
            foreach( $src_file as $this->new_file )
            {                   
                //Add the sourcefile minified content
                $this->compiled .= PHP_EOL . PHP_EOL . '/* Filename: '. basename( $this->new_file ) . ' */' . PHP_EOL;
                $this->compiled .= $this->minify_contents( $this->new_file );
            }
            
            //Write the temporary contents to the full contents
            $this->content = trim( $this->prequel . $this->compiled );
            
            //Remove the temporary data
            unset( $this->compiled );
        }

        //Create the new file
        $this->handle = fopen( $new_file, 'w' ) or die( 'Cannot open file:  '.$new_file );

        //Write the minified contents to it
        fwrite( $this->handle, $this->content );
        fclose( $this->handle );

        //Return filename and location
        return $new_file;
    }   
    

    /**
     * Get contents of JS or CSS script, create minified version
     * Idea and partial adaptation from: http://davidwalsh.name/php-cache-function
     * Dependent on "make_min" function
     *
     * Example usage:
     * <script src="<?php $min->minify( 'js/script.dev.js', 'js/script.js', '1.3' ); ?>"></script> 
     * <link rel="stylesheet" href="<?php $min->minify( 'css/style.css', 'css/styles.min.css', '1.8' ); ?>" />
     * $min->minify( 'source file', 'output file', 'version' );
     *
     * @access public
     * @param string $src_file (filename and location for original file)
     * @param string $file (filename and location for output file.  Empty defaults to [filename].min.[extension])
     * @param string $version
     * @return string $output_file (includes provided location)
     */
    public function minify( $src_file, $file = '', $version = '' )
    {
        
        //Since the $file (output) filename is optional, if empty, just add .min.[ext]
        if( empty( $file ) )
        {
            //Get the pathinfo
            $ext = pathinfo( $src_file );
            //Create a new filename
            $file = $ext['dirname'] . '/' . $ext['filename'] . '.min.' . $ext['extension'];
        }
        
        //The file already exists and doesn't need to be recreated
        if( file_exists( $file ) && ( filemtime( $src_file ) < filemtime( $file ) ) )
        {
            //No change, so the output is the same as the input
            $this->output_file = $file;

        }
        //The file exists, but the development version is newer
        elseif( file_exists( $file ) && ( filemtime( $src_file ) > filemtime( $file ) ) )
        {
            //Remove the file so we can do a clean recreate
            unlink( $file );
            
            //Make the cached version
            $this->output_file = $this->make_min( $src_file, $file );
        }
        //The minified file doesn't exist, make one
        else
        {
            //Make the cached version
            $this->output_file = $this->make_min( $src_file, $file );
        }

        //Add the ? params if they exist
        if( !empty( $version ) )
        {
            $this->output_file .= '?v='. $version;   
        }
        
        //Return the output filename or echo
        if( $this->print )
        {
            echo $this->output_file;
        }
        else
        {
            return $this->output_file;
        }
    }   
    
    
    /**
     * Get the contents of js or css files, minify, and merge into a single file
     *
     * Example usage:
     * <?php
     * require_once( 'class.magic-min.php' );
     * $min = new Minifier();
     * ?>
     * <script src="<?php $min->merge( '[output folder]/[output filename.js]', '[directory]', '[type(js or css)]', array( '[filetoignore]', '[filetoignore]' ) ); ?>"></script>
     * <script src="<?php $min->merge( 'js/one-file-merged.js', 'js', 'js', array( 'js/inline-edit.js', 'js/autogrow.js' ) ); ?>"></script>
     *
     * @access public
     * @param string $output_filename
     * @param string $directory to loop through
     * @param mixed $type (css, js, selective - default is js)
     **** $type will also accept "selective" array which overrides glob and only includes specified files
     **** $type array passed files are included in order, and no other files will be included
     **** files must all be the same type in order to prevent eronious output contents (js and css do not mix)
     * @param array $exclude files to exclude
     * @param array $order to specify output order
     * @return string new filenae
     */
    public function merge( $output_filename, $directory, $type = 'js', $exclude = array(), $order = array() )
    {
        /**
         * Added selective inclusion to override glob and exclusion 13-Jun-2013 ala Ray Beriau
         * This assumes the user has passed an array of filenames, in order rather than a file type
         * By doing so, we'll set the directory to indicate no contents, and priorize directly into $order
         */
        if( is_array( $type ) && !empty( $type ) )
        {
            $this->directory = array();
            $order = $type;
        }
        else
        {
            //Open the directory for looping and seek out files of appropriate type
            $this->directory = glob( $directory .'/*.'.$type );            
        }

        //Create a bool to determine if a new file needs to be created
        $this->create_new = false;
        
        //Start the array of files to add to the cache
        $this->compilation = array();
        
        //Determine if a specific order is needed, if so remove only those files from glob seek
        if( !empty( $order ) )
        {
            foreach( $order as $specified->file )
            {
                
                //Check each file for modification greater than the output file if it exists
                if( file_exists( $output_filename ) && ( $specified->file != $output_filename ) && ( filemtime( $specified->file ) > filemtime( $output_filename ) ) )
                {
                    $this->create_new = true;
                }
                
                //Add the specified files to the beginning of the use array passed to $this->make_min
                $this->compilation[] = $specified->file;
                
            }
            
            //Now remove the same files from the glob directory
            $this->directory = array_diff( $this->directory, $this->compilation );
        
        } //End !empty( $order )

        //Loop through the directory grabbing files along the way
        foreach( $this->directory as $this->file )
        {
            
            //Make sure we didn't want to exclude this file before adding it
            if( !in_array( $this->file, $exclude ) && ( $this->file != $output_filename ) )
            {
                //Check each file for modification greater than the output file if it exists
                if( file_exists( $output_filename ) && ( filemtime( $this->file ) > filemtime( $output_filename ) ) )
                {
                    $this->create_new = true;
                }
                
                $this->compilation[] = $this->file;
            }
            
        } //End foreach( $this->directory )

        //Only recreate the file as needed
        if( $this->create_new || !file_exists( $output_filename ) )
        {
            //Group and minify the contents
            $this->compressed = $this->make_min( $this->compilation, $output_filename );   
        }
        else
        {
            $this->compressed = $output_filename;
        }
        
        //Echo or return
        if( $this->print )
        {
            echo $this->compressed;
        }
        else
        {
            return $this->compressed;
        }
        
    }
    
    public function  __destruct()
    {
        if( $this->timer )
        {
            echo "<script>console.log('Script loaded in ". ( microtime( true ) - $this->mtime )."');</script>";   
        }
    }

} //End class Minifier