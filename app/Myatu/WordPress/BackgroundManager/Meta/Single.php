<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Meta;

use Pf4wp\Meta\PostMetabox;
use Myatu\WordPress\BackgroundManager\Main;

/**
 * A meta box that provides the ability to override the active gallery on a post or page
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Meta
 */
class Single extends PostMetabox implements \Pf4wp\Dynamic\DynamicInterface
{
    /** Meta Filters */
    const
        MT_ACTIVE_GALLERY = 'myatu_bgm_active_gallery',
        MT_ACTIVE_OVERLAY = 'myatu_bgm_active_overlay',
        MT_BG_COLOR       = 'myatu_bgm_background_color';

    protected $title    = 'Background';
    protected $context  = 'advanced';
    protected $pages    = array('post', 'page');

    /**
     * Constructor
     *
     * Adds a filter, to display the custom CSS
     */
    public function __construct($owner, $auto_register = true)
    {
        add_filter('myatu_bgm_active_gallery',   array($this, 'onActiveGallery'), 25, 1);
        add_filter('myatu_bgm_active_overlay',   array($this, 'onActiveOverlay'), 25, 1);
        add_filter('myatu_bgm_background_color', array($this, 'onBackgroundColor'), 25, 1);

        $active = true;

        // If we're on the admin side, check if there's sufficient rights to override the posts/pages
        if (is_admin()) {
            switch ($owner->options->single_post_override) {
                case 'admin' :
                    if (!current_user_can('edit_theme_options'))
                        $active = false;
                    break;

                case 'editor' :
                    if (!current_user_can('edit_private_posts'))
                        $active = false;
                    break;

                case 'author' :
                    if (!current_user_can('publish_posts'))
                        $active = false;
                    break;

                case 'contributor' :
                    if (!current_user_can('edit_posts'))
                        $active = false;
                    break;

                default :
                    $active = false; // Failsafe
                    break;
            }
        }

        if ($active) {
            // Include Custom Post Types
            $this->pages = array_merge($this->pages, get_post_types(array('_builtin' => false, 'public' => true)));

            // Exclude our own Custom Post Type
            unset($this->pages[\Myatu\WordPress\BackgroundManager\Main::PT_GALLERY]);

            parent::__construct($owner, $auto_register);
        }
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
     * @param int $id ID of the post or page
     * @param object $post The gallery object, or post data.
     */
    public function onRender($id, $post)
    {
        wp_enqueue_script('farbtastic');
        wp_enqueue_style('farbtastic');

        $active_gallery   = get_post_meta($id, self::MT_ACTIVE_GALLERY, true);
        $active_overlay   = get_post_meta($id, self::MT_ACTIVE_OVERLAY, true);
        $background_color = get_post_meta($id, self::MT_BG_COLOR, true);

        // Generate a list of galleries, including a default and one to de-activate the background
        $galleries = array_merge(array(
            array(
            'id' => -1,
            'name' => __('-- None (deactivated) --', $this->owner->getName()),
            'selected' => ($active_gallery == -1),
            ),
            array(
                'id' => 0,
                'name' => __('Default Image Set', $this->owner->getName()),
                'selected' => ($active_gallery == false),
            )
        ), $this->owner->getSettingGalleries($active_gallery));

        // List of overlays
        $overlays = array_merge(array(
            array(
                'value'    => -1,
                'desc'     => __('-- None (deactivated) --', $this->owner->getName()),
                'selected' => ($active_overlay == -1),
            ),
            array(
                'value'    => 0,
                'desc'     => __('Default Overlay', $this->owner->getName()),
                'selected' => ($active_overlay == false),
            )
        ), $this->owner->getSettingOverlays($active_overlay));

        $vars = array(
            'galleries'        => $galleries,
            'overlays'         => $overlays,
            'background_color' => $background_color,
        );

        $this->owner->template->display('meta_single.html.twig', $vars);
    }

    /**
     * Event called when a post or page
     *
     * @param int $id ID of the post/page being saved
     */
    public function onSave($id)
    {
        $active_gallery   = (isset($_REQUEST['active_gallery'])) ? $_REQUEST['active_gallery'] : 0;
        $active_overlay   = (isset($_REQUEST['active_overlay'])) ? $_REQUEST['active_overlay'] : 0;
        $background_color = (isset($_REQUEST['background_color'])) ? ltrim($_REQUEST['background_color'], '#') : '';

        // Sanity check for color
        if (!preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $background_color))
            $background_color = '';

        $this->setSinglePostMeta($id, self::MT_ACTIVE_GALLERY, $active_gallery);
        $this->setSinglePostMeta($id, self::MT_ACTIVE_OVERLAY, $active_overlay);
        $this->setSinglePostMeta($id, self::MT_BG_COLOR, $background_color);
    }

    /**
     * Either returns the original ID or an overriden ID from the meta for the post
     *
     * @param string $meta The meta to retrieve the data from (ie., MT_ACTIVE_GALLERY)
     * @param mixed $orig_data The original data
     * @return mixed The (overriden) data
     */
    protected function getOverrideID($meta, $orig_data)
    {
        global $post, $wp_query;

        $id = false;

        // Is this page being used as the post_as_page?
        if ($wp_query->is_posts_page)
            $id = (isset($wp_query->query_vars['page_id']) && $wp_query->query_vars['page_id']) ? $wp_query->query_vars['page_id'] : $wp_query->queried_object_id;

        // Otherwise, if it's just a regular page/post or the home
        if (!$id && is_singular())
            $id = $post->ID;

        // Still nothing, then don't bother
        if (!$id)
            return $orig_data;

        $post_specific_data = get_post_meta($id, $meta, true);

        if ($post_specific_data == -1) {
            return 0; // Disable
        } else if (!$post_specific_data) {
            return $orig_data; // Default
        } else {
            return $post_specific_data; // Override
        }
    }

    /**
     * Event called when the Background Manager needs to know the active gallery
     *
     * @param int $id ID of the active gallery
     * @return int
     */
    public function onActiveGallery($id)
    {
        return $this->getOverrideID(self::MT_ACTIVE_GALLERY, $id);
    }

    /**
     * Event called when the Background Manager needs to know the active overlay
     *
     * @param string $overlay The active overlay
     * @return string
     */
    public function onActiveOverlay($overlay)
    {
        return $this->getOverrideID(self::MT_ACTIVE_OVERLAY, $overlay);
    }

    /**
     * Event called when the Background Manager needs the background color
     *
     * @param string $color The current color
     * @return string
     */
    public function onBackgroundColor($color)
    {
        return $this->getOverrideID(self::MT_BG_COLOR, $color);
    }
}
