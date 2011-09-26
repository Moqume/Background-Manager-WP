<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

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
    const DB_PHOTOS    = 'bgm_photos';
    const DB_GALLERIES = 'bgm_galleries';
    
    private $in_edit;
    
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
        global $wpdb;

        $trashed = ($active) ? 'FALSE' : 'TRUE';
        $db      = $wpdb->prefix . self::DB_GALLERIES;
        
        return $wpdb->get_var("SELECT COUNT(*) FROM `{$db}` WHERE `trash` = {$trashed}");
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
        } else if (!empty($edit)) {
            $db = $wpdb->prefix . self::DB_GALLERIES;
        
            $result = ($wpdb->get_var($wpdb->prepare("SELECT `id` FROM `{$db}` WHERE `trash` = FALSE AND `id` = %d", $edit)) == $edit);
        } // else empty, return default (false)
        
        $this->in_edit = $result; // Cache response
        
        return $result;
    }
    
    /* ----------- Events ----------- */
    
    public function onActivation()
    {
        if (!Database\Galleries::init() || !Database\Photos::init())
            $this->addDelayedNotice(sprintf(
                'There was a problem initializing the database for <strong>%s</strong> during activation.', 
                $this->getDisplayName()
            ), true);
    }
    
    /**
     * Perform additional registerActions()
     *
     * This will replace WordPress' Custom Background with ours
     */
    public function registerActions()
    {
        parent::registerActions();
        
        // Remove the original 'Background' menu and WP's callback
        remove_custom_background(); // WP 3.1
        
        // And add our own handlers
        add_action('wp_head', array($this, 'onWpHead'));
        
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
        
        // If items are in trash, display this menu too:
        if (!empty($galleries)) {
            $trash_menu = $mymenu->addSubmenu(__('Trash', $this->getName()), array($this, 'onTrashMenu'));
            $trash_menu->count = $this->getGalleryCount(false);
            $trash_menu->per_page = 30;
        }

        // Make it appear in `Appearance`
        $mymenu->setType(\Pf4wp\Menu\MenuEntry::MT_THEMES);
        
        // Give the 'Home' a different title
        $mymenu->setHomeTitle(__('Settings', $this->getName()));

        if ((($active_menu = $mymenu->getActiveMenu()) == false && !$this->inEdit()) || $active_menu != $gallery_menu) {
            // Give any menu other than the gallery submenu an 'Add New Photo Set' link
            $url = esc_url(add_query_arg(
                array(
                    \Pf4wp\Menu\CombinedMenu::SUBMENU_ID => $gallery_menu->getSlug(),
                    'page' => $gallery_menu->getSlug(true),
                    'edit' => 'new',
                )
            ));
                
            $main_menu->page_title = sprintf(
                '%s <a class="add-new-h2" href="%s">%s</a>',
                $main_menu->page_title,
                $url,
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
     * Return the available gallery list columns
     *
     * Called before onGalleriesMenu via a WordPress filter. It's due to this
     * filter that we have a special callback, rather than just a setting.
     *
     * @return array Associative array of column names => column titles
     */
    public function onGalleriesMenuColumns($data) {
        if ($this->inEdit())
            return $data;
            
        $galleries_list = new Lists\Galleries($this);

        return array_merge($data, $galleries_list->get_columns());
    }
    
    /**
     * Galleries Menu
     */
    public function onGalleriesMenu($data, $per_page)
    {
        if ($this->inEdit() && $this->onEditGallery())
            return;
            
        $galleries_list = new Lists\Galleries($this, $per_page);
        $galleries_list->prepare_items();
        
        $vars = array(
            'list' => $galleries_list->render(),
        );
        
        $this->template->display('galleries.html.twig', $vars);
    }
    
    /**
     * Trash Menu
     */
    public function onTrashMenu($data, $per_page)
    {
        echo 'Nothing here yet. Trashed items appear here, with the option to restore or permanently delete.';
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
     */
    public function onEditGallery()
    {
        if (!current_user_can('edit_theme_options'))
            return false;

        $edit = trim($_REQUEST['edit']);
        
        echo "OK: ". $edit;
        return true;
    }
    
}