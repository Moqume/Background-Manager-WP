<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Myatu\WordPress\BackgroundManager\Main;
use Pf4wp\Notification\AdminNotice;

/**
 * The Galleries class for the BackgroundManager
 *
 * This is a container class for basic Gallery functions
 *
 * @author Mike Green <myatus@gmail.com>
 * @version 0.0.0.2
 * @package BackgroundManager
 */
class Galleries
{
    protected $owner;
    
    /** Constructor */
    public function __construct(Main $owner)
    {
        $this->owner = $owner;
    }
    
    /**
     * Performs a Trash, Delete or Restore action on one or more Gallery IDs
     *
     * The possible actions are `trash`, `delete` or `restore` and their
     * bulk operation counterparts with the `_all` suffix.
     *
     * @param string $action The action to perform
     * @param mixed $ids A single ID or array of IDs to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    public function doTDR($action, $ids = false)
    {
        // Check for user capability and present nonce
        if (!current_user_can('edit_theme_options'))
            return false;
            
        if (!$ids) {
            // If ids have not been specifically specified, we need to check the nonce first
            if (!isset($_REQUEST['ids']))
                return false;
                
            $ids = $_REQUEST['ids'];
            
            switch($action) {
                case 'delete':
                    $nonce_context = Main::NONCE_DELETE_GALLERY . $ids;
                    break;
                
                case 'trash':
                    $nonce_context = Main::NONCE_TRASH_GALLERY . $ids;
                    break;
                
                case 'restore':
                    $nonce_context = Main::NONCE_RESTORE_GALLERY . $ids;
                    break;
                    
                default:
                    $nonce_context = 'bulk-galleries';
                    break;
            }

            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], $nonce_context)) 
                wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));            
        
            // Nonce passed, set ids
        }        

        // Ensure the ids is always an array, and seperate multiple ids in their own array value
        if (!is_array($ids))
            $ids = explode(',', trim($ids));

        // Sanitize $ids to integer values only
        foreach ($ids as $id_key => $id_val)
            if (!is_int($id_val)) {
                if (!is_numeric($id_val)) {
                    unset($ids[$id_key]);
                } else {
                    $ids[$id_key] = intval($id_val);
                }
            }
        
        // Check if there's something left to do after sanitizing the ids.
        if (empty($ids))
            return false;
        
        // Initially set to false
        $do_trash = false;
        $result   = false;
        
        switch ($action) {
            case 'delete':
            case 'delete_all':
                foreach ($ids as $id)
                    if (($result = wp_delete_post($id, true)) == false)
                        break;
                        
                break;
            
            case 'trash':
            case 'trash_all':
                foreach ($ids as $id)
                    if (($result = wp_trash_post($id)) == false)
                        break;
                        
                break;
                
                    
            case 'restore':
            case 'restore_all':
                foreach ($ids as $id)
                    if (($result = wp_untrash_post($id)) == false)
                        break;
                        
                break;
        }
        
        if ($result !== false)
            return $ids;
        
        return false;
    }
    
    /**
     * Sends one or more Galleries to the Trash
     *
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional)
     * @param mixed $ids A single ID or array of IDs to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    public function trash($bulk = false, $ids = false)
    {
        return $this->doTDR('trash' . (($bulk) ? '_all' : ''), $ids);
    }
    
    /**
     * Restores one or more Galleries from the Trash
     *
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional)
     * @param mixed $ids A single ID or array of IDs to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    public function restore($bulk = false, $ids = false)
    {
        return $this->doTDR('restore' . (($bulk) ? '_all' : ''), $ids);
    }
    
    /**
     * Permanently deletes one or more Galleries
     *
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional)
     * @param int|bool $ids The IDs to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    public function delete($bulk = false, $ids = false)
    {
        return $this->doTDR('delete' . (($bulk) ? '_all' : ''), $ids);
    }
    
    /**
     * Adds a single meta value to a selected gallery (by ID)
     *
     * This will replace an existing meta value with the same key
     * or delete the entry if the value is empty.
     *
     * @param int $id The ID of the gallery
     * @param string $meta_key The meta key
     * @param string $meta_value The value for the meta key
     * @return bool Returns `true` if successful, `false` otherwise
     */
    public function addSingleMeta($id, $meta_key, $meta_value)
    {
        $result   = true;
        $old_meta = get_post_meta($id, $meta_key, true);
       
        if (empty($old_meta)) {
            if (!empty($meta_value))
                $result = add_post_meta($id, $meta_key, $meta_value, true);
        } else {
            if (empty($meta_value)) {
                $result = delete_post_meta($id, $meta_key);
            } else {
                $result = update_post_meta($id, $meta_key, $meta_value, $old_meta);
            }
        }
        
        return $result;
    }
    
    /**
     * Saves a gallery
     *
     * If the ID is `new` or zero (0), then a new gallery will be added,
     * otherwise the existing gallery will be replaced.
     *
     * @param int|string $id The id of the gallery to save, or `new` (or zero) if it is a new gallery
     * @param string $title The title of the gallery
     * @param string $description The description of the gallery
     * @return int|bool Returns the post ID if successful, `false` otherwise
     */
    public function save($id, $title, $description)
    {   
        if ($title == '')
            return false;
            
        // Save post
        $post = array(
            'ID'           => ($id == 'new' || (int)$id == 0) ? false : (int)$id,
            'post_type'    => Main::PT_GALLERY,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $description,
        );
        
        $result = wp_insert_post($post);
        
        if ($result == 0)
            return false;
            
        return $result;
    }
   
    
    /** Helper function for Actions, to redirect the user back to the origin without exposing specific action details */
    private function redirectOrigin()
    {
        wp_redirect(remove_query_arg(array('action', 'ids', '_wpnonce')));
        
        die(); 
    }
    
    /* ---------- User Actions ---------- */
    
    /**
     * Performs a Trash action (initiated by a link or list bulk action)
     *
     * This will retrieve the IDs to send to the Trash from the $_REQUEST
     * variable, and check against a nonce to see if is valid.
     *
     * @see doTDR
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise
     */
    public function trashUserAction($bulk)
    {
        $result = $this->trash($bulk);
        
        if ($result !== false) {
            if ($bulk) {
                $action = 'restore_all';
                $nonce  = wp_create_nonce('bulk-galleries'); 
            } else {
                $action = 'restore';
                $nonce  = wp_create_nonce(Main::NONCE_RESTORE_GALLERY . $result[0]);
            }
            
            $this->owner->addDelayedNotice(sprintf(
                __('%s moved to the Trash. <a href="%s">Undo</a>', $this->owner->getName()),
                ucfirst(_n('photo set', 'photo sets', count($result), $this->owner->getName())),
                esc_url(add_query_arg(
                    array(
                        'action'   => $action,
                        'ids'      => implode(',', $result),
                        '_wpnonce' => $nonce
                    )
                ))
            ));
        } else {
           $this->owner->addDelayedNotice(__('There was a problem moving the photo set(s) to the Trash.', $this->owner->getName()), true);
        }
        
        $this->redirectOrigin();   
    }

    /**
     * Performs a Restore action (initiated by a link or list bulk action)
     *
     * This will retrieve the IDs to restore from the Trash from the $_REQUEST
     * variable, and check against a nonce to see if is valid.
     *
     * @see doTDR
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise
     */
    public function restoreUserAction($bulk)
    {
        $result = $this->restore($bulk);
        
        if ($result !== false) {
            $this->owner->addDelayedNotice(sprintf(__('%s restored from the Trash.', $this->owner->getName()), ucfirst(_n('photo set', 'photo sets', count($result), $this->owner->getName()))));
        } else {
            $this->owner->addDelayedNotice(__('There was a problem restoring the photo set(s) from the Trash.', $this->owner->getName()), true);
        }        
        
        $this->redirectOrigin(); 
    }
    
    /**
     * Performs a Delete action (initiated by a link or list bulk action)
     *
     * This will retrieve the IDs to permanently delete from the $_REQUEST
     * variable, and check against a nonce to see if is valid.
     *
     * @see doTDR
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise
     */
    public function deleteUserAction($bulk)
    {
        $result = $this->delete($bulk);
        
        if ($result !== false) {
            $this->owner->addDelayedNotice(sprintf(__('%s permanently deleted.', $this->owner->getName()), ucfirst(_n('photo set', 'photo sets', count($result), $this->owner->getName()))));
        } else {
            $this->owner->addDelayedNotice(__('There was a problem deleting the photo set(s).', $this->owner->getName()), true);
        }        
        
        $this->redirectOrigin();
    }
    
    /**
     * Performs a Save action (initiated by edit form)
     *
     * This will retrieve all neccesary data from the $_REQUEST variable and
     * check against the nonce for validity.
     */
    public function saveUserAction()
    {
        $id = strtolower(trim($_REQUEST['edit']));
        
        if (!isset($_REQUEST['_nonce']) && !wp_verify_nonce($_REQUEST['_nonce'], Main::NONCE_EDIT_GALLERY . $id))
            wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));
        
        // Nonce passed, do sanity check on fields
        $post_title = trim($_REQUEST['post_title']);
        
        if ($post_title == '') {
            AdminNotice::add(__('Please specify a name for this Photo Set.', $this->owner->getName()), true);
            return;
        }
               
        $saved_id = $this->save($id, $post_title, $_REQUEST['post_content']);
        
        if (!$saved_id) {
            AdminNotice::add(__('The Photo Set could not be saved.', $this->owner->getName()), true);
            return;
        }
        
        // Add meta(s)
        if (!$this->addSingleMeta($saved_id, Main::MT_CSS, trim($_REQUEST['meta_css'])))
            $this->owner->addDelayedNotice(__('There was a problem adding the custom CSS metadata.', $this->owner->getName()), true);

        // Let the user know if the photo set was successfuly added or saved.
        $did_what = ($id == 'new') ? __('added', $this->owner->getName()) : __('saved', $this->owner->getName());
        $this->owner->addDelayedNotice(sprintf(__('The photo set was successfully %s.', $this->owner->getName()), $did_what));

        wp_redirect(add_query_arg('edit', $saved_id));
        die();
    }
    
}