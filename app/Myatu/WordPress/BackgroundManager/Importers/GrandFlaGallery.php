<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Importers;

use Pf4wp\Info\PluginInfo;
use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Galleries;
use Myatu\WordPress\BackgroundManager\Images;

/**
 * Importer for GRAND FlAGallery
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class GrandFlaGallery extends Importer
{
    // Overrides
    const DYN_NAME = 'GRAND FlAGallery Importer';
    const DYN_DESC = 'Imports one or more galleries from GRAND Flash Albmum Gallery into the Background Manager.';
    
    /**
     * Returns the active status of the importer
     *
     * @return bool
     */
    static public function isActive()
    {
        global $wpdb;
        
        if (!current_user_can('upload_files') || !PluginInfo::isInstalled('GRAND Flash Album Gallery') || !($gallery_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}flag_gallery`")))
            return false;
     
        return true;
    }
    
    /**
     * Pre-import settings
     *
     * Allows the selection of a particular gallery
     */
    static public function preImport(Main $main)
    {
        global $wpdb;
        
		$galleries = $wpdb->get_results("SELECT `gid`, `title`, COUNT(`galleryid`) `pictures` FROM `{$wpdb->prefix}flag_gallery` LEFT JOIN `{$wpdb->prefix}flag_pictures` ON `{$wpdb->prefix}flag_pictures`.`galleryid` = `{$wpdb->prefix}flag_gallery`.`gid` GROUP BY `gid`");

        if (!$galleries)
            return;
       
        if (isset($_REQUEST['gallery'])) {
            foreach($galleries as $gallery)
                if ($gallery->gid == $_REQUEST['gallery'])
                    return; // A valid gallery was selected */
        }
        
        // Variables to pass on to template
        $vars = array(
            'galleries' => $galleries,
        );
        
        return $main->template->render('importer_grandflagallery.html.twig', $vars);
    }
    
    /**
     * Imports galleries from GRAND FlAGallery
     *
     * @param object $main The object of the Main class
     */
    static public function doImport(Main $main)
    {
        global $wpdb;
        
        // Just in case
        if (!isset($_REQUEST['gallery']) || empty($_REQUEST['gallery']))
            return;
            
        $galleries = new Galleries($main);
        
        // Collect initial data
        $flag_gallery   = $wpdb->get_row($wpdb->prepare("SELECT `title`, `path`, `galdesc` FROM `{$wpdb->prefix}flag_gallery` WHERE `gid` = %d", $_REQUEST['gallery']));
        $flag_pictures  = $wpdb->get_results($wpdb->prepare("SELECT `filename`, `alttext`, `description` FROM `{$wpdb->prefix}flag_pictures` WHERE `galleryid` = %d", $_REQUEST['gallery']));
        
        if (!$flag_gallery || !$flag_pictures) {
            $main->addDelayedNotice(__('There was a problem obtaining GRAND FlAGallery database details', $main->getName()), true);
            return;
        }
        
        // Create the image set
        $image_set  = sprintf(__('%s (Imported)', $main->getName()), $flag_gallery->title);
        $gallery_id = $galleries->save(0, $image_set, $flag_gallery->galdesc);
        
        if (!$gallery_id) {
            $main->addDelayedNotice(sprintf(__('Unable to create Image Set <strong>%s</strong>', $main->getName()), $image_set), true);
            return;
        }
        
        // If we don't have any pictures to import, then finish here (so at least we have the gallery)
        if (count($flag_pictures) == 0)
            return;

        // Turn how many gallery's we process into chunks for progress bar
        $chunks = ceil(100 / count($flag_pictures) -1);
        $chunk  = 0;

        // Import the pictures
        foreach ($flag_pictures as $flag_picture) {
            $image_file = ABSPATH . trailingslashit($flag_gallery->path) . $flag_picture->filename;
            
            if (@is_file($image_file)) {
                if (!Images::importImage($image_file, $gallery_id, $flag_picture->alttext, $flag_picture->description, $flag_picture->alttext))
                    $main->addDelayedNotice(sprintf(__('Unable to import image <em>%s</em> into Image Set <strong>%s</strong>', $main->getName()), $image_file, $image_set), true);
            }
           
            $chunk++;
            static::setProgress($chunk * $chunks);
        }
        
        $main->addDelayedNotice(__('Completed import from GRAND FlAGallery', $main->getName()));
        
        unset($galleries);
    }
}