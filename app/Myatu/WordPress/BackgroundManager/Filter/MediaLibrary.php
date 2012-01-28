<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Filter;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * The Media Library filter class 
 * 
 * This class modifies (filters) certain features or displayed information
 * provided by the 'Insert/Upload' Media Library screen (iframe), if it is
 * specifically shown on the Image Set edit screen.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Filter
 */
class MediaLibrary
{
    protected $owner;
    
    const FILTER = 'media_library';
    
    const META_LINK = 'myatu_bgm_link';
    
    /** Options that we use, passed by get_media_item_args action */
    private $media_item_args = array();
    
    /**
     * Constructor
     *
     * @param Main $owner Reference to the owner of this class (of WordpressPlugin \ Main type)
     */
    public function __construct(Main $owner)
    {
        $this->owner = $owner;
        
        unset($_POST['save']); // Prevents media_upload_gallery() from being called - see wp-admin/media.php

        $order = 50;
        
        add_filter('attachment_fields_to_edit', array($this, 'onAttachmentFields'), $order, 2);
        add_filter('media_upload_form_url', array($this, 'onUploadFormUrl'), $order, 2);
        add_filter('media_upload_tabs', array($this, 'onUploadTabs'), $order);
        add_filter('get_media_item_args', array($this, 'onMediaItemArgs'), $order);
        add_filter('post_mime_types', array($this, 'onMediaMimeTypes'), $order);
        add_filter('media_upload_mime_type_links', array($this, 'onMediaTypeLinks'), $order);
        add_filter('media_send_to_editor', array($this, 'onSendToEditor'), $order, 3);
        add_filter('attachment_fields_to_save', array($this, 'onAttachmentFieldsToSave'), $order, 2);
    }
    
    /**
     * Filter Media Library Attachment Form Fields
     *
     * This removes fields from the Media Library upload screen that are not 
     * needed (and thus confuse the end user). See wp-admin/includes/media.php
     *
     * Since 1.0.6 we also include a 'link' form field.
     *
     * @param array $form_fields The form fields being displayed, as specified by WordPress
     * @param object $attachment The attachment (post)
     * @return array The (modified) form fields
     */
    public function onAttachmentFields($form_fields, $attachment)
    {
        unset($form_fields['url']);
        unset($form_fields['align']);
        unset($form_fields['image-size']);
        
        $attachment_id = (is_object($attachment) && $attachment->ID) ? $attachment->ID : 0;
        $filename      = esc_html(basename($attachment->guid));
        
        // Add a 'link' form field
        $form_fields['link'] = array(
            'label' => __('Background URL', $this->owner->getName()),
            'helps' => __('Optional link URL for the background', $this->owner->getName()),
            'value' => get_post_meta($attachment_id, static::META_LINK, true),
        );
        
        // 'Add to' button
        $send = '';
        if (isset($this->media_item_args['send']) && $this->media_item_args['send'])
            $send = get_submit_button( __('Add to Image Set', $this->owner->getName()), 'button', "send[$attachment_id]", false);
        
        // 'Delete' or 'Trash' button
        $delete = '';
        if (isset($this->media_item_args['delete']) && $this->media_item_args['delete']) {
            if (!EMPTY_TRASH_DAYS) {
                $delete = sprintf(
                    '<a href="%s" id="del[%d]" class="delete">%s</a>',
                    wp_nonce_url('post.php?action=delete&amp;post=' . $attachment_id, 'delete-attachment_' . $attachment_id),
                    $attachment_id,
                    __('Delete Permanently', $this->owner->getName())
                );
            } elseif (!MEDIA_TRASH) {
                $delete = sprintf(
                    '<a href="#" class="del-link" onclick="document.getElementById(\'del_attachment_%d\').style.display=\'block\';return false;">%s</a>'.
                    '<div id="del_attachment_%1$d" class="del-attachment updated" style="display:none;padding:10px">%s<br /><br />'.
                    '<a href="%s" id="del[%1$d]" class="button">%s</a> '.
                    '<a href="#" class="button" onclick="this.parentNode.style.display=\'none\';return false;">%s</a>'.
                    '</div>',
                    $attachment_id,
                    __('Delete', $this->owner->getName()),
                    sprintf(__('You are about to delete <strong>%s</strong>.'), $filename),
                    wp_nonce_url('post.php?action=delete&amp;post='  .$attachment_id, 'delete-attachment_' . $attachment_id),
                    __('Continue', $this->owner->getName()),
                    __('Cancel', $this->owner->getName())
                );
            } else {
                $delete = sprintf(
                    '<a href="%s" id="del[%d]" class="delete">%s</a>'.
                    '<a href="%s" id="undo[%2$d]" class="undo hidden">%s</a>',
                     wp_nonce_url('post.php?action=trash&amp;post=' . $attachment_id, 'trash-attachment_' . $attachment_id),
                     $attachment_id,
                    __('Move to Trash', $this->owner->getName()),
                    wp_nonce_url('post.php?action=untrash&amp;post=' . $attachment_id, 'untrash-attachment_' . $attachment_id),
                    __('Undo', $this->owner->getName())
                );
            }
        }
        
        if ($send || $delete)
            $form_fields['buttons'] = array('tr' => "\t\t<tr class='submit'><td></td><td class='savesend'>$send $delete</td></tr>\n"); 
        
        return $form_fields;
    }
    
    /**
     * Filter Media Library Upload Tabs
     *
     * This removes the 'From Url' tab. See wp-admin/includes/media.php
     *
     * @param array $tabs The list of tabs to be displayed (type => title association)
     */
    public function onUploadTabs($tabs)
    {
        global $wpdb;
        
        unset($tabs['type_url']);
        unset($tabs['gallery']);
        unset($tabs['nextgen']);
        
        // Grab a count of available mime types
        list($post_mime_types, $avail_post_mime_types) = wp_edit_attachments_query();
        $num_posts  = array();
        $_num_posts = (array) wp_count_attachments();
        $matches    = wp_match_mime_types(array_keys($post_mime_types), array_keys($_num_posts));
        foreach ($matches as $_type => $reals) {
            foreach ( $reals as $real )
                if ( isset($num_posts[$_type]) )
                    $num_posts[$_type] += $_num_posts[$real];
                else
                    $num_posts[$_type] = $_num_posts[$real];
        }
        
        // If we don't have anything in 'images' (or any other type a 3rd party allows us to use), hide `Library` tab too.
        if (empty($num_posts))
            unset($tabs['library']);
        
        return $tabs;
    }
    
    /**
     * Filters the allowed mime types for the Image Set to images only
     *
     * @param array $mime_types The mime types as specified by WordPress
     * @return array The (modified) array of mime types
     */
    public function onMediaMimeTypes($mime_types)
    {
        unset($mime_types['video']);
        unset($mime_types['audio']);
        
        return $mime_types;
    }
    
    /**
     * Filters the 'All Types' from the type links
     *
     * This also sneaks in a hidden field, that ensures our filter is 
     * retained on a Search or Filter request.
     *
     * @param array $type_links The type types as specified by WordPress
     * @return array The (modified) array of type links
     */
    public function onMediaTypeLinks($type_links)
    {
        array_shift($type_links);
        
        // Sneak in a hidden field
        if (count($type_links) > 0)
            $type_links[0] .= sprintf('<input type="hidden" name="filter" value="%s" />', static::FILTER);
        
        return $type_links;
    }    
    
    /**
     * Filter that ensures we keep the right Attachment Fields, by adding the filter query arg
     *
     * See wp-admin/includes/media.php
     *
     * @param string $form_action_url The action URL as specified by WordPress
     * @param string $type The media type (ie., 'image', 'video')
     * @return The (modified) form action URL
     */
    public function onUploadFormUrl($form_action_url, $type)
    {
        return add_query_arg('filter', static::FILTER, $form_action_url);
    }

    /**
     * Saves the arguments of the current media item being displayed as a local variable for later use
     *
     * See wp-admin/includes/media.php
     *
     * @params array $args The list of arguments
     * @retrun array The (modified) list of arguments
     */
    public function onMediaItemArgs($args)
    {
        $this->media_item_args = $args;

        return $args;
    }
    
    /**
     * What to send to the Gallery Editor if a image needs to be attached
     */
    public function onSendToEditor($html, $send_id, $attachment)
    {
        return $send_id;
    }
    
    /**
     * Saves the 'link' form field
     *
     * @since 1.0.6
     * @see onAttachmentFields
     * @param array $post
     * @param array $attachments
     */
    public function onAttachmentFieldsToSave($post, $attachments)
    {
        if (isset($attachments['link'])) {
            $attachment_id = $post['ID'];
            
            $link     = get_post_meta($attachment_id, static::META_LINK, true);
            $new_link = wp_strip_all_tags(stripslashes($attachments['link']));
            
            if ($link != $new_link)
                update_post_meta($attachment_id, static::META_LINK, $new_link);
        }
        
        return $post;
    }
}