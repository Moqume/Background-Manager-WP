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
class WpFlickrBackground
{
    const WP_OPTION_NAME = 'wp-flickr-background';
    
    /**
     * Protected constructor, as this is a purely static class
     */
    protected function __construct() {}
    
    /**
     * Imports galleries from WP Flickr Background
     *
     * @param object $main The object of the Main class
     * @return array Array of import errors, if any. 
     */
    static public function import(Main $main)
    {
        global $wpdb;
        
        $setProgress = function($percentage) { echo '<script type="text/javascript">/* <![CDATA[ */ mainWin.doImportProgress(' . $percentage . '); /* ]]> */</script>'; };
        
        /* Prevent this function from timing out, and set implicit flush on output buffer writes */
        set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level())
            ob_end_clean();
        ob_implicit_flush(true);
        
        echo '<!DOCTYPE html><html><head></head><body><script type="text/javascript">/* <![CDATA[ */ mainWin = window.dialogArguments || opener || parent || top; /* ]]> */</script>';
        
        $errors = array();
        
        // Ensure we have valid options
        if (($options = get_option(static::WP_OPTION_NAME)) === false || !array_key_exists('galleries', $options) || !is_array($options['galleries']) || count($options['galleries']) == 0)
            return;
            
        $galleries = new Galleries($main);
        $images    = new Images($main);
        
        // Turn how many gallery's we process into chunks for progress bar
        $chunks = ceil(100 / count($options['galleries']));
        $chunk  = 0;
        
        for ($i = 1; $i < 101; $i++) {
            $setProgress($i);
            sleep(1);
        }
        return;

        foreach ($options['galleries'] as $wpfbg_gallery) {
            $gallery_id = $galleries->save(0, $wpfbg_gallery['name'], $wpfbg_gallery['desc']);
            
            if (!$gallery_id) {
                $errors[] = sprintf(__('Could not import gallery \'%s\'', $main->getName()), $wpfbg_gallery['name']);
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
                        $errors[] = sprintf(__('Could not import remote image from \'%s\'', $main->getName()), $photo['background']);
                } else {
                    // Strip any -DDDxDDD from the filename within the URL
                    $background_image_url = preg_replace('#^(.*?)(-\d{2,4}x\d{2,4}(?=[.]))(.*)$#', '$1$3', $photo['background']);
                    
                    // Fetch the image ID from the posts/attachments
                    $background_image_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `guid` = %s", $background_image_url));
                    
                    // Change the parent of the image attachment to that of the gallery
                    if ($background_image_id && ($image = get_post($background_image_id))) {
                        wp_insert_attachment($image, false, $gallery_id);
                    } else {
                        $errors[] = sprintf(__('Could not import local image at \'%s\'', $main->getName()), $photo['background']);
                    }
                }
            }
            
            $chunk++;
            $setProgress($chunk * $chunks);
        }
        
        unset($galleries);
        unset($images);
        
        // Finalize progress bar
        $setProgress(100);
        echo '</body></html>';
        
        return $errors;
    }
}