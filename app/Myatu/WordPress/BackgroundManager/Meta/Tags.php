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
 * A meta box allowing the Image Set to be displayed if the post contains certain tags
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Tags extends PostMetabox
{
    const MT_TAGS    = 'myatu_bgm_override_tags';
    const MT_TAGS_OL = 'myatu_bgm_override_tags_ol'; // Overlay
    
    const CACHE_ID = 'myatu_bgm_override_tags_';
    
    protected $title    = 'Override by Tag';
    protected $pages    = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context  = 'side';
    
    private $np_cache = array();
    
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
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $selected_overlay = get_post_meta($id, self::MT_TAGS_OL, true);
        
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
        
        $vars = array(
            'tags'     => get_post_meta($id, self::MT_TAGS, true),
            'overlays' => $overlays,
        );
        
        $this->owner->template->display('meta_gallery_tags.html.twig', $vars);    
    }
    
    /**
     * Event called when a gallery is saved
     *
     * Note: removal of meta data is handled by WordPress already, so
     * there is no need for an onDelete(), unless we do fancy stuff with
     * meta data.
     *
     * @param int $id ID of the gallery being saved
     */
    public function onSave($id)
    {
        $tags    = (isset($_REQUEST['tax_input']) && isset($_REQUEST['tax_input']['post_tag'])) ? $_REQUEST['tax_input']['post_tag'] : '';
        $overlay = (isset($_REQUEST['overlay_tag_override'])) ? $_REQUEST['overlay_tag_override'] : 0;
        
        $this->setSinglePostMeta($id, self::MT_TAGS, $tags);
        $this->setSinglePostMeta($id, self::MT_TAGS_OL, $overlay);
    }
    
    /**
     * Obtains the gallery and overlay IDs for the post based on its tags, if any
     *
     * @param array|bool Returns an array continaing the gallery and overlay ID, or `false` if none
     */
    private function getIds($post_id)
    {
        $cache_gal_id = static::CACHE_ID . $post_id;
        
        // Check if we already have a cached value
        if ($cached_val = get_transient($cache_gal_id))
            return $cached_val;
        
        // Get the tags for the current post
        $post_tags = wp_get_post_tags($post_id, array('fields' => 'names'));
       
        // Grab the galleries
        $galleries = get_posts(array('numberposts' => -1, 'post_type' => \Myatu\WordPress\BackgroundManager\Main::PT_GALLERY));
        
        // Iterate galleries until we found one for which one or more tags match up with the post's tags
        foreach ($galleries as $gallery) {
            $override_tags = get_post_meta($gallery->ID, self::MT_TAGS, true);
            
            if (!empty($override_tags)) {
                $intersect = array_intersect(explode(',', $override_tags), $post_tags); // Find out if this gallery overrides any tags set in the post
                
                if (!empty($intersect)) {
                    // Match found
                    $cached_val = array(
                        'gallery_id' => $gallery->ID,
                        'overlay_id' => get_post_meta($gallery->ID, self::MT_TAGS_OL, true),
                    );
                    
                    set_transient($cache_gal_id, $cached_val, 10);
                    return $cached_val;
                }
            }
        }
        
        // If we reach this point, there's nothing to override
        set_transient($cache_gal_id, false, 10);
        return false;
    }
    
    /**
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