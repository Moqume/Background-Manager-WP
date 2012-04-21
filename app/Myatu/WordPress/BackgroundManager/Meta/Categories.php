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
 * A meta box for automatically detected categories
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 * @since 1.0.26
 */
class Categories extends Taxonomy
{
    const META_TAX_PREFIX = 'myatu_bgm_override_';
    const DEF_META_TAX    = 'myatu_bgm_override_cats';
    const DEF_TAXONOMY    = 'category';
    
    protected $title   = 'Override by Category';
    protected $pages   = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context = 'side';
    
    private $reg_categories = array(); // Registered taxonomies of 'Category' type
    
    /**
     * Initialize the registered categories variable
     */
    public function __construct($owner, $auto_register = true)
    {
        $this->resetMetaVars();
        
        parent::__construct($owner, $auto_register);
        
        // Search for registered taxonomies, and add them if they match 'categor'(y|ies) in key name
        $reg_taxonomies = get_taxonomies(array('public' => true, 'show_ui' => true), 'objects');
        
        foreach ($reg_taxonomies as $reg_taxonomy_key => $reg_taxonomy) {
            if (stristr($reg_taxonomy_key, 'categor'))
                $this->reg_categories[$reg_taxonomy_key] = $reg_taxonomy;
        }
    }
    
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
     * Set the meta_tax and taxonomy variables according to the taxonomy id
     *
     * @param string $tax_id The taxonomy ID
     */
    private function setMetaVars($tax_id)
    {
        /* For backward compatibility, use 'myatu_bgm_override_cats' for 'category' */
        if ($tax_id == 'category') {
            $this->meta_tax = static::META_TAX_PREFIX . 'cats';
        } else {
            $this->meta_tax = static::META_TAX_PREFIX . $tax_id;
        }
            
        $this->taxonomy = $tax_id;
    }
    
    /**
     * Reset meta_tax and taxonomy variables to defaults
     *
     * This is mainly to provide backward compatibility
     */
    private function resetMetaVars()
    {
        $this->meta_tax = static::DEF_META_TAX;
        $this->taxonomy = static::DEF_TAXONOMY;
    }
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param int $id ID of the gallery
     * @param object $gallery The gallery object, or post data.
     */
    public function onRender($id, $gallery)
    {
        $categories = array();
        
        foreach ($this->reg_categories as $reg_cat_key => $reg_cat) {
            $this->setMetaVars($reg_cat_key);
            
            $selected_cats = get_post_meta($id, $this->meta_tax, true);
            
            ob_start();
            wp_terms_checklist($id, array('selected_cats' => $selected_cats, 'taxonomy' => $this->taxonomy));
            $categories[$reg_cat_key] = array(
                'label'     => $reg_cat->label,
                'checklist' => ob_get_clean(),   // end ob_start()
            );
        }

        $this->resetMetaVars();
        $this->doRender($id, 'meta_gallery_categories.html.twig', array('categories' => $categories));
    }
    
    /**
     * Event called when a gallery is saved
     *
     * @param int $id ID of the gallery being saved
     */
    public function onSave($id)
    {
        $overlay          = (isset($_REQUEST['overlay_cat_override'])) ? $_REQUEST['overlay_cat_override'] : 0;
        $background_color = (isset($_REQUEST['background_cat_color'])) ? ltrim($_REQUEST['background_cat_color'], '#') : '';
        
        foreach ($this->reg_categories as $reg_cat_key => $reg_cat) {
            $this->setMetaVars($reg_cat_key);
            
            $tax = (isset($_REQUEST['tax_input'][$this->taxonomy])) ? $_REQUEST['tax_input'][$this->taxonomy] : array();
            
            // Check 'post_category' instead, if tax_input is empty
            if (empty($tax)) {
                $tax = (isset($_REQUEST['post_category'])) ? $_REQUEST['post_category'] : array();
            }
            
            $this->doSave($id, $tax, $overlay, $background_color);
        }
        
        $this->resetMetaVars();
    }
    
    /**
     * Helper function to obtain the Override ID for a specific item
     *
     * @param string|bool $item The item to override (ie., 'gallery_id', 'overlay_id), `false` if none found
     */
    protected function getOverrideId($item)
    {
        $result = parent::getOverrideId($item);
        
        if (!$result) {
            foreach ($this->reg_categories as $reg_cat_key => $reg_cat) {
                $this->setMetaVars($reg_cat_key);
                
                $result = parent::getOverrideId($item);
                
                if ($result) break;
            }
            
            $this->resetMetaVars();
        }
        
        return $result;
    }    
}