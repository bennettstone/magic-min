<?php
/*------------------------------------------------------------------------------
** File:            class.magic-min.php
** Class:           MagicMin
** Description:     Javascript and CSS minification/merging class to simplify movement from development to production versions of files
** Dependencies:    jShrink (https://github.com/tedious/JShrink)
** Version:         3.0.3
** Created:         01-Jun-2013
** Updated:         08-Oct-2014
** Author:          Bennett Stone
** Homepage:        www.phpdevtips.com 
**------------------------------------------------------------------------------
** COPYRIGHT (c) 2014 BENNETT STONE
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
**
** Adding gzip, base64 image encoding, or returning rather than echo:
** $vars = array( 
**    'echo' => false, 
**    'encode' => true, 
**    'timer' => true, 
**    'gzip' => true
** );
** $minified = new Minifier( $vars );
**
** Using JShrink for js minification as opposed to google closure (default set to google closure)
** $vars = array(
**   'closure' => false, 
**   'gzip' => true, 
**   'encode' => true
** );
** $minified = new Minifier( $vars );
**
** NEW as of 3.0.0: Output sha1 hashed time-based filenames for new files to break caching without version numbers
** $vars = array(
**   'closure' => false, 
**   'gzip' => true, 
**   'encode' => true, 
**   'remove_comments' => true, 
**   'hashed_filenames' => true, 
**   'output_log' => true
** );
** $minified = new Minifier( $vars );
**
**------------------------------------------------------------------------------ */

class Minifier {
    
    public $content;
    public $output_file;
    public $extension;
    private $type;
    //Max image size for inclusion
    const IMAGE_MAX_SIZE = 5;
    //For script execution time (src: http://bit.ly/18O3VWw)
    private $mtime;
    //Sum of output messages
    private static $messages = array();
    //array of settings to add-to/adjust
    private $settings = array();
    //List of available config keys that can be set via init
    private $config_keys = array(
        'echo' => true,                 //Return or echo the values
        'encode' => false,              //base64 images from CSS and include as part of the file?
        'timer' => true,                //Ouput script execution time
        'gzip' => false,                //Output as php with gzip?
        'closure' => true,              //Use google closure (utilizes cURL)
        'remove_comments' => true,      //Remove comments, 
        'hashed_filenames' => false,    //Generate hashbased filenames to break caches, 
        'output_log' => false           //Output logs automatically at end of file output
    );
    
    
    /**
     * Construct function
     * @access public
     * @param array $vars
     * @return mixed
     */
    public function __construct( $vars = array() )
    {;
        $this->mtime = microtime( true );
        foreach( $this->config_keys as $key => $default )
        {
            if( isset( $vars[$key] ) )
            {
                self::$messages[]['Minifier Log'] = $key .': '. $vars[$key];
                $this->settings[$key] = $vars[$key];
            }
            else
            {
                self::$messages[]['Minifier Log'] = $key .': '. $default;
                $this->settings[$key] = $default;
            }
        }
        
    } //end __construct()
    
	
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
     * Private function used to automatically add URI schemes to 
     * assets in order for them to have their contents retrieved
     * ONLY WORKS to add schemes to URI's prefixed with "//" to 
     * handle protocol irrelevant loading when using either http or https
     *
     * For example, the following file WOULD work:
     * //ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js
     *
     * While, the following would NOT work:
     * ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js
     *
     * @access private
     * @param string $uri
     * @param string $scheme
     * @return string
     */
    private function add_uri_scheme( $uri, $scheme = 'http:' )
    {
        if( substr( $uri, 0, 2 ) == '//' )
        {
            return parse_url( $uri, PHP_URL_SCHEME ) === null ? $scheme . $uri : $uri;   
        }
        else
        {
            return $uri;
        }
    }
    
    
    /**
     * Private function to determine if files are local or remote
     * Used for merge_images() and minify() to determine if filemtime can be used
     *
     * @access private
     * @param string $file
     * @return bool
     */
    private function remote_file( $file )
    {
        //It is a remote file
        if( substr( $file, 0, 4 ) == 'http' )
        {
            return true;
        }
        //Local file
        else
        {
            return false;
        }
    }

    
    /**
     * Internal function to output everything as a gmdate
     * Prevents issues with servertime vs. PHP time settings by 
     * turning all timestamps into gmt
     * @access private
     * @param int time
     * @return int
     */
    private function gmstamp( $time = '' )
    {
        $time = !empty( $time ) ? $time : time();
        return gmdate( 'U', $time );
    }
    
    
    /**
     * Function to create or retrieve stored data for cachefiles
     * that are generated using hashed filenames
     * Cachedfiles store:
     ** orig filename
     ** hashed filename
     ** timestamp
     ** regenerated timestamp (if the file has been regenerated)
     *
     * Both minify() and merge() run processes to determine filenames and timestamps
     * through this function to unify the placement of hashed_filename checks, and files
     * are created/checked using this function
     *
     * @access private
     * @param string $source_file- always the same- name of NONhashed minified file
     * @param string $reference_file - mainly used to determine accurate file extensions
     * @param bool $regen (wipe to recreate contents of cachefile, defaults to false)
     * @return object (for less-array-ey bracket retrieval)
     */
    private function minified_filedata( $source_file, $reference_files = array(), $regen = false )
    {   
        if( $this->settings['hashed_filenames'] && is_dir( dirname( $source_file ) ) && is_writable( dirname( $source_file ) ) )
        {
            //Reference filename to create
            $cache_refname = sha1( $source_file ) .'.txt';
            
            $checkfile = dirname( $source_file ) . DIRECTORY_SEPARATOR . $cache_refname;
            
            if( file_exists( $checkfile ) && !$regen )
            {
                $data = file_get_contents( $checkfile );
                return (object)unserialize( $data );
            }
            else
            {
                $new_ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
                if( $new_ext == 'php' )
                {
                    $source_file = rtrim( strtolower( $source_file ), '.php' );
                    $new_ext = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) ) .'.php';
                }

                
                $time = $this->gmstamp();
                $data = array(
                    'files' => $reference_files, 
                    'references' => dirname( $source_file ) . DIRECTORY_SEPARATOR. sha1( $time ) . '.'. $new_ext, 
                    'filemtime' => $time, 
                    'generated' => $time
                );

                //If we need to regen, just wipe the contents of the file
                if( $regen === true )
                {
                    $data['regenerated'] = $this->gmstamp();
                }
                $handle = fopen( $checkfile, 'w' ) or error_log( 'Cannot open file:  '.$checkfile );
                fwrite( $handle, serialize( $data ) );
                fclose( $handle );
                return (object)$data;
            }
        }
        else
        {
            if( file_exists( $source_file ) )
            {
                $data = array(
                    'files' => $reference_files, 
                    'references' => $source_file, 
                    'filemtime' => $this->gmstamp( filemtime( $source_file ) ), 
                    'generated' => $this->gmstamp( filemtime( $source_file ) )
                );
            }
            else
            {
                $time = $this->gmstamp();
                $data = array(
                    'files' => $reference_files, 
                    'references' => $source_file, 
                    'filemtime' => $time, 
                    'generated' => $time
                );
            }
            return (object)$data;
        }
    }
    
    
    /**
     * Function to seek out and replace image references within CSS with base64_encoded data streams
     * Used in minify_contents function IF global for $this->encode
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
        $this->directory = dirname( $source_file ) . DIRECTORY_SEPARATOR;

        if( preg_match_all( '/url\((["\']?)((?!["\']?data:).*?\.(gif|png|jpg|jpeg))\\1\)/i', $contents, $this->matches, PREG_SET_ORDER ) )
        {
            $this->find = array();
            $this->replace = array();

            foreach( $this->matches as $this->graphic )
            {

                $this->extension = pathinfo( $this->graphic[2], PATHINFO_EXTENSION );

                $this->image_file = '';

                //See if the file is remote or local
                if( $this->remote_file( $this->graphic[2] ) )
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

            //Log the number of replacements to the console
            self::$messages[]['Minifier Log: merge_images'] = count( $this->replace ) .' files base64_encoded into ' . $source_file;
            
            //Find and replace all the images with the base64 data
            $this->updated_style = str_replace( $this->find, $this->replace, $contents );
            
            return $this->updated_style;

        } //End if( regex for images)
        else
        {
            //No images found in the sheet, just return the contents
            return $contents;
        }
        
    } //end merge_images()
    
	
    /**
     * Private function to handle minification of file contents
     * Supports CSS and JS files
     *
     * @access private
     * @param string $src_file
     * @param bool $run_minification (default true)
     * @return string $content
     */
    private function minify_contents( $src_file, $run_minification = true )
    {
        $this->source = @file_get_contents( $src_file );   
    
        //Log the error and continue if we can't get the file contents
        if( !$this->source )
        {
            self::$messages[]['Minifier ERROR'] = 'Unable to retrieve the contents of '. $src_file . '.  Skipping at '. __LINE__ .' in '. basename( __FILE__ );
            
            //This will cause  potential js errors, but allow the script to continue processing while notifying the user via console
            $this->source = '';
        }
    
        $this->type = strtolower( pathinfo( $src_file, PATHINFO_EXTENSION ) );
    
        $this->output = '';
            
        /**
         * If the filename indicates that the contents are already minified, we'll just return the contents
         * If the switch is flipped (useful for loading things such as jquery via google cdn)
         */
        if( preg_match( '/\.min\./i', $src_file ) || $run_minification === false )
        {
            return $this->source;
        }
        else
        {   
            if( !empty( $this->type ) && $this->type == 'css' )
            {
                $this->content = $this->source;
                //If the param is set to merge images into the css before minifying...
                if( $this->settings['encode'] )
                {
                    $this->content = $this->merge_images( $src_file, $this->content );   
                }
                
                /* remove comments */
                if( $this->settings['remove_comments'] )
                {
                    $this->content = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $this->content );
                }
                
                /* remove tabs, spaces, newlines, etc. */
                $this->content = preg_replace( '/(\s\s+|\t|\n)/', ' ', $this->content );
                /* remove other spaces before/after ; */
                $this->content = preg_replace( array('(( )+{)','({( )+)'), '{', $this->content );
                $this->content = preg_replace( array('(( )+})','(}( )+)','(;( )*})'), '}', $this->content );
                $this->content = preg_replace( array('(;( )+)','(( )+;)'), ';', $this->content );

            } //end $this->type == 'css'
            
            if( !empty( $this->type ) && $this->type == 'js' )
            {
                $this->content = $this->source;
                
                /**
                 * Migrated preg_replace and str_replace custom minification to use google closure API
                 * OR jShrink on 15-Jun-2013 due to js minification irregularities with most regex's: 
                 * https://github.com/tedious/JShrink
                 * https://developers.google.com/closure/compiler/
                 * https://developers.google.com/closure/compiler/docs/api-ref
                 * Accomodates lack of local file for JShrink by getting contents from github
                 * and writing to a local file for the class (just in case)
                 * If bool is passed for 'closure' => true during class initiation, cURL request processes
                 */
                if( $this->settings['closure'] )
                {
                    
                    //Build the data array
                    $data = array(
                        'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
                        'output_format' => 'json', 
                        'output_info' => 'compiled_code', 
                        'js_code' => urlencode( $this->content )
                    );

                    //Compile it into a post compatible format
                    $fields_string = '';
                    foreach( $data as $key => $value )
                    {
                        $fields_string .= $key . '=' . $value . '&';
                    }
                    rtrim( $fields_string, '&' );
                    
                    //Initiate and execute the curl request
                    $h = curl_init();
                    curl_setopt( $h, CURLOPT_URL, 'http://closure-compiler.appspot.com/compile' ); 
                    curl_setopt( $h, CURLOPT_POST, true );
                    curl_setopt( $h, CURLOPT_POSTFIELDS, $fields_string );
                    curl_setopt( $h, CURLOPT_HEADER, false );
                    curl_setopt( $h, CURLOPT_RETURNTRANSFER, 1 );
                    $result = curl_exec( $h );
                    $result_raw = json_decode( $result, true );
                    
                    //If we've made too many requests, or passed bad data, our js will be broken
                    if( isset( $result_raw['serverErrors'] ) && !empty( $result_raw['serverErrors'] ) )
                    {
                        $e_code = $result_raw['serverErrors'][0]['code'];
                        $e_message = $result_raw['serverErrors'][0]['error'];
                        self::$messages[]['Minifier ERROR'] = $e_code . ': '. $e_message . ' File: '. basename( $src_file ) . '.  Returning unminified contents.';
                    }
                    else
                    {
                        $this->content = $result_raw['compiledCode'];   
                    }

                    //close connection
                    curl_close( $h );
                    
                } //end if( $this->settings['closure'] )
                else
                {
                    //Not using google closure, default to JShrink but make sure the file exists
                    if( !file_exists( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'jShrink.php' ) )
                    {
                        self::$messages[]['Minifier Log'] = 'jShrink does not exist locally.  Retrieving...';
                        
                        $this->handle = fopen( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'jShrink.php', 'w' );
                        $this->jshrink = file_get_contents( 'https://raw.github.com/tedivm/JShrink/master/src/JShrink/Minifier.php' );
                        fwrite( $this->handle, $this->jshrink );
                        fclose( $this->handle );
                    }
                
                    //Include jShrink
                    require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'jShrink.php' );
                
                    //Minify the javascript
                    $this->content = JShrink\Minifier::minify( $this->content, array( 'flaggedComments' => $this->settings['remove_comments'] ) );

                } //end if( !$this->settings['closure'] )

            } //end $this->type == 'js'
            
            //Add to the output and return it
            $this->output .= $this->content;
            return $this->output;   
        }
        
    } //end minify_contents()
	
	
    /**
     * Private function to create file with, or without minified contents
     *
     * @access private
     * @param string path to, and name of source file
     * @param string path to, and name of new minified file
     * @param bool $do_minify (default is true) (used for remote files)
     * @return string new filename/location (same as path to variable)
     */
    private function make_min( $src_file, $new_file, $do_minify = true )
    {        
        self::$messages[]['Minifier note'] = 'Writing new file to '. dirname( $new_file );
        
        //Make sure the directory exists and is writable
        if( !is_dir( dirname( $new_file ) ) || !is_writeable( dirname( $new_file ) ) )
        {
            self::$messages[]['Minifier ERROR'] = dirname( $new_file ) . ' is not writable.  Cannot create minified file.';
            trigger_error( dirname( $new_file ) . ' is not writable.  Cannot create minified file.' );
            return false;
        }
        
        //Output gzip data as needed, but default to none
        //Lengthy line usage is intentional to provide cleanly formatted fwrite contents
        $this->prequel = '';
        if( $this->settings['gzip'] )
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
            
            /**
             * If gzip is enabled, the .php extension is added automatically
             * and must be accounted for to prevent files from being recreated
             */
            if( $this->extension != 'php' )
            {
                $new_file = $new_file. '.php';   
            }
            
            //Close out the php row so we can continue with normal content
            $offset = 60 * 60 * 24 * 31;
            $this->prequel .= 'header( \'Content-Encoding: gzip\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Cache-Control: max-age=' . $offset.'\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Expires: ' . gmdate( "D, d M Y H:i:s", time() + $offset ) . ' GMT\' );' . PHP_EOL;
            $this->prequel .= 'header( \'Last-Modified: ' . gmdate( "D, d M Y H:i:s", filemtime( __FILE__ ) ) . ' GMT\' );' . PHP_EOL;
            
            //Add the header content type output for correct rendering
            if( $this->extension == 'css' || ( strpos( $new_file, '.css' ) !== false ) )
            {
                $this->prequel .= 'header( \'Content-type: text/css; charset: UTF-8\' );' . PHP_EOL;   
            }
            if( $this->extension == 'js' || ( strpos( $new_file, '.js' ) !== false ) )
            {
                $this->prequel .= 'header( \'Content-type: application/javascript; charset: UTF-8\' );' . PHP_EOL;   
            }
            
            //Close out the php tag that gets written to the file
            $this->prequel .= '?>' . PHP_EOL;
            
        } //End if( $this->gzip )
        
        //Single files
        if( !is_array( $src_file ) )
        {
            $this->filetag = '/**' . PHP_EOL;
            $this->filetag .= ' * Filename: '. basename( $src_file ) . PHP_EOL;
            $this->filetag .= ' * Generated by MagicMin '.date('Y-m-d'). ' at '. date('h:i:s A') . PHP_EOL;
            $this->filetag .= ' */' . PHP_EOL;
            $this->content = $this->prequel . $this->filetag . $this->minify_contents( $src_file, $do_minify );  
        }
        else
        {
            //Strip the directory names from the $src_file array for security
            $filenames = array_map( array( $this, 'strip_directory' ), $src_file );
            
            //Make a temporary var to store the data and write a TOC
            $this->compiled = '/**' . PHP_EOL;
            $this->compiled .= ' * Table of contents: ' . PHP_EOL;
            $this->compiled .= ' * '. implode( PHP_EOL. ' * ', $filenames ) . PHP_EOL;
            $this->compiled .= ' * Generated by MagicMin: ' . date( 'Y-m-d h:i:s' ). PHP_EOL;
            $this->compiled .= ' */' . PHP_EOL;
            
            //Loop through an array of files to write to the new file
            foreach( $src_file as $this->new_file )
            {
                
                /**
                 * It's relatively safe to assume that remote files being retrieved
                 * already have minified contents (ie. Google CDN hosted jquery)
                 * so prevent re-minification, but default to $do_minify = true;
                 */
                $do_minify = true;
                if( $this->remote_file( $this->new_file ) )
                {
                    //Remote files should not have compressed content
                    $do_minify = false;
                }
        
                $this->compiled .= $this->minify_contents( $this->new_file, $do_minify );
            }
            
            //Write the temporary contents to the full contents
            $this->content = trim( $this->prequel . $this->compiled );
            
            //Remove the temporary data
            unset( $this->compiled );
        
        } //End $src_file is_array

        //If the file already exists, open it and empty it
        if( file_exists( $new_file ) && is_writeable( $new_file ) )
        {
            $f = fopen( $new_file, 'w' );
            fclose( $f );
        }
        
        //Create the new file
        $this->handle = fopen( $new_file, 'w' );
        
        //Log any error messages from the new file creation
        if( !$this->handle )
        {
            self::$messages[]['Minifier ERROR'] = 'Unable to open file:  '.$new_file;
            trigger_error( 'Unable to open file:  '.$new_file );
            return false;
        }
        else
        {
            //Write the minified contents to it
            fwrite( $this->handle, $this->content );
            fclose( $this->handle );
            //Make sure this filemtime syncs up with everything else magicmin does
            touch( $new_file, $this->gmstamp() );
            
            //Log to the console
            self::$messages[]['Minifier Log: New file'] = 'Successfully created '. $new_file;

            //Return filename and location
            return $new_file;   
        }
    
    } //end make_min()
    

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
        //Handle double slash prefixed remote filenames, as well as checking relative filenames
        $src_file = $this->add_uri_scheme( $src_file );
        
        //Since the $file (output) filename is optional, if empty, just add .min.[ext]
        if( empty( $file ) )
        {
            //Get the pathinfo
            $ext = pathinfo( $src_file );
            //Create a new filename
            $file = $ext['dirname'] . DIRECTORY_SEPARATOR . $ext['filename'] . '.min.' . $ext['extension'];

        }
        
        //If we have gzip enabled, we must account for the .php extension
        if( $this->settings['gzip'] && ( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) != '.php' ) )
        {
            $file .= '.php';
        }
        
        $minfile = $this->minified_filedata( $src_file, array( $file ) );
        $file = $minfile->references;
        
        //The source file is remote, and we can't check for an updated version anyway
        if( $this->remote_file( $src_file ) && file_exists( $file ) )
        {
            $this->output_file = $file;
        }
        //The local version doesn't exist, but we don't need to minify
        elseif( $this->remote_file( $src_file ) && !file_exists( $file ) )
        {
            $this->output_file = $this->make_min( $src_file, $file, false );
            
            //Add the filename to the output log
            self::$messages[]['Minifier Log: minify'] = 'Retrieving contents of '.$src_file .' to add to '.$file;
        }
        //The file already exists and doesn't need to be recreated
        elseif( ( file_exists( $file ) && file_exists( $src_file ) ) && ( $this->gmstamp( filemtime( $src_file ) ) < $minfile->filemtime ) )
        {
            
            //No change, so the output is the same as the input
            $this->output_file = $file;

        }
        //The file exists, but the development version is newer
        elseif( ( file_exists( $file ) && file_exists( $src_file ) ) && ( $this->gmstamp( filemtime( $src_file ) ) > $minfile->filemtime ) )
        {
            //Remove the file so we can do a clean recreate
            chmod( $file, 0777 );
            unlink( $file );
            
            //Regen cacheref
            $minfile = $this->minified_filedata( $src_file, array( $file ), true );
            $file = $minfile->references;
            
            //Make the cached version
            $this->output_file = $this->make_min( $src_file, $file );
            
            //Add to the console.log output
            self::$messages[]['Minifier Log: minify'] = 'Made new version of '.$src_file.' into '.$file;
        }
        //The minified file doesn't exist, make one
        else
        {
            //Make the cached version
            $this->output_file = $this->make_min( $src_file, $file );
            
            //Add to the console.log output if desired
            self::$messages[]['Minifier Log: minify'] = 'Made new version of '.$src_file.' into '.$file;
        }

        //Add the ? params if they exist
        if( !empty( $version ) )
        {
            $this->output_file .= '?v='. $version;   
        }
        
        //Return the output filename or echo
        if( $this->settings['echo'] )
        {
            echo $this->output_file;
        }
        else
        {
            return $this->output_file;
        }
        
    } //end minify() 
    
    
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
     * @param mixed $list_or_type (css, js, selective - default is js)
     **** $list_or_type will also accept "selective" array which overrides glob and only includes specified files
     **** $list_or_type array passed files are included in order, and no other files will be included
     **** files must all be the same type in order to prevent eronious output contents (js and css do not mix)
     * @param array $exclude files to exclude
     * @param array $order to specify output order
     * @return string new filenae
     */
    public function merge( $output_filename, $directory, $list_or_type = 'js', $exclude = array(), $order = array() )
    {
        
        /**
         * Added selective inclusion to override glob and exclusion 13-Jun-2013 ala Ray Beriau
         * This assumes the user has passed an array of filenames, in order rather than a file type
         * By doing so, we'll set the directory to indicate no contents, and priorize directly into $order
         */
        if( is_array( $list_or_type ) && !empty( $list_or_type ) )
        {
            //Direct the directory to be an empty array
            $this->directory = array();
            //Utilize the $order variable
            $order = $list_or_type;
        }
        else
        {
            //Open the directory for looping and seek out files of appropriate type
            $this->directory = glob( $directory .'/*.'.$list_or_type );            
        }
        
        /**
         * Reassign the $output_filename if gzip is enabled as we must account for the .php
         * extension in order to prevent the file from being recreated
         */
        if( $this->settings['gzip'] && ( strtolower( pathinfo( $output_filename, PATHINFO_EXTENSION ) ) != '.php' ) )
        {
            $output_filename .= '.php';
        }
        
        $reference_files = ( is_array( $list_or_type ) && !empty( $list_or_type ) ) ? $list_or_type : array( $output_filename );
        $minfile = $this->minified_filedata( $output_filename, $reference_files );
        $minified_name = $minfile->references;
        $minified_filelist = !empty( $minfile->files ) ? $minfile->files : array();

        //Create a bool to determine if a new file needs to be created
        $this->create_new = false;
        
        //Start the array of files to add to the cache
        $this->compilation = array();
        
        //Determine if a specific order is needed, if so remove only those files from glob seek
        if( !empty( $order ) )
        {
            
            self::$messages[]['Minifier Log: Merge order'] = 'Order specified with '. count( $order ) .' files';
            
            foreach( $order as $this->file )
            {
                
                //Handle protocol irrelevant URIs such as '//ajax.google...'
                $this->file = $this->add_uri_scheme( $this->file );
                
                //Check each file for modification greater than the output file if it exists
                if( file_exists( $minified_name ) && ( $this->file != $output_filename ) && ( !$this->remote_file( $this->file ) ) && ( $this->gmstamp( filemtime( $this->file ) ) > $minfile->filemtime ) || !in_array( $this->file, $minified_filelist ) )
                {
                    self::$messages[]['Minifier Log: New File Flagged'] = 'Flagged for update by '. $this->file;
                    $this->create_new = true;
                }
                
                //Add the specified files to the beginning of the use array passed to $this->make_min
                $this->compilation[] = $this->file;
                
            }
            
            //Now remove the same files from the glob directory
            $this->directory = array_diff( $this->directory, $this->compilation );
        
        } //End !empty( $order )

        //Loop through the directory grabbing files along the way
        foreach( $this->directory as $this->file )
        {
         
            //Handle protocol irrelevant URIs such as '//ajax.google...'
            $this->file = $this->add_uri_scheme( $this->file );
            
            //Make sure we didn't want to exclude this file before adding it
            if( !in_array( $this->file, $exclude ) && ( $this->file != $minified_name ) )
            {
                //Check each file for modification greater than the output file if it exists
                if( file_exists( $minified_name ) && ( !$this->remote_file( $this->file ) ) && ( $this->gmstamp( filemtime( $this->file ) ) > $minfile->filemtime ) || !in_array( $this->file, $minified_filelist ) )
                {
                    self::$messages[]['Minifier Log: New File Flagged'] = 'Flagged for update by '. $this->file;
                    $this->create_new = true;
                }
                
                $this->compilation[] = $this->file;
            }
            
        } //End foreach( $this->directory )
        
        //Check to see that we have the same number of files passed to the function as were stored
        if( count( $minified_filelist ) != count( $this->compilation ) )
        {
            $this->create_new = true;
        }

        //Only recreate the file as needed
        if( file_exists( $minified_name ) && $this->create_new )
        {
            //Remove the file so we can do a clean recreate
            chmod( $minified_name, 0777 );
            unlink( $minified_name );
            
            //Regen cacheref
            $minfile = $this->minified_filedata( $output_filename, $this->compilation, true );
            $output_filename = $minfile->references;
            
            //Group and minify the contents
            $this->compressed = $this->make_min( $this->compilation, $output_filename );
            
        }
        elseif( !file_exists( $minified_name ) )
        {
            //Regen cacheref
            $minfile = $this->minified_filedata( $output_filename, $this->compilation, true );
            $output_filename = $minfile->references;
            
            //Group and minify the contents
            $this->compressed = $this->make_min( $this->compilation, $output_filename );   
        }
        else
        {
            $this->compressed = $minified_name;
        }
        
        //Echo or return
        if( $this->settings['echo'] )
        {
            echo $this->compressed;
        }
        else
        {
            return $this->compressed;
        }
        
    } //end merge()
    
    
    /**
     * Output any return data to the javascript console/source of page
     * Usage (assuming minifier is initiated as $minifier):
     * <?php $minifier->logs(); ?>
     *
     * @param none
     * @return string
     */
    public function logs()
    {   
        //Add the timer the console.log output if desired
        if( $this->settings['timer'] )
        {
            self::$messages[]['Minifier Log: timer'] = 'MagicMin processed and loaded in '. ( microtime( true ) - $this->mtime ) .' seconds';
        }
        
        if( !empty( self::$messages ) )
        {
            
            echo PHP_EOL . '<script>' . PHP_EOL;
            foreach( self::$messages as $this->data )
            {
                foreach( $this->data as $this->type => $this->output )
                {
                    echo 'console.log("'.$this->type .' : '. $this->output.'");' . PHP_EOL;   
                }
            }
            echo '</script>' . PHP_EOL;
        
        } //end !empty( $this-messages )

    } //end logs()
    
    
    /**
     * Allow logs to be automatically output at script completion
     * Dependent on 'output_log' configuration variable set to true
     *
     */
    public function __destruct()
    {
        if( $this->settings['output_log'] === true )
        {
            $this->logs();
        }
    } //end __destruct()
    

} //End class Minifier