<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Meta;

use Pf4wp\Meta\PostMetabox;

/**
 * Abstract class for Taxonomy Meta Boxes
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
abstract class Taxonomy extends PostMetabox
{
    protected $meta_tax         = '';       // The name of the Meta under which taxonomy overrides are stored
    protected $taxonomy         = '';       // The taxonomy being handled
    protected $tax_fields       = 'ids';    // The taxonomy fields to compare to (ie., 'ids', 'names', etc)
    
    /** 
     * Constructor 
     *
     * Adds filters to allow overriding the gallery/overlay.
     */
    public function __construct($owner, $auto_register = true)
    {
        add_filter('myatu_bgm_active_gallery', array($this, 'onActiveGallery'), 20, 1);
        add_filter('myatu_bgm_active_overlay', array($this, 'onActiveOverlay'), 20, 1);
        
        parent::__construct($owner, $auto_register);
    }
    
    /**
     * Renders the Metabox Contents
     *
     * @param int $id The ID of the Gallery
     * @param string $template The template to display/render
     * @param array $vars The variables to pass to the template
     */
    protected function doRender($id, $template, $vars)
    {
        $selected_overlay = get_post_meta($id, $this->meta_tax . '_ol', true);
        
        // List of overlays
        $overlays = array_merge(array(
            array(
                'value'    => -1,
                'desc'     => __('-- None (deactivated) --', $this->owner->getName()),
                'selected' => ($selected_overlay == -1),
            ),
            array(
                'value'    => 0,
                'desc'     => __('Default Overlay', $this->owner->getName()),
                'selected' => ($selected_overlay == false),            
            )
        ), $this->owner->getSettingOverlays($selected_overlay)); 

        $vars = array_merge($vars, array('overlays' => $overlays));
        
        $this->owner->template->display($template, $vars);    
    }
    
    /**
     * Saves the Meta Data
     *
     * @param int $id ID of the gallery being saved
     * @param mixed $tax The taxonomies selected
     * @param string|int $overlay The overlay selected
     */
    public function doSave($id, $tax, $overlay)
    {
        $this->setSinglePostMeta($id, $this->meta_tax, $tax);
        $this->setSinglePostMeta($id, $this->meta_tax . '_ol', $overlay);
    }
    
    /**
     * Obtains the gallery and overlay IDs for the post based on its categories, if any
     *
     * @param int $post_id The Post ID to check against
     */
    protected function getIds($post_id)
    {
        $cache_id = 'myatu_bgm_override_' . $this->taxonomy . '_' . $post_id;
        
        // Check if we already have a cached value
        if ($cached_val = get_transient($cache_id))
            return unserialize($cached_val);
        
        // Get the taxonomies for the current post
        $post_tax = wp_get_object_terms($post_id, $this->taxonomy, array('fields' => $this->tax_fields));
        
        // Grab the galleries
        $galleries = get_posts(array('numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_type' => \Myatu\WordPress\BackgroundManager\Main::PT_GALLERY));
        
        // Iterate galleries until we found one for which one or more tags match up with the post's tags
        foreach ($galleries as $gallery) {
            $overrides = get_post_meta($gallery->ID, $this->meta_tax, true);
            
            if (is_array($overrides) && !empty($overrides)) {
                $intersect = array_intersect($overrides, $post_tax); // Find out if this taxonomy overrides any taxonomies set in the post
                
                if (!empty($intersect)) {
                    // Match found
                    $cached_val = array(
                        'gallery_id' => $gallery->ID,
                        'overlay_id' => get_post_meta($gallery->ID, $this->meta_tax . '_ol', true),
                    );
                    
                    // Cache response before returning - WP claims it will serialize, but doesn't seem to work well for this
                    set_transient($cache_id, serialize($cached_val), 10);
                    return $cached_val;
                }
            }
        }
        
        // If we reach this point, there's nothing to override
        set_transient($cache_id, serialize(false), 10);
        return false;
    }
    
    /**
     * Event called on myatu_bgm_active_gallery filter
     */
    public function onActiveGallery($gallery_id)
    {
        $post_id = get_the_ID();
        
        // Return the default if it isn't a single post, or no post ID returned
        if (!$post_id || !is_single())
            return $gallery_id;
        
        // Return the gallery ID (@see getIds())
        if ($ids = $this->getIds($post_id))
            return $ids['gallery_id'];
        
        return $gallery_id;
    }
    
    /**
     * Event called on myatu_bgm_active_overlay filter
     */
    public function onActiveOverlay($overlay_id)
    {
        $post_id = get_the_ID();
        
        // Return the default if it isn't a single post, or no post ID returned
        if (!$post_id || !is_single())
            return $overlay_id;
            
        if ($ids = $this->getIds($post_id)) {
            if ($ids['overlay_id'] == -1) {
                return 0; // Disabled
            } else if ($ids['overlay_id'] == false) {
                return $overlay_id;
            } else {
                return $ids['overlay_id']; // Override requested
            }
        }
        
        return $overlay_id; // Default
    }    
}