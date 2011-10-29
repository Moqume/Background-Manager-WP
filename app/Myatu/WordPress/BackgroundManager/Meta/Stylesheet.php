<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Meta;

use Pf4wp\Meta\PostMetabox;

/**
 * A meta box that provides the custom CSS meta data
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Stylesheet extends PostMetabox
{
    const MT_CSS = 'myatu_bgm_css';
    
    protected $title    = 'Custom Stylesheet';
    protected $pages    = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context  = 'normal';
    
    /** 
     * Constructor 
     *
     * Adds a filter, to display the custom CSS
     */
    public function __construct($owner, $auto_register = true)
    {
        add_filter('myatu_bgm_custom_styles', array($this, 'onCustomStyles'), 15, 2);
        
        parent::__construct($owner, $auto_register);
    }
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $vars = array('custom_css' => get_post_meta($id, self::MT_CSS, true), 'theme_name' => get_current_theme());
        
        $this->owner->template->display('meta_gallery_css.html.twig', $vars);    
    }
    
    /**
     * Event called when a gallery is saved
     *
     * Note: removal of meta data is handled by WordPress already, so
     * there is no need for an onDelete(), unless we do fancy stuff with
     * meta data.
     *
     * @param int $id ID of the gallery being saved
     */
    public function onSave($id)
    {
        $data = (isset($_REQUEST['custom_css'])) ? $_REQUEST['custom_css'] : '';
        
        $this->setSinglePostMeta($id, self::MT_CSS, $data);
    }
    
    /**
     * Event called when Background Manager is ready to print custom styles
     *
     * @param int $id ID of the gallery
     * @param string $styles Current styles (excluding 'body')
     * @return string 
     */
    public function onCustomStyles($id, $styles)
    {
        $styles .= get_post_meta($id, self::MT_CSS, true);
        
        return $styles;
    }
}