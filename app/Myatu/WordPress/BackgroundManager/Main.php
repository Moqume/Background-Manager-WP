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
        
        // Remove the 'Background' menu and WP's custom background callback
        remove_custom_background();
        
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
        $menu = $mymenu->addMenu(__('Background Manager', $this->getName()), array($this, 'onSettingsMenu'));
        $menu->large_icon = 'icon-themes';
        
        // Add galley submenu
        $menu = $mymenu->addSubmenu(__('Galleries', $this->getName()), array($this, 'onGalleriesMenu'));

        // Make it appear in `Appearance`
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
        $vars = array(
            'page_title' => 'Test'
        );
        
        $this->template->display('settings.html.twig', $vars);
    }
    
    /**
     * Galleries Menu
     */
    public function onGalleriesMenu($data, $per_page)
    {
        $this->template->display('galleries.html.twig');
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