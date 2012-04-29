<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pages;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Images;

use Pf4wp\Notification\AdminNotice;

/**
 * The Galleries and Trash pages
 *
 * Note: The Galleries uses sub-page Sub\EditGallery
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pages
 * @since 1.0.39
 */
class Galleries
{
    protected $owner;

    // Instance to ..\List\Galleries
    protected $list;
    protected $is_trash;

    public function __construct(Main $owner, $is_trash = false)
    {
        $this->owner    = $owner;
        $this->is_trash = $is_trash;
    }

    /**
     * Handles pre-Page Menu actions
     */
    public function onGalleriesMenuLoad($current_screen)
    {
        // Initialize $this->list
        if (!isset($this->list))
            $this->list = new \Myatu\WordPress\BackgroundManager\Lists\Galleries($this->owner, $this->is_trash);

        if ($this->list->isTrash()) {
            if (isset($_POST['empty_trash']))
                $this->owner->galleries->emptyTrashUserAction();
        }

        // Render any requested iframes (and 'die' afterwards)
        if (isset($_REQUEST['iframe'])) {
            switch (strtolower(trim($_REQUEST['iframe']))) {
                case 'images' :
                    Sub\EditGallery::showIframeImages($this->owner);
                    break;

                case 'edit_image' :
                    Sub\EditGallery::showIframeEditImage($this->owner);
                    break;
            }
        }

        // "Simple" actions
        if (isset($_REQUEST['ids']) && ($action = $this->list->current_action()) !== false) {
            switch (strtolower($action)) {
                case 'restore':
                case 'restore_all':
                    $this->owner->galleries->restoreUserAction(($action == 'restore_all'));
                    break;

                case 'trash':
                case 'trash_all':
                    $this->owner->galleries->trashUserAction(($action == 'trash_all'));
                    break;

                case 'delete':
                case 'delete_all':
                    $this->owner->galleries->deleteUserAction(($action == 'delete_all'));
                    break;
            }
        }

        // If we're in edit mode and not on the "Trash" menu, initialize the stuff for the Edit screen
        if ($this->owner->inEdit() && !$this->list->isTrash()) {
            /* Set the current screen to 'bgm_gallery' - a requirement for
             * edit form meta boxes for this post type
             */
            set_current_screen(Main::PT_GALLERY);

            // Override the context help
            $this->owner->getMenu()->getActiveMenu()->context_help = new \Pf4wp\Help\ContextHelp($this->owner, 'edit');

            // Respond to a save edit action (this will not return if the gallery was saved) - @see ..\Galleries
            if (isset($_POST['submit']))
                $this->owner->galleries->saveUserAction();

            // Add thickbox and other javascripts
            list($js_url, $version, $debug) = $this->owner->getResourceUrl();

            add_thickbox();
            wp_enqueue_script($this->owner->getName() . '-gallery-edit', $js_url . 'gallery_edit' . $debug . '.js', array('jquery', $this->owner->getName() . '-functions'), $version);
            wp_localize_script(
                $this->owner->getName() . '-gallery-edit', 'bgmL10n', array(
                    'warn_delete_all_images' => __('You are about to permanently delete the selected images. Are you sure?', $this->owner->getName()),
                    'warn_delete_image'      => __('You are about to permanently delete this image. Are you sure?', $this->owner->getName()),
                    'l10n_print_after'       => 'try{convertEntities(bgmL10n);}catch(e){};'
                )
            );

            // Enqueue editor buttons (since WordPress 3.3)
            wp_enqueue_style('editor-buttons');

            // Guided Help ("Add Images")
            new \Myatu\WordPress\BackgroundManager\Pointers\AddNewStep2($this->owner->getName());

            // Intro to new features in 1.1
            if ($this->owner->options->last_upgrade == '1.1') {
                new \Myatu\WordPress\BackgroundManager\Pointers\Upgrade1dot1new3($this->owner->getName());
            }

            // Set the 'images per page'
            $active_menu                 = $this->owner->getMenu()->getActiveMenu();
            $active_menu->per_page       = 30;
            $active_menu->per_page_label = __('images per page', $this->owner->getName());

            // Set the layout two 1 or 2 column width
            add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

            // Perform last-moment meta box registrations a la WordPress
            do_action('add_meta_boxes', Main::PT_GALLERY, $this->owner->gallery);
            do_action('add_meta_boxes_' . Main::PT_GALLERY, $this->owner->gallery);

            do_action('do_meta_boxes', Main::PT_GALLERY, 'normal', $this->owner->gallery);
            do_action('do_meta_boxes', Main::PT_GALLERY, 'advanced', $this->owner->gallery);
            do_action('do_meta_boxes', Main::PT_GALLERY, 'side', $this->owner->gallery);
        }
    }

    /**
     * Settings Menu
     */
    public function onGalleriesMenu($data, $per_page)
    {
        // Basic sanity check
        if (!isset($this->list) || !isset($this->owner->galleries))
            return;

        // Show the editor instead of the list
        if ($this->owner->inEdit()) {
            Sub\EditGallery::showEditScreen($this->owner, $per_page);
            return;
        }

        $this->list->setPerPage($per_page);
        $this->list->prepare_items();

        $vars = array(
            'trash' => $this->list->isTrash(),
            'list'  => $this->list->render(),
        );

        $this->owner->template->display('galleries.html.twig', $vars);
    }
}
