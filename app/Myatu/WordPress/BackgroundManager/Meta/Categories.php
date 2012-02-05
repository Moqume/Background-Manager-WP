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
 * A meta box that allows the Image Set to override based on a selected category
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Categories extends Taxonomy
{
    protected $meta_tax         = 'myatu_bgm_override_cats';    // Taxonomy
    protected $taxonomy         = 'category';                   // Taxonomy
    protected $title            = 'Override by Category';
    protected $pages            = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context          = 'side';
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $sel_cats = get_post_meta($id, $this->meta_tax, true);
        
        ob_start();
        wp_terms_checklist($id, array('selected_cats' => $sel_cats));
        $categories = ob_get_clean();

        $this->doRender($id, 'meta_gallery_categories.html.twig', array('categories' => $categories));
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
        $tax     = (isset($_REQUEST['post_category'])) ? $_REQUEST['post_category'] : array();
        $overlay = (isset($_REQUEST['overlay_cat_override'])) ? $_REQUEST['overlay_cat_override'] : 0;
        
        $this->doSave($id, $tax, $overlay);
    }
}