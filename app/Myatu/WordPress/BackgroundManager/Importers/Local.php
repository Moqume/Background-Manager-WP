<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Importers;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Galleries;
use Myatu\WordPress\BackgroundManager\Images;

/**
 * Importer for Local Directories
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class Local extends Importer
{
    const NAME = 'Local Directory';
    const DESC = 'Imports images from a directory (and optionally its sub-directories) located on the web server';
    
    /**
     * Pre-import settings
     *
     * Renders a directory listing
     */
    static public function preImport(Main $main)
    {
        $root     = trailingslashit(realpath(ABSPATH)); // Starting point
        $root_len = strlen($root);    // Often used, string length of root
        
        // Check if the selected directory falls within the root, and if it does, continue to import (else display pre-import)
        if (isset($_REQUEST['directory']) && !empty($_REQUEST['directory'])) {
            $sel_dir = realpath($root . $_REQUEST['directory']);
            
            if (strpos($sel_dir, $root) === 0)
                return;
        }
        
        // Build directoy listing
        $iterator   = new \RecursiveIteratorIterator(new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        $last_ident = 0; // Tracks identation
        $directory  = '<ul><li id="root"><a href="#">/</a>';
        
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDir()) {
                $cur_dir     = str_replace('\\', '/', substr($fileinfo, $root_len));
                $children    = explode('/', $cur_dir);               
                $child_count = count($children);

                if ($child_count > $last_ident) {
                    // Increase identation 
                    $directory .= '<ul>';
                    $last_ident = $child_count;
                } else if ($child_count < $last_ident) {
                    // Reset identation
                    for ($i = 0; $i < ($last_ident - $child_count); $i++)
                        $directory .= '</li></ul></li>';
                    $last_ident = $child_count;
                } else {
                    $directory .= '</li>';
                }
                
                $directory .= '<li><a href="#" data-dir="' . $cur_dir . '">' . $children[$child_count-1] . '</a>';
            }
        }
        
        $directory .= '</li></ul>';
        
        // Add jsTree
        list($js_url, $version, $debug) = $main->getResourceUrl();
        wp_enqueue_script('jquery-jstree', $js_url . 'vendor/jsTree/jquery.jstree.min.js', array('jquery'), null);
        
        // Render template
        $vars = array(
            'rtl'       => is_rtl(),
            'root'      => $root,
            'directory' => $directory,
        );

        return $main->template->render('importer_local.html.twig', $vars);
    }
       
    /**
     * Performs the import
     *
     * @param mixed $main The object of the Main class
     */
    static public function doImport(Main $main)
    {
        $sub_dirs  = !empty($_REQUEST['sub_dirs']);
        $root      = trailingslashit(realpath(ABSPATH));
        $directory = realpath($root . $_REQUEST['directory']);
        $galleries = new Galleries($main);
        
        // Create the image set
        $desc = sprintf(__('Imported directory "%s"', $main->getName()), $_REQUEST['directory']);
        if ($sub_dirs)
            $desc .= __(' and sub-directories', $main->getName());
        $gallery_id = $galleries->save(0, 'Imported Directory', $desc);
        
        if (!$gallery_id) {
            $main->addDelayedNotice(sprintf(__('Unable to create Image Set <strong>%s</strong>', $main->getName()), $image_set), true);
            return;
        }        
        
        // Import specified directory
        static::importDir($directory, $gallery_id, $main);
        
        // Import sub-directories, if requested
        if ($sub_dirs) {
            $iterator= new \RecursiveIteratorIterator(new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isDir())
                    static::importDir($fileinfo, $gallery_id, $main);
            }
        }
        
        $main->addDelayedNotice(__('Completed import from Local Directory', $main->getName()));
        
        unset($galleries);
    }
    
    /**
     * Import a directoy into the specified gallery
     *
     * @param string $directory The directory to import
     * @param int $gallery_id the Gallery ID (Image Set)
     * @param mixed $main The reference to the Main class object
     */
    static protected function importDir($directory, $gallery_id, $main)
    {
        $iterator = new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        
        foreach ($iterator as $fileinfo)
            if ($fileinfo->isFile() && file_is_valid_image($fileinfo))
                if (!Images::importImage($fileinfo, $gallery_id))
                    $main->addDelayedNotice(sprintf(__('Unable to import <em>%s</em> into Image Set', $main->getName()), $fileinfo), true);
    }
}