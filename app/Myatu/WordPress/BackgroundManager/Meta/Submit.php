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
 * A core meta box that provides a description field and submit/add button
 *
 * Note: Saving the description is part of \Galleries
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Submit extends PostMetabox
{
    protected $title    = 'Save Photo Set';
    protected $pages    = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context  = 'side';
    protected $priority = 'core';
    
    /** Constructor [Override] */
    public function __construct($owner, $auto_register = true) {
        parent::__construct($owner, false);

        // We sneak our own name instead of the auto-generated one, so WP will apply its own CSS. Saves some CSS code.
        $this->name = 'submitdiv';
        
        if ($auto_register == true)
            $this->register();
    }
    
    /** Renders a trash or delete link */
    protected function deleteLink($id)
    {
        if ($id == 0)
            return '';
            
        $link = '<a href="%s" title="%s" class="submitdelete deletion">%s</a>';

        if (EMPTY_TRASH_DAYS) {
            $action = 'trash';
            $nonce  =  wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_TRASH_GALLERY . $id);
            $title  = __('Move this Photo Set to the Trash', $this->owner->getName());
            $text   = __('Trash Photo Set', $this->owner->getName());
        } else {
            $action = 'delete';
            $nonce  = wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_DELETE_GALLERY . $id);
            $title  = __('Delete this Photo Set permanently', $this->owner->getName());
            $text   = __('Delete Photo Set', $this->owner->getName());
        }
                
        return sprintf($link, esc_url(add_query_arg(array('action' => $action, 'ids' => $id, '_wpnonce' => $nonce), remove_query_arg(array('edit')))), $title, $text);
    }
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param string $id ID of the post or link being edited
     * @param object $data Array object containing $_POST data, if any
     */
    public function onRender($id, $gallery)
    {
        $vars = array(
            'gallery'        => ($gallery) ? $gallery : $_REQUEST,
            'save_btn_title' => ($id != 0) ? __('Save Changes', $this->owner->getName()) : __('Add Photo Set', $this->owner->getName()),
            'delete_action'  => $this->deleteLink($id),
        );
        
        $this->owner->template->display('meta_gallery_submit.html.twig', $vars);    
    }
}