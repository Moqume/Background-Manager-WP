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
 * @since 1.0.14
 */
abstract class Taxonomy extends PostMetabox implements \Pf4wp\Dynamic\DynamicInterface
{
    protected $meta_tax         = '';       // The name of the Meta under which taxonomy overrides are stored
    protected $taxonomy         = '';       // The taxonomy being handled
    
    const MT_OVERLAY = '_ol';
    const MT_COLOR   = '_bgc';
    
    /** 
     * Constructor 
     *
     * Adds filters to allow overriding the gallery/overlay.
     */
    public function __construct($owner, $auto_register = true)
    {
        add_filter('myatu_bgm_active_gallery',   array($this, 'onActiveGallery'), 20, 1);
        add_filter('myatu_bgm_active_overlay',   array($this, 'onActiveOverlay'), 20, 1);
        add_filter('myatu_bgm_background_color', array($this, 'onBackgroundColor'), 20, 1);
        
        parent::__construct($owner, $auto_register);
    }
    
    /**
     * Return whether the dynamic class is active
     *
     * @return bool
     */
    public static function isActive()
    {
        return false;
    }
    
    /**
     * Info for dynamic loading
     */
    public static function info()
    {
        return array(
            'name'   => '', // Not used
            'desc'   => '', // Not used
            'active' => static::isActive(),
        );
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
        wp_enqueue_script('farbtastic');
        wp_enqueue_style('farbtastic');
        
        $selected_overlay = get_post_meta($id, $this->meta_tax . static::MT_OVERLAY, true);
        $background_color = get_post_meta($id, $this->meta_tax . static::MT_COLOR, true);
        
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

        $vars = array_merge($vars, array('overlays' => $overlays, 'background_color' => $background_color));
        
        $this->owner->template->display($template, $vars);    
    }
    
    /**
     * Saves the Meta Data
     *
     * @param int $id ID of the gallery being saved
     * @param mixed $tax The taxonomies selected
     * @param string|int $overlay The overlay selected
     * @param string $background_color The background color to use
     */
    public function doSave($id, $tax, $overlay, $background_color = '')
    {
        // Sanity check for color
        if (!preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $background_color))
            $background_color = '';
        
        $this->setSinglePostMeta($id, $this->meta_tax, $tax);
        $this->setSinglePostMeta($id, $this->meta_tax . static::MT_OVERLAY, $overlay);
        $this->setSinglePostMeta($id, $this->meta_tax . static::MT_COLOR,   $background_color);
    }
    
    /**
     * Obtains the gallery and overlay IDs for the post based on its categories, if any
     *
     * @param int $post_id The Post ID to check against
     * @param mixed $tax The queried object containing the taxonomy details
     */
    protected function getOverrideIds($post_id, $tax = null)
    {
        $cache_id = 'myatu_bgm_' . md5('override' . $this->taxonomy . (is_null($tax) ? $post_id : $tax->slug));
        
        // Check if we already have a cached value
        if ($cached_val = get_transient($cache_id))
            return unserialize($cached_val);
        
        // Grab the galleries
        $galleries = get_posts(array('numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_type' => \Myatu\WordPress\BackgroundManager\Main::PT_GALLERY));
        
        // Iterate galleries
        foreach ($galleries as $gallery) {
            $overriding_tax = get_post_meta($gallery->ID, $this->meta_tax, true); // Grab the meta containing the overriding tax from gallery
            
            if (is_array($overriding_tax) && !empty($overriding_tax)) {
                $match = false;
                
                if (is_null($tax)) {
                    // Check if the specified post contains an overrding tax
                    $match = is_object_in_term($post_id, $this->taxonomy, $overriding_tax);
                } else {
                    // Check if the specified tax is in the overriding tax
                    $match = (in_array($tax->term_id, $overriding_tax) ||
                              in_array($tax->name,    $overriding_tax) ||
                              in_array($tax->slug,    $overriding_tax));
                }
                
                // Match found
                if ($match) {
                    $cached_val = array(
                        'gallery_id'       => $gallery->ID,
                        'overlay_id'       => get_post_meta($gallery->ID, $this->meta_tax . static::MT_OVERLAY, true),
                        'background_color' => get_post_meta($gallery->ID, $this->meta_tax . static::MT_COLOR,   true),
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
     * Helper function to obtain the Override ID for a specific item
     *
     * @param string|bool $item The item to override (ie., 'gallery_id', 'overlay_id), `false` if none found
     */
    protected function getOverrideId($item)
    {
        $ids = false;
        
        if (is_tax() || is_category() || is_tag()) {
            // Taxonomy page
            $qo = get_queried_object();
            
            if ($qo->taxonomy == $this->taxonomy)
                $ids = $this->getOverrideIds(0, $qo);
        } else if (is_single() && ($post_id = get_the_ID())) {
            $ids = $this->getOverrideIds($post_id);
        }
        
        if (is_array($ids) && array_key_exists($item, $ids))
            return $ids[$item];
            
        return false;
    }
    
    /**
     * Event called on myatu_bgm_active_gallery filter
     */
    public function onActiveGallery($gallery_id)
    {
        if ($id = $this->getOverrideId('gallery_id'))
            return $id;        
        
        return $gallery_id;
    }
    
    /**
     * Event called on myatu_bgm_active_overlay filter
     */
    public function onActiveOverlay($overlay_id)
    {
        if ($id = $this->getOverrideId('overlay_id')) {
            if ($id == -1) {
                return 0; // Disabled
            } else if ($id == false) {
                return $overlay_id;
            } else {
                return $id; // Override requested
            }
        }
        
        return $overlay_id; // Default
    }
    
    /**
     * Event called on myatu_bgm_background color filter
     */
    public function onBackgroundColor($color)
    {
        $m_color = $this->getOverrideId('background_color');
        
        if (!empty($m_color))
            return $m_color;
            
        return $color; // Default
    }
}