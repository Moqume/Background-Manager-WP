<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pages\Sub;

use Myatu\WordPress\BackgroundManager\Main;

use Pf4wp\Common\Helpers;

/**
 * The Edit page (child/sub of Galleries)
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pages\Sub
 * @since 1.0.39
 */

class EditGallery
{
    /**
     * Static only class, construct is hidden
     */
    protected function __construct(Main $owner) {}

    /**
     * Edit an existing or new gallery
     *
     * This will render the edit form, in place of the gallery list, unless
     * the user does not have the privileges to edit any theme options.
     *
     * @see onGalleriesMenu()
     */
    public static function showEditScreen(Main $owner, $per_page)
    {
        // Get the main meta box output (for Twig)
        ob_start();
        do_meta_boxes(Main::PT_GALLERY, 'normal', $owner->gallery);
        do_meta_boxes(Main::PT_GALLERY, 'advanced', $owner->gallery);
        $meta_boxes_main = ob_get_clean();

        // Get the side meta box output (for Twig)
        ob_start();
        do_meta_boxes(Main::PT_GALLERY, 'side', $owner->gallery);
        $meta_boxes_side = ob_get_clean();

        // Check if we start by displaying the right-side column;
        $screen  = get_current_screen();
        $columns = (int)get_user_option('screen_layout_'.$screen->id);
        if ($columns == 0)
            $columns = 2;

        // Image upload button iframe src (href)
        $image_media_library = add_query_arg(array('post_id' => ($owner->gallery) ? $owner->gallery->ID : '', 'type' => 'image'), admin_url('media-upload.php'));
        $image_media_library = apply_filters('image_upload_iframe_src', $image_media_library); // As used by WordPress

        // Add an "Add Image" media button
        $media_buttons['image']['id']    = 'add_image';
        $media_buttons['image']['url']   = add_query_arg('filter', \Myatu\WordPress\BackgroundManager\Filter\MediaLibrary::FILTER, $image_media_library); // Add filter
        $media_buttons['image']['icon']  = admin_url('images/media-button-image.gif');
        $media_buttons['image']['title'] = __('Add an Image', $owner->getName());

        // Allow additional media buttons to be specified
        $media_buttons = apply_filters(Main::BASE_PUB_PREFIX . 'media_buttons', $media_buttons);

        // Ensure that media buttons have a `TB_iframe` as the last query arg
        foreach ($media_buttons as $media_button_key => $media_button_value) {
            if (isset($media_button_value['url']))
                $media_buttons[$media_button_key]['url'] = add_query_arg('TB_iframe', true, remove_query_arg('TB_iframe', $media_buttons[$media_button_key]['url']));
        }

        // Iframe source for listing the images - @see onIframeImages()
        $images_iframe_src = add_query_arg(array(
            'iframe'    => 'images',
            'edit'      => $owner->gallery->ID,
            'orderby'   => false,
            'order'     => false,
            'pp'        => $per_page,
            'paged'     => false
        ));

        // Iframe source for editing a single image - @see onIframeEditImage()
        $image_edit_src = add_query_arg(array(
            'iframe'    => 'edit_image',
            'edit'      => false,
            'orderby'   => false,
            'order'     => false,
            'post_id'   => $owner->gallery->ID,
            'filter'    => \Myatu\WordPress\BackgroundManager\Filter\MediaLibrary::FILTER // Media Library Filter, see Main\onFilter()
        ));

        $vars = array(
            'is_wp34'           => Helpers::checkWPVersion('3.4', '>='),
            'has_right_sidebar' => ($columns == 2) ? 'has-right-sidebar columns-2' : '',
            'nonce'             => wp_nonce_field(Main::NONCE_EDIT_GALLERY . $owner->gallery->ID, '_nonce', true, false),
            'nonce_meta_order'  => wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false, false),
            'nonce_meta_clsd'   => wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false, false),
            'images_iframe_src' => $images_iframe_src,  // iframe source
            'image_edit_src'    => $image_edit_src,     // iframe source
            'gallery'           => $owner->gallery,
            'post_type'         => Main::PT_GALLERY,
            'meta_boxes_main'   => $meta_boxes_main,
            'meta_boxes_side'   => $meta_boxes_side,
            'media_buttons'     => $media_buttons,
            'is_new'            => $owner->gallery->post_status != 'auto-draft',
            'edit'              => $owner->gallery->ID,
            'images_per_page'   => $per_page,
            'images_count'      => $owner->images->getCount($owner->gallery->ID),
            'images_hash'       => $owner->images->getHash($owner->gallery->ID),
            'img_large_loader'  => $owner->getPluginUrl() . Main::DIR_IMAGES . 'large_loader.gif',
            'image_del_is_perm' => (!EMPTY_TRASH_DAYS || !MEDIA_TRASH) ? true : false,
        );

        $owner->template->display('edit_gallery.html.twig', $vars);
    }

    /** Images Iframe */
    public static function showIframeImages(Main $owner)
    {
        if (!isset($owner->gallery->ID))
            die; // Something didn't go quite right

        // Only if Javascript is disabled will we get here, which adds a image to the gallery directly
        if (!empty($_POST) && isset($_POST['_nonce'])) {
            if (!wp_verify_nonce($_POST['_nonce'], 'image-upload'))
                wp_die(__('You do not have permission to do that [nonce].', $owner->getName()));

            // Check if there's a valid image, and if so, let the Media Library handle the upload
            if (!empty($_FILES) && $_FILES['upload_file']['error'] == 0 && file_is_valid_image($_FILES['upload_file']['tmp_name']))
                media_handle_upload('upload_file', $owner->gallery->ID);
        }

        iframe_header();

        $items_per_page = isset($_GET['pp']) ? $_GET['pp'] : 30;
        $page_num       = isset($_GET['paged']) ? $_GET['paged'] : 1;

        // Grab the total amount of items (images) and figure out how many pages that is
        $total_items = $owner->images->getCount($owner->gallery->ID);
        if (($total_pages = ceil($total_items / $items_per_page)) < 1)
            $total_pages = 1;

        // Get a valid page number
        if ($page_num > $total_pages) {
            $page_num = $total_pages;
        } else if ($page_num < 1) {
            $page_num = 1;
        }

        // Grab the images
        $images = $owner->images->get($owner->gallery->ID,
            array(
                'orderby'     => 'menu_order',
                'order'       => 'asc',
                'numberposts' => $items_per_page,
                'offset'      => ($page_num-1) * $items_per_page,
            )
        );

        // The page links (for non-JS browsers)
        $page_links = paginate_links(array(
            'base'         => add_query_arg('paged', '%#%'),
            'format'       => '',
            'prev_text'    => __('&laquo;'),
            'next_text'    => __('&raquo;'),
            'total'        => $total_pages,
            'current'      => $page_num,
        ));

        $vars = array(
            'images'       => $images,
            'current_page' => $page_num,
            /* For non-JS: */
            'page_links'   => $page_links,
            'nonce'        => wp_nonce_field('image-upload', '_nonce', false, false),
        );

        $owner->template->display('gallery_image.html.twig', $vars);

        iframe_footer();
        die();
    }

    /** Edit Image iframe **/
    public static function showIframeEditImage(Main $owner)
    {
        if (!isset($_GET['id']))
            die; // How did you get here? Hmm!

        $id       = (int)$_GET['id'];
        $post     = get_post($id);
        $vars     = array();
        $did_save = false;

        // Handle save request
        if (isset($_REQUEST['save'])) {
            $errors   = media_upload_form_handler();
            $did_save = true;
        }

        // Queue additional scripts and styles
        wp_enqueue_script('image-edit');
        wp_enqueue_style('media');

        // Send iframe header
        iframe_header();

        if ($id == 0 || $post == false || $post->post_type != 'attachment' || $post->post_status == 'trash') {
            // Invalid ID or item was deleted
            $vars = array('deleted' => true);
        } else {
            $vars = array(
                'did_save'   => $did_save,
                'has_error'  => isset($errors[$id]),
                'nonce'      => wp_nonce_field('media-form', '_wpnonce', false, false), // Same as used by media_upload_form_handler()
                'media_item' => get_media_item($id, array('toggle'=>false, 'show_title'=>false, 'send'=>false, 'delete'=>false, 'errors'=>(isset($errors[$id]) ? $errors[$id] : null))),
                'submit'     => get_submit_button(__( 'Save all changes'), 'button', 'save', false),
            );
        }

        // Render template
        $owner->template->display('gallery_edit_image.html.twig', $vars);

        // Send iframe footer and then 'die'.
        iframe_footer();
        die();
    }
}
