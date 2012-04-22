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
     * Construct a base SQL statement
     *
     * @since 1.0.36
     * @param array $what What columns to SELECT from the DB
     */
    private function base_select($what = array('*'))
    {
        global $wpdb;
        
        foreach ($what as $what_k => $what_v) {
            if ($what_v != '*')
                $what[$what_k] = '`' . $what_v . '`';
        }
        
        $what = implode(',', $what);
        
        return "SELECT {$what} FROM `{$wpdb->posts}` WHERE `post_status` = 'inherit' AND `post_type` = 'attachment' " . wp_post_mime_type_where('image') . " ";
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
            
        $cache_id = 'all_image_ids_'.$id;
        
        if (!isset($this->np_cache[$cache_id])) {
            $ids = $wpdb->get_results($this->base_select(array('ID')) . "AND `post_parent` = {$id} ORDER BY `menu_order`", OBJECT_K);
            
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
        
        $cache_id = 'images_' . $id;
        
        if (is_array($args)) {
            $cache_id = 'images_' . md5($id . implode(array_keys($args)) . implode(array_values($args)));
        } else {
            $args = array();
        }
        
        if (!isset($this->np_cache[$cache_id])) {
            // Make sure we have an ordered gallery before grabbing images
            $this->reorderIfNeeded($id);
            
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
    
    /**
     * Re-order images in a gallery in sequential order
     *
     * @since 1.0.36
     * @param int $id Gallery ID
     */
    public function reorder($id)
    {
        global $wpdb;
        
        $id = (int)$id;
        
        if ($id == 0)
            return;

        $images           = $wpdb->get_results($this->base_select(array('ID','menu_order')) . "AND `post_parent` = {$id} ORDER BY `menu_order`", OBJECT_K);
        $count            = 1;
        $invalidate_cache = false;
        
        foreach ($images as $image) {
            if ($image->menu_order != $count) {
                $image->menu_order = $count;
                wp_update_post($image);
                
                $invalidate_cache = true;
            }
            
            $count++;
        }
        
        // Invalidate np_cache if need be
        if ($invalidate_cache)
            $this->np_cache = array();
    }
    
    /**
     * Re-orders the images in a gallery if there's a need for it
     *
     * If there's an image with an order of 0 found, the images are re-ordered
     *
     * @since 1.0.36
     * @param int $id Gallery ID
     * @return bool Returns true if re-ordered
     */
    public function reorderIfNeeded($id)
    {
        global $wpdb;
        
        $id = (int)$id;
        
        if ($id == 0)
            return false;
            
        $order            = $this->getCount($id); // Initial order
        $invalidate_cache = false;
        $images           = $wpdb->get_results($this->base_select(array('ID','menu_order')) . "AND `post_parent` = {$id} AND `menu_order` = 0 ORDER BY `post_date` ASC", OBJECT_K);

        // Images with a zero order, ordered by the date they've been added, are given a new order
        foreach ($images as $image) {
            $image->menu_order = $order;
            wp_update_post($image);
            
            $invalidate_cache = true;
            $order++;
        }
        
        // If changed were made, invalidate cache and ensure all images are in sequential order
        if ($invalidate_cache) {
            $this->np_cache = array();
            
            $this->reorder($id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Sorts a selection of IDs by their respective order
     *
     * This is required for bulk moves
     *
     * @since 1.0.36
     * @param array $ids Array containing the IDs
     * @param bool $reverse Reverse sort order (ascending by default)
     * @return array
     */
    public function getSortedByOrder($ids, $reverse = false)
    {
        global $wpdb;

        // Sanitize
        foreach ($ids as $id_k => $id_v) {
            if (!is_int($id_v)) {
                if (is_numeric($id_v) && (int)$id_v != 0) {
                    $ids[$id_k] = (int)$id_v;
                } else {
                    unset($ids[$id_k]);
                }
            }
        }
        
        $sql_ids   = implode(',', $ids);
        $sql_order = ($reverse) ? 'DESC' : 'ASC';
        
        if ($sql_ids != '')
            $ids = $wpdb->get_col($this->base_select(array('ID')) . "AND `ID` in ({$sql_ids}) ORDER BY `menu_order` {$sql_order}");
        
        return array_map(function($v) { return (int)$v; }, $ids);
    }
    
    /**
     * Changes the order of an image
     *
     * @since 1.0.36
     * @param int $id Image ID
     * @param bool $inc Whether to increase (true) or decrease (false) the order. Increase by default.
     */
    public function changeOrder($id, $inc = true)
    {
        global $wpdb;
        
        $id = (int)$id;
        
        if ($id == 0)
            return false;
            
        $image = get_post($id);
        
        if (!$image || $image->post_type != 'attachment' || $image->post_parent == 0)
            return false;
            
        $gallery_id = $image->post_parent;
        
        // Ensure we have an order for all the images, and if things were changed, that we have the correct menu order
        if ($this->reorderIfNeeded($gallery_id))
            $image = get_post($id);
        
        // Save the menu_order for later
        $current_order = $image->menu_order;            
        
        // Determine wether to move it up or down in the order
        if ($inc) {
            $image->menu_order++;
        } else {
            $image->menu_order--;
        }
        
        // We cannot lower the order down to zero - lowest possible
        if ($image->menu_order == 0)
            return false;
    
        // Find out if there's already image(s) with the new order
        $images_at_order = $wpdb->get_results($this->base_select(array('ID', 'menu_order')) . "AND `post_parent` = {$gallery_id} AND `menu_order` = {$image->menu_order}", OBJECT_K);
        
        // Swap the order for those already at the new order
        foreach ($images_at_order as $image_at_order) {
            $image_at_order->menu_order = $current_order;
            wp_update_post($image_at_order);
        }
        
        // Set the new order
        wp_update_post($image);
 
        // Invalidate cache as changes were made
        $this->np_cache = array();
        
        // Ensure all images have a sequential order
        $this->reorder($gallery_id);
        
        return true;
    }
}