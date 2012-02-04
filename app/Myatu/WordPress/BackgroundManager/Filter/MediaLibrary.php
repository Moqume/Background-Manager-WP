<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Filter;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Common\FlickrApi;
use Myatu\WordPress\BackgroundManager\Images;

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
        
        add_action('admin_print_styles-media-upload-popup', array($this, 'onPrintStyles'), $order);
        add_action('media_upload_bgm_url', array($this, 'onBgmUrl'), $order); // "From External Source" tab
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
        
        $gallery_id    = (isset($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : 0;
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
        if (substr($attachment->post_mime_type, 0, 5) == 'image') {
            if (!$attachment->post_parent) {
                $send = get_submit_button( __('Add to Image Set', $this->owner->getName()), 'button', "send[$attachment_id]", false);
            } else if ($attachment->post_parent != $gallery_id) {
                // The image is already attached, but not to this Image Set. Allow it to be copied (@see onSendToEditor)
                $parent      = get_post($attachment->post_parent);
                $parent_name = '';
                
                if ($parent_type = get_post_type_object(get_post_type($parent)))
                    $parent_name = (!empty($parent_type->labels->singular_name)) ? $parent_type->labels->singular_name : $parent_type->labels->name;
                
                $send = '<p><span class="description">' . sprintf('Currently attached to %s "%s"', $parent_name, $parent->post_title) . '</span></p>';
                $send .= get_submit_button( __('Copy to Image Set', $this->owner->getName()), 'button', "send[$attachment_id]", false);
            }
        }
        
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
     * This removes the 'From Url' and 'From Gallery' tabs. See wp-admin/includes/media.php
     * It also removes the 'NextGEN Gallery' tab, and inserts our own 'Downlaod from URL' tab
     *
     * @param array $tabs The list of tabs to be displayed (type => title association)
     */
    public function onUploadTabs($tabs)
    {
        global $wpdb;
        
        unset($tabs['type_url']);
        unset($tabs['gallery']);
        unset($tabs['nextgen']);
        
        // Insert the 'Download from URL' uploader
        if (current_user_can('upload_files')) {
            $bgm_url_title = __('Download from URL', $this->owner->getName());
            if (count($tabs) > 1) {
                // Insert it immediately after the first tab
                $c     = 0;
                $_tabs = array();
                foreach ($tabs as $tab_idx => $tab) {
                    if ($c == 1)
                        $_tabs['bgm_url'] = $bgm_url_title;
                    
                    $_tabs[$tab_idx] = $tab;
                    $c++;
                }
                $tabs = $_tabs;
            } else {
                $tabs['bgm_url'] = $bgm_url_title;
            }
        }
        
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
     *
     * If the image is already attached, we duplicate it first so it can be re-attached (bypasses WordPress single attachment limitation)
     *
     */
    public function onSendToEditor($_html, $send_id, $_attachment)
    {
        $result     = $send_id; // Default response
        $attachment = get_post($send_id, ARRAY_A); // Original attachment
        $gallery_id = (isset($_REQUEST['post_id'])) ? $_REQUEST['post_id'] : 0;
        
        // Check if the image is already attached to something other than the current gallery
        if (($gallery_id && $attachment['post_parent']) && $attachment['post_parent'] != $gallery_id) {
            $orig_image = get_attached_file($attachment['ID']);
            
            // Obtain original image details
            $alttext = get_post_meta($attachment['ID'], '_wp_attachment_image_alt', true);  // ALT
            $link    = get_post_meta($attachment['ID'], static::META_LINK, true);           // Background URL
            $details = array_merge($attachment, array(
                'ID'            => 0,
                'post_parent'   => $gallery_id,
                'ancestors'     => array(),
                'guid'          => '',
            ));    
            
            // Import the image
            if ($id = Images::importImage($orig_image, $gallery_id, '', '', $alttext, $details)) {
                $result = $id;
                
                // Re-addd the Background URL
                update_post_meta($id, static::META_LINK, $link);
            }
        }
        
        return $result;
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
    
    /**
     * Outputs some extra styles
     *
     * @since 1.0.8
     */
    public function onPrintStyles()
    {
        // Makrs errors a more noticable
        echo '<style type="text/css" media="screen">.describe td.error{background-color:#FFEBE8;border:1px solid #C00;padding:2px 8px !important;}</style>';
    }
    
    /**
     * Handles the 'Download From URL' tab
     *
     * This will close the TB Frame if the photo is 'inserted' (saved), or download
     * the image from an external source if 'submitted'. If the image comes from Flickr,
     * it will also attempt to obtain more details about it through the Flickr API.
     *
     * @since 1.0.8
     * This works different from the WordPress 'From URL' tab, is it will
     * download the image locally and place it directly in the Media Library
     */
    public function onBgmUrl()
    {
        $errors        = false;
        $attachment_id = isset($_POST['attachment_id']) ? $_POST['attachment_id'] : 0;
        
        // Save any changes made, then close the TB Frame if successful
        if (isset($_POST['insert'])) {
            $errors = media_upload_form_handler();
            
            if (!$errors) {
                echo '<html><head><script type="text/javascript">/* <![CDATA[ */ var win=window.dialogArguments||opener||parent||top; win.tb_remove(); /* ]]> */</script></head><body></body></html>';
                die();
            }
        }
        
        // Attempt to download and save the image
        if (isset($_POST['submit']) && current_user_can('upload_files')) {
            check_admin_referer('media-form'); // die() if invalid NONCE
            
            $gallery_id = (int)$_POST['post_id'];
            $image_url  = trim($_POST['url']);
            
            if (!empty($image_url)) {
                $title       = '';
                $description = '';
                
                // Check if it's a Flickr URL
                if (preg_match('#^http[s]?://farm\d{1,3}\.(?:staticflickr|static\.flickr).com\/\d+\/(\d+)_.+\.(?:jpg|png|gif)$#i', $image_url, $matches)) {
                    $flickr_photo_id = $matches[1];

                    // Obtain some more details about the image from Flickr
                    $flickr = new FlickrApi($this->owner);
                    if ($flickr->isValid($info = $flickr->call('photos.getInfo', array('photo_id' => $flickr_photo_id))) && isset($info['photo'])) {
                        $info         = $info['photo'];
                        $title        = $info['title']['_content'];
                        $license_info = $flickr->getLicenseById($info['license']);
                        $description  = sprintf(__('<p>%s</p><p>By: <a href="http://www.flickr.com/photos/%s/%s/">%s</a> (%s)</p>', $this->owner->getName()),
                            $info['description']['_content'],
                            $info['owner']['nsid'],     // User ID
                            $info['id'],                // Photo ID
                            $info['owner']['username'], // Username
                            (!empty($license_info['url'])) ? sprintf('<a href="%s">%s</a>', $license_info['url'], $license_info['name']) : $license_info['name']
                        );
                    }
                    unset($flickr);
                }
                
                $attachment_id = Images::importImage($image_url, $gallery_id, $title, $description);
                
                if (!$attachment_id)
                    $errors = __('Unable to import image at specified URL', $this->owner->getName());
            }            
        }

        // Display the form
        wp_enqueue_style('media'); // Either this, or give callback function a funky 'media_' prefix >_<
        return wp_iframe(array($this, 'onBgmUrlForm'), $errors, $attachment_id);
    }
    
    /**
     * Displays the 'Download from URL' tab (form)
     *
     * @since 1.0.8
     * @param mixed $errors If not `false`, it contains one or more error messages to display to the user
     * @param int $attachment_id The ID of the attached image on successful retrieval from external source, or 0
     */    
    public function onBgmUrlForm($errors, $attachment_id = 0)
    {
        media_upload_header();
        
        $post_id = (isset($_REQUEST['post_id'])) ? (int)$_REQUEST['post_id'] : 0;
        
        $vars = array(
            'nonce'         => wp_nonce_field('media-form', '_wpnonce', false, false), // Same as used by media_upload_form_handler()
            'get_btn'       => get_submit_button(__('Download', $this->owner->getName()), 'button', 'submit', false, array('style'=>'display:inline-block')),
            'save_btn'      => get_submit_button(__('Save all changes', $this->owner->getName()), 'button', 'insert'),
            'post_id'       => $post_id,
            'errors'        => (!is_array($errors)) ? $errors : false,
            'attachment_id' => $attachment_id,
            'attachment'    => ($attachment_id) ? get_media_item($attachment_id, array('toggle' => false, 'show_title' => false, 'send' => false, 'errors' => (is_array($errors) && isset($errors[$attachment_id])) ? $errors[$attachment_id] : null)) : '',
        );
        
        $this->owner->template->display('media_library_bgm_url.html.twig', $vars);
    }
}
