<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Pf4wp\Notification\AdminNotice;

/**
 * The main class for the BackgroundManager
 *
 * It is the controller for all other functionality of BackgroundManager
 *
 * @author Mike Green <myatus@gmail.com>
 * @version 0.0.0.2
 * @package BackgroundManager
 */
class Main extends \Pf4wp\WordpressPlugin
{
    /* Post Types */
    const PT_GALLERY = 'bgm_gallery';
    
    /* Meta Types */
    const MT_CSS = 'bgm_css';
    
    /* Gallery Nonces (to ensure consistency) */
    const NONCE_DELETE_GALLERY  = 'delete-gallery';
    const NONCE_TRASH_GALLERY   = 'trash-gallery';
    const NONCE_RESTORE_GALLERY = 'restore-gallery';
    const NONCE_EDIT_GALLERY    = 'edit-gallery';
    
    private $galleries;     // Instance of Galleries
    private $list;          // Instance to a List\Galleries
    private $in_edit;       // Cached response of inEdit()
    
    protected $default_options = array();
    
    
    /* ----------- Helpers ----------- */

    /**
     * Returns the number of galleries
     *
     * @param bool $active If set to `true` return the active gallery count, otherwise return the trashed gallery count
     * @return int Number of galleries
     */
    public function getGalleryCount($active = true)
    {
        $counts = wp_count_posts(self::PT_GALLERY);
        
        if (!$active)
            return $counts->trash;

        return $counts->publish;
    }
    
    /**
     * Returns whether we are currently in an edit mode
     *
     * @return bool Returns `true` if we are in an edit mode, `false` otherwise
     */
    public function inEdit()
    {
        global $wpdb;
        
        if (!current_user_can('edit_theme_options'))
            return false;
        
        if (isset($this->in_edit))
            return ($this->in_edit);
        
        $edit = (isset($_REQUEST['edit'])) ? trim($_REQUEST['edit']) : '';
        
        $result = false;

        if ($edit == 'new') {
            $result = true;
        } else if ($edit != '') {
            // Check if the Gallery actually exists and isn't in the Trash
            $result = ($wpdb->get_var($wpdb->prepare("SELECT `id` FROM `{$wpdb->posts}` WHERE `post_type` = %s AND `post_status` != 'trash' AND `id` = %d", self::PT_GALLERY, $edit)) == $edit);
        } // else empty, return default (false)
        
        $this->in_edit = $result; // Cache response
        
        return $result;
    }
    
    /* ----------- Events ----------- */
    
    /**
     * Perform additional registerActions()
     *
     * This will replace WordPress' Custom Background with ours
     */
    public function registerActions()
    {
        parent::registerActions();
        
        // Remove the original 'Background' menu and WP's callback
        remove_custom_background(); // Since WP 3.1
        
        // Register additional actions
        add_action('wp_head', array($this, 'onWpHead'));
        add_action('bgm_scheduled_delete', array($this, 'onScheduledDelete'));
        
        // Register post types
        register_post_type(self::PT_GALLERY, array(
            'labels' => array(
                'name' => __('Background Manager Galleries', $this->getName()),
                'singular_name' => __('Background Manager Gallery', $this->getName()),
            ),
            'public' => false,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => false,
        ));
        
        /** @TODO: Attachement support */
        add_theme_support('custom-background');
    }
    
    /**
     * Build the menu
     */
    public function onBuildMenu()
    {
        $mymenu = new \Pf4wp\Menu\SubHeadMenu($this->getName());
        
        // Add settings menu
        $main_menu = $mymenu->addMenu(__('Background', $this->getName()), array($this, 'onSettingsMenu'));
        $main_menu->page_title = $this->getDisplayName();
        $main_menu->large_icon = 'icon-themes';
        
        // Add photo sets (galleries) submenu
        $gallery_menu = $mymenu->addSubmenu(__('Photo Sets', $this->getName()), array($this, 'onGalleriesMenu'));
        $gallery_menu->count = $this->getGalleryCount();
        if (!$this->inEdit())
            $gallery_menu->per_page = 30; // Add a `per page` screen setting
        
        // If there are items in the Trash, display this menu too:
        if ($count = $this->getGalleryCount(false)) {
            $trash_menu = $mymenu->addSubmenu(__('Trash', $this->getName()), array($this, 'onTrashMenu'));
            $trash_menu->count = $count;
            $trash_menu->per_page = 30;
        }

        // Make it appear under WordPress' `Appearance`
        $mymenu->setType(\Pf4wp\Menu\MenuEntry::MT_THEMES);
        
        // Give the 'Home' a different title
        $mymenu->setHomeTitle(__('Settings', $this->getName()));

        // Add an 'Add New Photo Set' link
        if (($this->inEdit() && strtolower(trim($_REQUEST['edit'])) != 'new') ||
            (($active_menu = $mymenu->getActiveMenu()) == false) || 
            $active_menu != $gallery_menu) {
            $add_new_url = esc_url(add_query_arg(
                array(
                    \Pf4wp\Menu\CombinedMenu::SUBMENU_ID => $gallery_menu->getSlug(),
                    'page' => $gallery_menu->getSlug(true),
                    'edit' => 'new',
                )
            ));
            
            // Replace existing main page title with one that contains a link
            $main_menu->page_title = sprintf(
                '%s <a class="add-new-h2" href="%s">%s</a>',
                $main_menu->page_title,
                $add_new_url,
                __('Add New Photo Set', $this->getName())
            );
        }

        return $mymenu;
    }
    
    /**
     * Settings Menu
     */
    public function onSettingsMenu($data, $per_page)
    {
        if (!empty($_POST) && !wp_verify_nonce($_POST['_nonce'], 'onSettingsMenu'))
            wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
            
            
        $test_val = isset($_POST['test']) ? $_POST['test'] : 'Default';
        
        $vars = array(
            'nonce' => wp_nonce_field('onSettingsMenu', '_nonce', true, false),
            'submit_button' => get_submit_button(),
            'test_val' => $test_val,
        );
        
        $this->template->display('settings.html.twig', $vars);
    }
    
    /**
     * Before loading the Galleries Menu, load the list and handle any pending user actions
     *
     * This is also shared with onTrashMenuLoad(), due to its shared code
     *
     * @see onTrashMenuLoad()
     */
    public function onGalleriesMenuLoad() {
        if (!isset($this->list))
            $this->list = new Lists\Galleries($this);
            
        if (!isset($this->galleries))
            $this->galleries = new Galleries($this);
        
        // "Simple" actions
        if (isset($_REQUEST['ids']) && ($action = $this->list->current_action()) !== false) {
            switch (strtolower($action)) {
                case 'restore':
                case 'restore_all':
                    $this->galleries->restoreUserAction(($action == 'restore_all'));
                    break;
                
                case 'trash':
                case 'trash_all':
                    $this->galleries->trashUserAction(($action == 'trash_all'));
                    break;
                    
                case 'delete':
                case 'delete_all':
                    $this->galleries->deleteUserAction(($action == 'delete_all'));
                    break;                    
            }                    
        }
        
        // Save (new) gallery action
        if ($this->inEdit() && isset($_POST['submit']))
            $this->galleries->saveUserAction();
    }
    
    /**
     * Galleries Menu
     *
     * This is also shared with onTrashMenu(), due to its shared code
     *
     * @see onTrashMenu()
     */
    public function onGalleriesMenu($data, $per_page)
    {
        // Basic sanity check
        if (!isset($this->list) || !isset($this->galleries))
            return; 
            
        // Edit action (this is handled here, instead of Load
        if ($this->inEdit() && $this->editGallery())
            return;
            
        $this->list->setPerPage($per_page);
        $this->list->prepare_items();
        
        $vars = array(
            'trash' => $this->list->isTrash(),
            'list'  => $this->list->render(),
        );
        
        $this->template->display('galleries.html.twig', $vars);
    }
    
    /**
     * Before loading the Trash Menu, load the list.
     *
     * @see onGalleriesMenuLoad()
     */
    public function onTrashMenuLoad() {
        $this->list = new Lists\Galleries($this, true); // !!
        
        $this->onGalleriesMenuLoad();
    }
    
    /**
     * Trash Menu
     *
     * @see onGalleriesMenu()
     */
    public function onTrashMenu($data, $per_page)
    {
        $this->onGalleriesMenu($data, $per_page);
    }
    
    /**
     * Called on wp_head, rendering the stylesheet as late as possible.
     */
    public function onWpHead()
    {
        if (is_admin())
            return;
            
        $style = '';
        
        if ($color = get_background_color())
            $style .= sprintf('background-color: #%s;', $color);
        
        printf('<style type="text/css" media="screen">body { %s }</style>'.PHP_EOL, $style);
    }
    
    /**
     * Edit or create a gallery
     *
     * This will render the edit form, in place of the gallery list, unless
     * the user does not have the privileges to edit any theme options.
     *
     * @see onGalleriesMenu()
     */
    public function editGallery()
    {
        if (!current_user_can('edit_theme_options'))
            return false;

        $edit     = strtolower(trim($_REQUEST['edit']));
        $is_new   = ($edit == 'new');
        $gallery  = null;
        
        if (!isset($_POST['submit'])) {
            if (!$is_new) {
                $edit    = intval($edit);
                $gallery = get_post($edit);
                
                // Add custom css meta
                $gallery->meta_css = get_post_meta($edit, self::MT_CSS, true);
            }
        }
        
        $vars = array(
            'nonce'         => wp_nonce_field(self::NONCE_EDIT_GALLERY . $edit, '_nonce', true, false),
            'submit_button' => get_submit_button(($is_new) ? __('Add Photo Set', $this->getName()) : ''),
            'gallery'      => ($gallery) ? $gallery : $_REQUEST,
            'is_new'        => $is_new,
            'edit'          => $edit,
        );
        
        $this->template->display('edit_gallery.html.twig', $vars);
        
        return true;
    }
}