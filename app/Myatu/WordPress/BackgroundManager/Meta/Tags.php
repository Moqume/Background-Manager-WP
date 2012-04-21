<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Meta;

use Pf4wp\Meta\PostMetabox;

/**
 * A meta box allowing the Image Set to be displayed if the post contains certain tags
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 * @since 1.0.14
 */
class Tags extends Taxonomy
{
    protected $meta_tax         = 'myatu_bgm_override_tags';        // Taxonomy
    protected $taxonomy         = 'post_tag';                       // Taxonomy
    protected $title            = 'Override by Tag';
    protected $pages            = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context          = 'side';
    
    /**
     * Mark as active dynamic class
     *
     * @return bool
     */
    public static function isActive()
    {
        return true;
    }    
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $tags = get_post_meta($id, $this->meta_tax, true);
        
        if (is_array($tags) && !empty($tags)) {
            $tags = implode(',', $tags);
        } else {
            $tags = '';
        }
        
        $this->doRender($id, 'meta_gallery_tags.html.twig', array('tags' => $tags));
    }
    
    /**
     * Event called when a gallery is saved
     *
     * @param int $id ID of the gallery being saved
     */
    public function onSave($id)
    {
        $tax              = (isset($_REQUEST['tax_input']) && isset($_REQUEST['tax_input']['post_tag'])) ? $_REQUEST['tax_input']['post_tag'] : array();
        $overlay          = (isset($_REQUEST['overlay_tag_override'])) ? $_REQUEST['overlay_tag_override'] : 0;
        $background_color = (isset($_REQUEST['background_tag_color'])) ? ltrim($_REQUEST['background_tag_color'], '#') : '';
        
        if (!is_array($tax))
            $tax = explode(',', $tax);
        
        $this->doSave($id, $tax, $overlay, $background_color);
    }
}