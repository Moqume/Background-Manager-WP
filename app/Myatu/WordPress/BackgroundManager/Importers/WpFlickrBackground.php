<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Importers;

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
    const DESC = 'Imports the galleries from WP Flickr Background into the Background Manager.';
    
    static public function preImport(Main $main)
    {
        return 'Hello World! <input type="hidden" name="test" value="testing" />';
        //$main->template->render('pub_footer.html.twig', $vars);
    }
    
    /**
     * Imports galleries from WP Flickr Background
     *
     * @param object $main The object of the Main class
     */
    static public function doImport(Main $main)
    {
        global $wpdb;
        
        // Ensure we have valid options
        if (($options = get_option(static::WP_OPTION_NAME)) === false || !array_key_exists('galleries', $options) || !is_array($options['galleries']) || count($options['galleries']) == 0)
            return;
            
        $galleries = new Galleries($main);
        $images    = new Images($main);
        
        // Turn how many gallery's we process into chunks for progress bar
        $chunks = ceil(100 / count($options['galleries']));
        $chunk  = 0;
        
        // TESTING!!!!
        for ($i = 1; $i < 20; $i++) {
            static::setProgress($i);
            sleep(1);
        }
        return;

        foreach ($options['galleries'] as $wpfbg_gallery) {
            $gallery_id = $galleries->save(0, $wpfbg_gallery['name'], $wpfbg_gallery['desc']);
            
            if (!$gallery_id)
                continue;
            
            // If we have custom CSS, add this as a meta to the gallery
            if (!empty($wpfbg_gallery['customcss']))
                add_post_meta($gallery_id, \Myatu\WordPress\BackgroundManager\Meta\Stylesheet::MT_CSS, $wpfbg_gallery['customcss'], true);
                
            foreach ($wpfbg_gallery['photos'] as $photo) {
                if ($photo['id'][0] != 'L') {
                    // Images that do not start with an "L" are only available via a remote URL.
                    media_sideload_image($photo['background'], $gallery_id);
                } else {
                    // Strip any -DDDxDDD from the filename within the URL
                    $background_image_url = preg_replace('#^(.*?)(-\d{2,4}x\d{2,4}(?=\.))(.*)$#', '$1$3', $photo['background']);
                    
                    // Fetch the image ID from the posts/attachments
                    $background_image_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `guid` = %s", $background_image_url));
                    
                    // Change the parent of the image attachment to that of the gallery
                    if ($background_image_id && ($image = get_post($background_image_id)))
                        wp_insert_attachment($image, false, $gallery_id);
                }
            }
            
            $chunk++;
            static::setProgress($chunk * $chunks);
        }
        
        unset($galleries);
        unset($images);
    }
}