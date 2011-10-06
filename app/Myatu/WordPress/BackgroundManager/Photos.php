<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * The Photos class for the BackgroundManager
 *
 * This is a container class for basic Photo functions
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 */
class Photos
{
    protected $owner;
    protected $np_cache = array();
        
    /** 
     * Constructor
     *
     * @param Main $owner Reference to a WordpressPlugin / Owner object
     */
    public function __construct(Main $owner)
    {
        $this->owner = $owner;
    }
    
    /**
     * Returns all the IDs of Photos in a Gallery
     *
     * @param int $id ID of the Gallery (photo set)
     * @return array An array containing the IDs
     */
    public function getAllPhotoIds($id)
    {
        global $wpdb;
        
        $id = (int)$id;
        
        if ($id == 0)
            return array();
        
        $cache_id = 'myatu_bgm_all_photo_ids_'.$id;
        
        if (!isset($this->np_cache[$cache_id])) {
            $ids = $wpdb->get_results($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `post_parent` = %d AND `post_status` = %s AND `post_type` = %s", $id, 'inherit', 'attachment') . wp_post_mime_type_where('image'), OBJECT_K);
            
            if ($ids !== false)
                $ids = array_keys($ids);
        
            $this->np_cache[$cache_id] = $ids;
        }
        
        return $this->np_cache[$cache_id];
    }
    
    
    /**
     * Obtains the Photos (images) in a Gallery
     *
     * @param int $id ID of the Gallery (photo set)
     * @param array $args Additional arguments to pass to the query (Optional)
     * @return array An array containing the photo objects
     */
    public function get($id, $args = false)
    {
        $id = (int)$id;
        
        if ($id == 0)
            return array();
        
        $cache_id = 'myatu_bgm_photos_' . $id;
        
        if (is_array($args)) {
            $cache_id = 'myatu_bgm_photos_' . md5($id . implode(array_keys($args)) . implode(array_values($args)));
        } else {
            $args = array();
        }
        
        if (!isset($this->np_cache[$cache_id])) {
            $images = get_children(array_merge($args, array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image')));
            
            foreach($images as $image) {
                $image->thumb    = wp_get_attachment_image_src($image->ID);
                $image->meta_alt = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
            }
            
            $this->np_cache[$cache_id] = $images;
        }
        
        return (array)$this->np_cache[$cache_id];
    }
    
    /**
     * Obtains a hash based on the Photos (images) in a Gallery
     *
     * @param int $id ID of the Gallery (photo set)
     * @return string A string containing the hash
     */
    public function getHash($id)
    {
        $id = (int)$id;
        
        if ($id == 0)
            return '';
            
        return md5(implode($this->getAllPhotoIds($id)));
    }

    /**
     * Obtains the number of Photos (images) in a Gallery
     *
     * @param int $id ID of the Gallery (photo set)
     * @return int Number of photos in the gallery
     */
    public function getCount($id)
    {
        return count($this->getAllPhotoIds($id));
    }    
}