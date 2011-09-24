<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Pf4wp\WordpressPlugin;
use Pf4wp\Menu\SubHeadMenu;

/**
 * The main class for the BackgroundManager
 *
 * It is the controller for all other functionality of BackgroundManager
 *
 * @author Mike Green <myatus@gmail.com>
 * @version 0.0.0.1
 * @package BackgroundManager
 */
class Main extends WordpressPlugin
{
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
        $mymenu = new SubHeadMenu($this->getName());
        
        // Add settings menu
        $menu = $mymenu->addMenu(__('Background', $this->getName()), array($this, 'onSettingsMenu'));
        $menu->page_title = __('Background Manager', $this->getName());
        $menu->large_icon = 'icon-themes';
        
        // Add photo sets (galleries) submenu
        $menu = $mymenu->addSubmenu(__('Photo Sets', $this->getName()), array($this, 'onGalleriesMenu'));
        $menu->per_page = 30;

        // Make it appear in Appearance
        $mymenu->setType(\Pf4wp\Menu\MenuEntry::MT_THEMES);
        
        // Give the 'Home' a different title
        $mymenu->setHomeTitle(__('Settings', $this->getName()));
        
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
     * Galleries Menu
     */
    public function onGalleriesMenu($data, $per_page)
    {
        $galleries_list = new \Myatu\WordPress\BackgroundManager\Lists\Galleries($this, $per_page);
        $galleries_list->prepare_items();
        $galleries_list->views();
        
        ob_start();
        $galleries_list->display();
        $list = ob_get_clean();
        
        $vars = array(
            'list' => $list
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
}