<?php
/*------------------------------------------------------------------------------
** File:		class.magic-min.php
** Class:       MagicMin
** Description:	Javascript and CSS minification/merging class to simplify movement from development to production versions of files
** Version:		1.0
** Updated:     01-Jun-2013
** Author:		Bennett Stone
** Homepage:	www.phpdevtips.com 
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
	    $this->content = file_get_contents( $src_file );
	    
	    $this->type = '';
        if( preg_match( '/.js/', $src_file ) )
        {
            $this->type = 'js';
        }
        if( preg_match( '/.css/', $src_file ) )
        {
            $this->type = 'css';
        }
	    
	    if( !empty( $this->type ) && $this->type == 'css' )
        {
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
            /* remove comments */
            $this->content = preg_replace( "/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/", "", $this->content );
            /* remove tabs, spaces, newlines, etc. */
            $this->content = str_replace( array("\r\n","\r","\t","\n",'  ','    ','     '), '', $this->content );
            /* remove other spaces before/after ) */
            $this->content = preg_replace( array('(( )+\))','(\)( )+)'), ')', $this->content );
        }
        
        return $this->content;
	}
	
	
    /**
     * Private function to create file with, or without minified contents
     *
     * @access public
     * @param string path to, and name of source file
     * @param string path to, and name of new minified file
     * @return string new filename/location (same as path to variable)
     */
    private function make_min( $src_file, $new_file )
    {
        //Start the output
        $this->content = '/* Generated '.date('Y-m-d'). ' at '. date('h:i:s A').' */' . PHP_EOL;

        //Single files
        if( !is_array( $src_file ) )
        {
            $this->content .= $this->minify_contents( $src_file );   
        }
        else
        {
            //Make a temporary var to store the data
            $this->compiled = '';
            
            //Loop through an array of files to write to the new file
            foreach( $src_file as $this->new_file )
            {
                //Add the sourcefile name for clarity
                $this->compiled .= PHP_EOL . PHP_EOL . '/* Source file: '.$this->new_file.' */' . PHP_EOL . PHP_EOL;
                                
                //Add the sourcefile minified content
                $this->compiled .= $this->minify_contents( $this->new_file );
            }
            
            //Write the temporary contents to the full contents
            $this->content = trim( $this->compiled );
        }

        //Create the new file
        $this->handle = fopen( $new_file, 'w' ) or die( 'Cannot open file:  '.$new_file );

        //Write the minified contents to it
        fwrite( $this->handle, $this->content );
        fclose( $this->handle );
        
        //Remove the temporary data
        unset( $this->compiled );

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
        
        //Return the output filename
        echo $this->output_file;
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
     * @param string output filename
     * @param string directory to loop through
     * @param string types
     * @param array files to exclude
     * @return string new filenae
     */
    public function merge( $output_filename, $directory, $type = 'js',  $exclude = array() )
    {
        //Open the directory for looping and seek out files of appropriate type
        $this->directory = glob( $directory .'/*.'.$type );
        
        //Create a bool to determine if a new file needs to be created
        $this->create_new = false;
        
        //Start the array of files to add to the cache
        $this->compilation = array();
        
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
        }

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
        
        echo $this->compressed;
    }

} //End class Minifier