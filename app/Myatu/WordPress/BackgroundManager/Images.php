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
 * The Images class for the BackgroundManager
 *
 * This is a container class for basic Image functions
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 */
class Images
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
     * Returns all the IDs of Images in a Gallery
     *
     * @param int $id ID of the Gallery (image set)
     * @return array An array containing the IDs
     */
    public function getAllImageIds($id)
    {
        global $wpdb;
        
        $id = (int)$id;
        
        if ($id == 0)
            return array();
        
        $cache_id = 'myatu_bgm_all_image_ids_'.$id;
        
        if (!isset($this->np_cache[$cache_id])) {
            $ids = $wpdb->get_results($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `post_parent` = %d AND `post_status` = %s AND `post_type` = %s", $id, 'inherit', 'attachment') . wp_post_mime_type_where('image'), OBJECT_K);
            
            if ($ids !== false)
                $ids = array_keys($ids);
        
            $this->np_cache[$cache_id] = $ids;
        }
        
        return $this->np_cache[$cache_id];
    }
    
    /**
     * Returns a random image ID from all available images in a gallery
     *
     * @param int $id ID of the Gallery (image set)
     * @return int|false Random image ID, or `false` if no images available
     */
    public function getRandomImageId($id)
    {
        $image_ids = $this->getAllImageIds($id);
        
        if (empty($image_ids))
            return false;
            
        return $image_ids[mt_rand(0, count($image_ids)-1)];
    }
    
    
    /**
     * Obtains the images in a Gallery
     *
     * @param int $id ID of the Gallery (image set)
     * @param array $args Additional arguments to pass to the query (Optional)
     * @return array An array containing the image objects
     */
    public function get($id, $args = false)
    {
        $id = (int)$id;
        
        if ($id == 0)
            return array();
        
        $cache_id = 'myatu_bgm_images_' . $id;
        
        if (is_array($args)) {
            $cache_id = 'myatu_bgm_images_' . md5($id . implode(array_keys($args)) . implode(array_values($args)));
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
     * Obtains a hash based on the images in a Gallery
     *
     * @param int $id ID of the Gallery (image set)
     * @return string A string containing the hash
     */
    public function getHash($id)
    {
        $id = (int)$id;
        
        if ($id == 0)
            return '';
            
        return md5(implode($this->getAllImageIds($id)));
    }

    /**
     * Obtains the number of images in a Gallery
     *
     * @param int $id ID of the Gallery (image set)
     * @return int Number of images in the gallery
     */
    public function getCount($id)
    {
        return count($this->getAllImageIds($id));
    }
    
    /**
     * Imports an image into the specified Gallery
     *
     * @since 1.0.11
     * @param string $file Path or URL to the file
     * @param int $id ID of the Gallery (image set)
     * @param string $title Optional title of the image
     * @param string $desc Optional description of the image
     * @param string $alttext Optional alternative text for the image
     * @param array $extra_post_data Optional array containing additional post data
     * @return int|bool Returns the ID of the imported image, or `false` on error
     */
    static public function importImage($file, $id, $title = '', $desc = '', $alttext = '', $extra_post_data = array())
    {
        $result    = false;
        $temp_file = false;
        
        if ($id && $file) {
            // Determine if we need to download the file first
            if (preg_match('#http[s]?:\/\/#i', $file)) {
                // It's a URL, dowload it
                $temp_file = download_url($file);
                
                if (!file_is_valid_image($temp_file)) {
                    // Downloaded file is not a valid image, invalidate $temp_file
                    @unlink($temp_file);
                    $temp_file = false;
                }
            } else {
                // It's not a URL, make a copy of the orignal file if possible (as side-loading deletes it otherwise)
                if (@is_readable($file) && file_is_valid_image($file)) {
                    $temp_file = trailingslashit(sys_get_temp_dir()) . 'bgm' . mt_rand(10000, 99999) . basename($file);
                    
                    if (!copy($file, $temp_file)) {
                        // Invalidate $temp_file if we could not create a temporary copy of the image file
                        @unlink($temp_file);
                        $temp_file = false;                        
                    }
                }
            }
            
            if ($temp_file) {
                $post_data = array();
                
                if ($title)
                    $post_data['post_title'] = $title;
                    
                if ($desc)
                    $post_data['post_content'] = $desc;
                
                // Allow extra post data to overwrite/add to existing post data
                $post_data = array_merge($post_data, $extra_post_data);
                
                // Sideload the image
                $id = media_handle_sideload(array('name' => basename($file), 'tmp_name' => $temp_file), $id, null, $post_data);
                
                if (!is_wp_error($id)) {
                    $result = $id;
                
                    // Add the alt text
                    if ($alttext)
                        update_post_meta($id, '_wp_attachment_image_alt', $alttext);
                }                   
            }
                
            @unlink($temp_file); // Ensure the temp file cleaned up
        }
        
        return $result;
    }
}