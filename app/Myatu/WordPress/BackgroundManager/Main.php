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
    const DB_PHOTOS    = 'bgm_photos';
    const DB_GALLERIES = 'bgm_galleries';
    
    private $gallery_counts = array(false, false); // Trash, Active - Cached to avoid excessive DB access
    private $in_edit;
    private $list;
    
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
      
        if (($cached = $this->gallery_counts[$active]) !== false)
            return $cached;

        $trashed = ($active) ? 'FALSE' : 'TRUE';
        $db      = $wpdb->prefix . self::DB_GALLERIES;
        
        $this->gallery_counts[$active] = $wpdb->get_var("SELECT COUNT(*) FROM `{$db}` WHERE `trash` = {$trashed}");
        
        return $this->gallery_counts[$active];
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
            $db = $wpdb->prefix . self::DB_GALLERIES;

            $result = ($wpdb->get_var($wpdb->prepare("SELECT `id` FROM `{$db}` WHERE `trash` = FALSE AND `id` = %d", $edit)) == $edit);
        } // else empty, return default (false)
        
        
        $this->in_edit = $result; // Cache response
        
        return $result;
    }
    
    /* ----------- Events ----------- */
    
    /**
     * Initializes the databases on activation
     */
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
        remove_custom_background(); // Since WP 3.1
        
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
     * Before loading the Galleries Menu, load the list.
     */
    public function onGalleriesMenuLoad() {
        $this->list = new Lists\Galleries($this);
        
        // Gallery action handling
        if (isset($_REQUEST['ids']) && isset($_REQUEST['_wpnonce'])) {
            // Check if it is a trash request
            if ((isset($_REQUEST['trash']) && $_REQUEST['trash'] == 1) || ($this->list->current_action() == 'trash_all'))
                $this->onTrashGallery();
        }
    }
    
    /**
     * Galleries Menu
     */
    public function onGalleriesMenu($data, $per_page)
    {
        // Basic sanity check
        if (!isset($this->list))
            return; 
            
        // Edit request
        if ($this->inEdit() && $this->onEditGallery())
            return;
            
        $this->list->setPerPage($per_page);
        $this->list->prepare_items();
        
        $vars = array(
            'list' => $this->list->render(),
        );
        
        $this->template->display('galleries.html.twig', $vars);
    }
    
    /**
     * Before loading the Galleries Menu, load the list.
     */
    public function onTrashMenuLoad() {
        $this->list = new Lists\Galleries($this, true);
    }
    
    /**
     * Trash Menu
     */
    public function onTrashMenu($data, $per_page)
    {
        if (!isset($this->list))
            return;
            
        $this->list->setPerPage($per_page);
        $this->list->prepare_items();
        
        $vars = array(
            'trash' => true,
            'list' => $this->list->render(),
        );
        
        $this->template->display('galleries.html.twig', $vars);
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
    
    /**
     * Send one or more galleries to the Trash
     */
    public function onTrashGallery()
    {
        global $wpdb;

        $origin = remove_query_arg(array('trash','ids','_wpnonce'));
        $ids    = $_REQUEST['ids'];
        
        if (!is_array($ids))
            $ids = explode(',', trim($ids));

        if (current_user_can('edit_theme_options') && !empty($ids)) {
            // Sanitize $ids to integers only
            foreach ($ids as $id_key => $id_val)
                if (!is_int($id_val)) {
                    if (!is_numeric($id_val)) {
                        unset($ids[$id_key]);
                    } else {
                        $ids[$id_key] = intval($id_val);
                    }
                }
            
            // Determine nonce context for single or multiple deletions
            $nonce_context = (count($ids) == 1) ? 'trash-gallery' : 'bulk-galleries';

            if (wp_verify_nonce($_REQUEST['_wpnonce'], $nonce_context)) {
                $db     = $wpdb->prefix . self::DB_GALLERIES;
                $result = $wpdb->query("UPDATE {$db} SET `trash` = TRUE WHERE `id` IN (" . implode(',', $ids) . ")");
            
                if ($result !== false) {
                    $nonce_context = (count($ids) == 1) ? 'restore-gallery' : 'bulk-galleries';
                    
                    $this->addDelayedNotice(
                        sprintf(
                            __('%s moved to the Trash. <a href="%s">Undo</a>', $this->getName()),
                            _n('Item', 'Items', count($ids), $this->getName()),
                            esc_url(add_query_arg(
                                array(
                                    'restore'  => 1,
                                    'ids'      => implode(',', $ids),
                                    '_wpnonce' => wp_create_nonce($nonce_context)
                                ),
                                $origin
                            ))
                        )
                    );
                } else {
                    $this->addDelayedNotice(
                        sprintf(
                            __('There was a problem moving the %s to the Trash.', $this->getName()),
                            _n('item', 'items', count($ids), $this->getName())
                        ), 
                    true);
                }
            }
        }
        
        wp_redirect($origin);
        
        die(); // Awww, didums.
    }
}