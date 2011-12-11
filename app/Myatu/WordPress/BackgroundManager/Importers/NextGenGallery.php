<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
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
 * Importer for NextGEN Gallery
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class NextGenGallery extends Importer
{
    // Overrides
    const NAME = 'NextGEN Gallery Importer';
    const DESC = 'Imports one or more galleries from NextGEN Gallery into the Background Manager.';
    
    /**
     * Returns the active status of the importer
     *
     * @return bool
     */
    static public function active()
    {
        global $wpdb;
        
        if (!PluginInfo::isInstalled('NextGEN Gallery') || !($gallery_count = (int)$wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->prefix}ngg_gallery`")))
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
        
		$galleries = $wpdb->get_results("SELECT `gid`, `title`, COUNT(`galleryid`) `pictures` FROM `{$wpdb->prefix}ngg_gallery` LEFT JOIN `{$wpdb->prefix}ngg_pictures` ON `{$wpdb->prefix}ngg_pictures`.`galleryid` = `{$wpdb->prefix}ngg_gallery`.`gid` GROUP BY `gid`");

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
        
        return $main->template->render('importer_nextgengallery.html.twig', $vars);
    }
    
    /**
     * Imports galleries from NextGEN Gallery
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
        $images    = new Images($main);

        // Collect initial data
        $ngg_gallery   = $wpdb->get_row($wpdb->prepare("SELECT `title`, `path`, `galdesc` FROM `{$wpdb->prefix}ngg_gallery` WHERE `gid` = %d", $_REQUEST['gallery']));
        $ngg_pictures  = $wpdb->get_results($wpdb->prepare("SELECT `filename`, `alttext`, `description` FROM `{$wpdb->prefix}ngg_pictures` WHERE `galleryid` = %d", $_REQUEST['gallery']));
        
        if (!$ngg_gallery || !$ngg_pictures) {
            $main->addDelayedNotice(__('There was a problem obtaining NextGEN Gallery database details', $main->getName()), true);
            return;
        }
        
        // Create the image set
        $image_set  = sprintf(__('%s (Imported)', $main->getName()), $ngg_gallery->title);
        $gallery_id = $galleries->save(0, $image_set, $ngg_gallery->galdesc);
        
        if (!$gallery_id) {
            $main->addDelayedNotice(sprintf(__('Unable to create Image Set <strong>%s</strong>', $main->getName()), $image_set), true);
            return;
        }
        
        // If we don't have any pictures to import, then finish here (so at least we have the gallery)
        if (count($ngg_pictures) == 0)
            return;

        // Turn how many gallery's we process into chunks for progress bar
        $chunks = ceil(100 / count($ngg_pictures) -1);
        $chunk  = 0;

        // Import the pictures
        foreach ($ngg_pictures as $ngg_picture) {
            $image_file = ABSPATH . trailingslashit($ngg_gallery->path) . $ngg_picture->filename;
            
            if (@is_file($image_file)) {
                // We use media_handle_sideload, which will delete the file after completion, so original is copied first.
                $temp_file = trailingslashit(sys_get_temp_dir()) . 'bgm' . mt_rand(10000, 99999) . basename($image_file);
                $copied    = copy($image_file, $temp_file);
                
                if ($copied) {
                    if ($id = media_handle_sideload(array('name' => basename($image_file), 'tmp_name' => $temp_file), $gallery_id, $ngg_picture->alttext, array('post_content' => $ngg_picture->description)))
                        update_post_meta($id, '_wp_attachment_image_alt', $ngg_picture->alttext);
                }
                
                if (!$copied || !$id)
                    $main->addDelayedNotice(sprintf(__('Unable to import image <em>%s</em> into Image Set <strong>%s</strong>', $main->getName()), $image_file, $image_set), true);
            }
           
            $chunk++;
            static::setProgress($chunk * $chunks);
        }
        
        $main->addDelayedNotice(__('Completed import from NextGEN Gallery', $main->getName()));
        
        unset($galleries);
        unset($images);
    }
}