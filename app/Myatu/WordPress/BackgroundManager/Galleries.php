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
     * When IDs are provided, there's no need to a specify bulk operation (_all),
     * though if they are not provided, specifying whether it is a bulk operation 
     * or not is used to determine the correct nonce.
     *
     * @param string $action The action to perform
     * @param mixed $ids A single ID, array of IDs or array of objects containing an 'id', to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    protected function doTDR($action, $ids = false)
    {
        // Check for user capability and present nonce
        if (!current_user_can('edit_theme_options'))
            return false;
            
        $action = strtolower(trim($action));
            
        if (!$ids) {
            // If ids have not been specifically specified, we need to check the nonce first
            if (!isset($_REQUEST['ids']))
                return true; // Nothing to do, all done succesfuly!
                
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

        if (is_array($ids) && isset($ids[0]) && is_object($ids[0])) {
            foreach ($ids as $id_key => $id_obj)
                $ids[$id_key] = $id_obj->id;
        } else {
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
        }
        
        // Check if there's something left to do after sanitizing the ids.
        if (empty($ids))
            return true;
        
        // Initially set to false
        $result = false;
        
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
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional used for nonce verification)
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
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional used for nonce verification)
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
     * @param bool $bulk Set to `true` if this is a bulk action, `false` otherwise (Default, optional used for nonce verification)
     * @param int|bool $ids The IDs to perform the action on (Optional, retrieved from $_REQUEST otherwise)
     * @return int|bool Returns an array with the IDs on which the actions were performed, or `false` if there was an error.
     */
    public function delete($bulk = false, $ids = false)
    {
        return $this->doTDR('delete' . (($bulk) ? '_all' : ''), $ids);
    }
    
    /**
     * Sets a meta value to a selected gallery (by ID)
     *
     * This will replace an existing meta value with the same key
     * or delete the entry if the value is empty.
     *
     * @param int $id The ID of the gallery
     * @param string $meta_key The meta key
     * @param string $meta_value The value for the meta key
     * @param bool $unique Set to `true` if the meta key is unique (default), or `false` otherwise (optional)
     * @return bool Returns `true` if successful, `false` otherwise
     */
    public function setSingleMeta($id, $meta_key, $meta_value, $unique = true)
    {
        $result   = true;
        $old_meta = get_post_meta($id, $meta_key, $unique);
       
        if (empty($old_meta)) {
            if (!empty($meta_value))
                $result = add_post_meta($id, $meta_key, $meta_value, $unique);
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
     * @param bool $is_draft Whether this is a new (draft) entry
     * @return int|bool Returns the post ID if successful, `false` otherwise
     */
    public function save($id, $title, $description, $is_draft = false)
    {   
        if (trim($title) == '') {
            if (!$is_draft)
                return false;
         
            $title = 'Auto Draft';
        }
        
        // Save post
        $post = array(
            'ID'           => ($id == 'new' || (int)$id == 0) ? false : (int)$id,
            'post_type'    => Main::PT_GALLERY,
            'post_status'  => ($is_draft) ? 'auto-draft' : 'publish',
            'post_title'   => $title,
            'post_content' => $description,
        );
        
        $result = wp_insert_post($post);
        
        if ($result == 0)
            return false;
            
        return $result;
    }
   
    /** 
     * Helper function for Actions, to redirect the user back to the origin 
     * without exposing specific action details (for the click- and refresh-happy
     * users out there).
     *
     * @param bool|array An array to add using add_query_arg(), or `false` if not required.
     */
    private function redirectUserActionOrigin($add_arg = false)
    {
        $origin = remove_query_arg(array('action', 'ids', '_wpnonce'));
        
        if (is_array($add_arg))
            $origin = add_query_arg($add_arg, $origin);
        
        wp_redirect($origin);
        
        die(); 
    }
    
    /* ---------- User Actions ---------- */
    
    /**
     * Empties the Trash
     */
    public function emptyTrashUserAction()
    {
       global $wpdb;
       
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'bulk-galleries')) 
            wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));        
       
       $ids = $wpdb->get_results($wpdb->prepare("SELECT `id` FROM `{$wpdb->posts}` WHERE `post_type` = %s AND `post_status` = 'trash'", Main::PT_GALLERY));
       
       $result = $this->delete(true, $ids);
       
        if ($result !== false) {
            $this->owner->addDelayedNotice(__('The Trash has been emptied.', $this->owner->getName()));
        } else {
            $this->owner->addDelayedNotice(__('There was a problem emptying the Trash.', $this->owner->getName()), true);
        }        
        
        $this->redirectUserActionOrigin();        
    }
    
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
                _n('Image Set', 'Image Sets', count($result), $this->owner->getName()),
                esc_url(add_query_arg(
                    array(
                        'action'   => $action,
                        'ids'      => implode(',', $result),
                        '_wpnonce' => $nonce
                    )
                ))
            ));
        } else {
           $this->owner->addDelayedNotice(__('There was a problem moving the Image Set(s) to the Trash.', $this->owner->getName()), true);
        }
        
        $this->redirectUserActionOrigin();   
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
            $this->owner->addDelayedNotice(sprintf(__('%s restored from the Trash.', $this->owner->getName()), _n('Image Set', 'Image Sets', count($result), $this->owner->getName())));
        } else {
            $this->owner->addDelayedNotice(__('There was a problem restoring the Image Set(s) from the Trash.', $this->owner->getName()), true);
        }        
        
        $this->redirectUserActionOrigin(); 
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
            $this->owner->addDelayedNotice(sprintf(__('%s permanently deleted.', $this->owner->getName()), _n('Image Set', 'Image Sets', count($result), $this->owner->getName())));
        } else {
            $this->owner->addDelayedNotice(__('There was a problem deleting the Image Set(s).', $this->owner->getName()), true);
        }        
        
        $this->redirectUserActionOrigin();
    }
    
    /**
     * Performs a Save action (initiated by edit form)
     *
     * This will retrieve all neccesary data from the $_REQUEST variable and
     * check against the nonce for validity.
     */
    public function saveUserAction()
    {
        $id     = (int)$_REQUEST['edit'];
        $is_new = (get_post_status($id) == 'auto-draft');
        
        if (!isset($_REQUEST['_nonce']) && !wp_verify_nonce($_REQUEST['_nonce'], Main::NONCE_EDIT_GALLERY . $id))
            wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));
        
        // Nonce passed, do sanity check on fields
        $post_title = trim($_REQUEST['post_title']);
        
        if ($post_title == '') {
            AdminNotice::add(__('Please specify a name for this Image Set.', $this->owner->getName()), true);
            return false;
        }
               
        $saved_id = $this->save($id, $post_title, $_REQUEST['post_content']);
        
        if (!$saved_id) {
            AdminNotice::add(__('The Image Set could not be saved.', $this->owner->getName()), true);
            return false;
        }

        // Let the user know if the image set was successfuly added or saved.
        $did_what = ($is_new) ? __('added', $this->owner->getName()) : __('saved', $this->owner->getName());
        $this->owner->addDelayedNotice(sprintf(__('The Image Set was successfully %s.', $this->owner->getName()), $did_what));
        
        $this->redirectUserActionOrigin(array('edit' => $saved_id));
    }
    
}