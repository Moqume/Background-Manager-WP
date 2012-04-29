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
class Submit extends PostMetabox implements \Pf4wp\Dynamic\DynamicInterface
{
    protected $title    = 'Save Image Set';
    protected $pages    = array(\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY);
    protected $context  = 'side';
    protected $priority = 'core';

    /** Constructor [Override] */
    public function __construct($owner, $auto_register = true) {
        parent::__construct($owner, false);

        // We sneak our own name instead of the auto-generated one, so WP will apply its own CSS.
        $this->name = 'submitdiv';

        if ($auto_register == true)
            $this->register();
    }

    /**
     * Info for dynamic loading
     */
    public static function info()
    {
        $active = false;

        if (current_user_can('edit_theme_options')) {
            $active = true;
        }

        return array(
            'name'   => '', // Not used
            'desc'   => '', // Not used
            'active' => $active,
        );
    }

    /** Returns an array containing details for the Trash/Delete link */
    protected function deleteLink($id)
    {
        if ($id == 0 || get_post_status($id) == 'auto-draft')
            return;

        if (EMPTY_TRASH_DAYS) {
            $action = 'trash';
            $nonce  =  wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_TRASH_GALLERY . $id);
            $title  = __('Move this Image Set to the Trash', $this->owner->getName());
            $text   = __('Trash Image Set', $this->owner->getName());
        } else {
            $action = 'delete';
            $nonce  = wp_create_nonce(\Myatu\WordPress\BackgroundManager\Main::NONCE_DELETE_GALLERY . $id);
            $title  = __('Delete this Image Set permanently', $this->owner->getName());
            $text   = __('Delete Image Set', $this->owner->getName());
        }

        return array(
            'url'   => esc_url(add_query_arg(array('action' => $action, 'ids' => $id, '_wpnonce' => $nonce, 'edit'=>false))),
            'title' => $title,
            'text'  => $text,
        );
    }

    /**
     * Event called when ready to render the Metabox contents
     *
     * @param string $id ID of the post or link being edited
     * @param object $data Array object containing $_POST data, if any
     */
    public function onRender($id, $gallery)
    {
        $is_new = (get_post_status($id) == 'auto-draft');

        $vars = array(
            'gallery'            => ($gallery) ? $gallery : $_REQUEST,
            'gallery_id'         => ($is_new) ? false : $id,
            'save_btn_title'     => (!$is_new) ? __('Save Changes', $this->owner->getName()) : __('Add Image Set', $this->owner->getName()),
            'show_delete_action' => (!$is_new),
            'delete_action'      => $this->deleteLink($id),
        );

        $this->owner->template->display('meta_gallery_submit.html.twig', $vars);
    }
}
