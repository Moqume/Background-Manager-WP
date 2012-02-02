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
 * Importer for WP Flickr Background
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class WpFlickrBackground extends Importer
{
    const WP_OPTION_NAME = 'wp-flickr-background';
    
    // Overrides
    const NAME = 'WP Flickr Background Importer';
    const DESC = 'Imports the galleries from WP Flickr Background into the Background Manager. Note: This will import ALL available galleries.';
    
    /**
     * Returns the active status of the importer
     *
     * @return bool
     */
    static public function active()
    {
        // Only active if ...
        if (!current_user_can('upload_files') ||                            // the user is permitted to add files to the Media Library
            !PluginInfo::isInstalled('WP Flickr Background') ||             // the WP Flickr Background is installed (regardless of active)
            ($options = get_option(static::WP_OPTION_NAME)) === false ||    // the options exists
            !array_key_exists('galleries', $options) ||                     // the options contain one or more galleries
            !is_array($options['galleries']) ||
            count($options['galleries']) == 0)
            return false;
     
        return true;
    }
    
    /**
     * Imports galleries from WP Flickr Background
     *
     * @param object $main The object of the Main class
     */
    static public function doImport(Main $main)
    {
        global $wpdb;
        
        $options    = get_option(static::WP_OPTION_NAME);
        $galleries  = new Galleries($main);
        $images     = new Images($main);
        
        // Turn how many gallery's we process into chunks for progress bar
        $chunks = ceil(100 / count($options['galleries']) -1);
        $chunk  = 0;
        
        foreach ($options['galleries'] as $wpfbg_gallery) {
            $image_set = sprintf(__('%s (Imported)', $main->getName()), $wpfbg_gallery['name']);
            $gallery_id = $galleries->save(0, $image_set, $wpfbg_gallery['desc']);
            
            if (!$gallery_id) {
                $main->addDelayedNotice(sprintf(__('Unable to create Image Set <strong>%s</strong>', $main->getName()), $image_set), true);
                continue;
            }
            
            // If we have custom CSS, add this as a meta to the gallery
            if (!empty($wpfbg_gallery['customcss']))
                add_post_meta($gallery_id, \Myatu\WordPress\BackgroundManager\Meta\Stylesheet::MT_CSS, $wpfbg_gallery['customcss'], true);
                
            foreach ($wpfbg_gallery['photos'] as $photo) {
                if ($photo['id'][0] != 'L') {
                    // Images that do not start with an "L" are only available via a remote URL.
                    $r = media_sideload_image($photo['background'], $gallery_id);
                    
                    if (is_wp_error($r))
                        $main->addDelayedNotice(sprintf(__('Unable to import image <em>%s</em> into Image Set <strong>%s</strong>', $main->getName()), $photo['background'], $image_set), true);
                } else {
                    // Strip any -DDDxDDD from the filename within the URL
                    $background_image_url = preg_replace('#^(.*?)(-\d{2,4}x\d{2,4}(?=\.))(.*)$#', '$1$3', $photo['background']);
                    
                    // Fetch the image ID from the posts/attachments
                    $background_image_id = $wpdb->get_var($wpdb->prepare("SELECT `ID` FROM `{$wpdb->posts}` WHERE `guid` = %s", $background_image_url));
                    
                    // Change the parent of the image attachment to that of the gallery
                    if ($background_image_id && ($image = get_post($background_image_id)))
                        $r = wp_insert_attachment($image, false, $gallery_id);
                    
                    if (!$background_image_id || !$image || !$r)
                        $main->addDelayedNotice(sprintf(__('Unable to import image <em>%s</em> into Image Set <strong>%s</strong>', $main->getName()), $background_image_url, $image_set), true);
                }
            }
            
            $chunk++;
            static::setProgress($chunk * $chunks);
        }
        
        // And voila!
        $main->addDelayedNotice(__('Completed import from WP Flickr Background', $main->getName()));
        
        unset($galleries);
        unset($images);
    }
}