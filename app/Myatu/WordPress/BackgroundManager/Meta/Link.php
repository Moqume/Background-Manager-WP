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
 * A meta box that provides the option to override the link in the icon
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Link extends PostMetabox implements \Pf4wp\Dynamic\DynamicInterface
{
    const MT_LINK = 'myatu_bgm_image_link';
    
    protected $title    = 'Background Image Link';
    protected $pages    = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context  = 'normal';
    
    /** 
     * Constructor 
     *
     * Adds a filter, to override the image link
     */
    public function __construct($owner, $auto_register = true)
    {
        add_filter('myatu_bgm_image_link', array($this, 'onImageLink'), 15, 2);
        
        parent::__construct($owner, $auto_register);
    }
    
    /**
     * Info for dynamic loading
     */
    public static function info()
    {
        return array(
            'name'   => '', // Not used
            'desc'   => '', // Not used
            'active' => true,
        );
    }    
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $vars = array('image_link' => get_post_meta($id, self::MT_LINK, true));
        
        $this->owner->template->display('meta_gallery_link.html.twig', $vars);    
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
        $data = (isset($_REQUEST['image_link'])) ? trim($_REQUEST['image_link']) : '';
        
        $this->setSinglePostMeta($id, self::MT_LINK, $data);
    }
    
    /**
     * Event called when Background Manager needs the background image link
     *
     * @param int $id ID of the active Image Set (Gallery)
     * @param string $image_link Current image link
     * @return string 
     */
    public function onImageLink($id, $image_link)
    {
        $m_link = get_post_meta($id, self::MT_LINK, true);
        
        if (!empty($m_link)) {
            // We have a link, perform a shortcode check on it first
            $m_link = do_shortcode($m_link);
            
            // Replace the image link provided by the filter
            $image_link = $m_link;
        }
        
        return $m_link;
    }
}