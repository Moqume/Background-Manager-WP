<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Pf4wp\Notification\AdminNotice;
use Pf4wp\Common\Helpers;
use Pf4wp\Common\Cookies;
use Pf4wp\Help\ContextHelp;

/**
 * The main class for the BackgroundManager
 *
 * It is the controller for all other functionality of BackgroundManager
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 */
class Main extends \Pf4wp\WordpressPlugin
{
    /* Base public prefix, used for exposing variables to JS, filters, etc.  */
    const BASE_PUB_PREFIX = 'myatu_bgm_';

    /* Post Types */
    const PT_GALLERY = 'myatu_bgm_gallery';

    /* Gallery Nonces (to ensure consistency) */
    const NONCE_DELETE_GALLERY  = 'delete-gallery';
    const NONCE_TRASH_GALLERY   = 'trash-gallery';
    const NONCE_RESTORE_GALLERY = 'restore-gallery';
    const NONCE_EDIT_GALLERY    = 'edit-gallery';

    /* Change Frequency Types */
    const CF_LOAD    = 'load';
    const CF_SESSION = 'session';
    const CF_CUSTOM  = 'custom';

    /* Background Sizes */
    const BS_FULL = 'full';
    const BS_ASIS = 'as-is';

    /* Background Scroll Types */
    const BST_FIXED  = 'fixed';
    const BST_SCROLL = 'scroll';

    /* Directory/URL defines */
    const DIR_IMAGES    = 'resources/images/';
    const DIR_OVERLAYS  = 'resources/images/overlays/';
    const DIR_IMPORTERS = 'app/Myatu/WordPress/BackgroundManager/Importers/';
    const DIR_META      = 'app/Myatu/WordPress/BackgroundManager/Meta/';

    /* Name of the WP Customize Manager class */
    const WP_CUSTOMIZE_MANAGER_CLASS = '\WP_Customize_Manager';

    /** Instance containing current gallery being edited (if any) - see @inEdit() */
    public $gallery = null;

    /** The link to edit Galleries - @see onBuildMenu() */
    private $edit_gallery_link = '';

    /** The link to the Import menu - @see onBuildMenu() (Importer access this) */
    public $import_menu_link  = '';

    /** Instance of Images - @see onAdminInit() */
    public $images;

    /** Instance of Galleries - @see onAdminInit() */
    public $galleries;

    /** Instance of Customizer - @see onRegisterActions() */
    public $customizer;

    /** Non-persistent Cache */
    private $np_cache = array();

    /** The default options */
    protected $default_options = array(
        'change_freq'            => 'load',      // static::CF_LOAD
        'change_freq_custom'     => 10,
        'image_selection'        => 'random',    // since 1.0.38
        'background_size'        => 'as-is',     // static::BS_ASIS
        'background_scroll'      => 'scroll',    // static::BST_SCROLL
        'background_position'    => 'top-left',
        'background_repeat'      => 'repeat',
        'background_opacity'     => 100,
        'overlay_opacity'        => 100,
        'background_transition'  => 'crossfade',
        'transition_speed'       => 600,
        'display_on_front_page'  => true,
        'display_on_single_post' => true,
        'display_on_single_page' => true,
        'display_on_archive'     => true,
        'display_on_search'      => true,
        'display_on_error'       => true,
        'full_screen_center'     => true,
        'info_tab_location'      => 'bottom-left',
        'info_tab_thumb'         => true,
        'info_tab_desc'          => true,
        'pin_it_btn_location'    => 'bottom-left', // Since 1.0.20
        'single_post_override'   => 'admin',       // Since 1.0.39
        'initial_ease_in'        => true,          // Since 1.0.44
        'bg_click_new_window'    => true,          // Since 1.0.47
        'bg_track_clicks_category' => 'Background Manager', // Since 1.0.49
    );

    /** The options can be filtered (prefixed by BASE_PUB_PREFIX in `apply_filters`) - @see getFilteredOptions */
    protected $filtered_options = array(
        'active_gallery',
        'background_opacity',
        'image_selection',
        'change_freq',
        'change_freq_custom',
        'active_overlay',
        'overlay_opacity',
        'background_size',
        'background_position',
        'background_repeat',
        'background_scroll',
        'background_stretch_vertical',
        'background_stretch_horizontal',
        'background_transition',
        'transition_speed',
        'info_tab',
        'info_tab_location',
        'info_tab_thumb',
        'info_tab_link',
        'info_tab_desc',
        'pin_it_btn',
        'pin_it_btn_location',
        'full_screen_center',
        'full_screen_adjust',
        'initial_ease_in',
        'image_remember_last',
    );

    /* Enable public-side Ajax - @see onAjaxRequest() */
    public $public_ajax = true;


    /* ----------- Helpers ----------- */

    /**
     * Helper to return possible background positions, repeats, corners, transitions options and roles
     *
     * @param string $opt The option to return
     * @param bool $withLabel Whether to include a label
     */
    public function getBgOptions($opt, $withLabel = false)
    {
        // Possible background positions
        $bg_positions = array(
            'top-left'      => __('Top Left', $this->getName()),
            'top-center'    => __('Top Center', $this->getName()),
            'top-right'     => __('Top Right', $this->getName()),
            'center-left'   => __('Center Left', $this->getName()),
            'center-center' => __('Center', $this->getName()),
            'center-right'  => __('Center Right', $this->getName()),
            'bottom-left'   => __('Bottom Left', $this->getName()),
            'bottom-center' => __('Bottom Center', $this->getName()),
            'bottom-right'  => __('Bottom Right', $this->getName()),
        );

        // Possible background tiling options
        $bg_repeats = array(
            'repeat'    => __('Tile horizontal and vertical', $this->getName()),
            'repeat-x'  => __('Tile horizontal', $this->getName()),
            'repeat-y'  => __('Tile vertical', $this->getName()),
            'no-repeat' => __('No Tiling', $this->getName()),
        );

        // Possible corner locations
        $corner_locations = array(
            'top-left'      => __('Top Left', $this->getName()),
            'top-right'     => __('Top Right', $this->getName()),
            'bottom-left'   => __('Bottom Left', $this->getName()),
            'bottom-right'  => __('Bottom Right', $this->getName()),
        );

        // Possible transition options
        $bg_transitions = array(
            'none'       => __('-- None (deactivated) --', $this->getName()),
            'random'     => __('Random', $this->getName()),
            'slidedown'  => __('Slide Downward', $this->getName()),
            'slideup'    => __('Slide Upward', $this->getName()),
            'slideleft'  => __('Slide to Left', $this->getName()),
            'slideright' => __('Slide to Right', $this->getName()),
            'coverdown'  => __('Cover Downward', $this->getName()),
            'coverup'    => __('Cover Upward', $this->getName()),
            'coverleft'  => __('Cover to Left', $this->getName()),
            'coverright' => __('Cover to Right', $this->getName()),
            'crossfade'  => __('Crossfade', $this->getName()),
            'zoom'       => __('Crossfade + Zoom', $this->getName()),
            'bars'       => __('Bars', $this->getName()),               // Flux
            'zip'        => __('Zip', $this->getName()),                // Flux
            'blinds'     => __('Blinds', $this->getName()),             // Flux
            'swipe'      => __('Swipe', $this->getName()),              // Flux
            'blocks'     => __('Random Blocks', $this->getName()),      // Flux
            'blocks2'    => __('Sequential Blocks', $this->getName()),  // Flux
            'concentric' => __('Concentric', $this->getName()),         // Flux
            'warp'       => __('Warp', $this->getName()),               // Flux
        );

        $roles = array(
            'admin'       => __('Administrator', $this->getName()),
            'editor'      => __('Editor', $this->getName()),
            'author'      => __('Author', $this->getName()),
            'contributor' => __('Contributor', $this->getName()),
        );

        $result = array();

        switch ($opt) {
            case 'position'   : $result = $bg_positions;     break;
            case 'repeat'     : $result = $bg_repeats;       break;
            case 'corner'     : $result = $corner_locations; break;
            case 'transition' : $result = $bg_transitions;   break;
            case 'role'       : $result = $roles;            break;
        }

        // Return the keys as values if we don't need the labels
        if (!$withLabel)
            $result = array_keys($result);

        return $result;
    }

    /**
     * Helper function to get the CSS location for an element placed in a corner
     *
     * @param string $location Location (ie., 'top-left'),
     * @param int $hspacer Horizontal spacer
     * @param int $vspacer Vertical spacer
     * @return string
     */
    private function getCornerStyle($location, $hspacer, $vspacer)
    {
        $style = '';

        switch ($location) {
            case 'top-left'     : $style = sprintf('left: %dpx !important; top: %dpx !important;', $hspacer, $vspacer); break;
            case 'top-right'    : $style = sprintf('right: %dpx !important; top: %dpx !important;', $hspacer, $vspacer); break;
            case 'bottom-left'  : $style = sprintf('left: %dpx !important; bottom: %dpx !important;', $hspacer, $vspacer); break;
            case 'bottom-right' : $style = sprintf('right: %dpx !important; bottom: %dpx !important;', $hspacer, $vspacer); break;
        }

        return $style;
    }

    /**
     * Helper function that returns the filtered results of options
     *
     * @param string $option Option to return (if none specified, all filtered settings are returned as an array)
     * @return mixed
     */
    public function getFilteredOptions($option = null)
    {
        // The background color is stored differentlly
        if (!isset($this->np_cache['filtered_background_color']))
            $this->np_cache['filtered_background_color'] = apply_filters(static::BASE_PUB_PREFIX . 'background_color', get_background_color());

        $background_color = $this->np_cache['filtered_background_color'];

        if (is_null($option)) {
            $results = $this->options->filtered($this->filtered_options, static::BASE_PUB_PREFIX);
            $results['background_color'] = $background_color; // Special case

            return $results;
        } else {
            if ($option == 'background_color') {
                $result = $background_color; // Special case
            } else {
                $result = $this->options->filtered($option, static::BASE_PUB_PREFIX);
            }

            return $result;
        }
    }

    /**
     * Returns the number of galleries
     *
     * @param bool $active If set to `true` return the active gallery count, otherwise return the trashed gallery count
     * @return int Number of galleries
     */
    public function getGalleryCount($active = true)
    {
        $counts = wp_count_posts(self::PT_GALLERY);

        if (!$active)
            return $counts->trash;

        return $counts->publish;
    }

    /**
     * Returns whether we are currently in an edit mode
     *
     * This will also provide a valid $this->gallery if it returns `true`
     *
     * @return bool Returns `true` if we are in an edit mode, `false` otherwise
     */
    public function inEdit()
    {
        global $wpdb, $post;

        if (!current_user_can('edit_theme_options'))
            return false;

        if (isset($this->np_cache['in_edit']))
            return ($this->np_cache['in_edit']);

        $edit = (isset($_REQUEST['edit'])) ? trim($_REQUEST['edit']) : '';

        $result = false;

        if ($edit == 'new') {
            // Generate a temporary 'auto draft'
            $result = get_default_post_to_edit(self::PT_GALLERY, true);

            if ($result !== false) {
                if (is_null($this->gallery))
                    $this->gallery = $result;

                // Set the 'post' global
                $post = $this->gallery;

                $result = true;
            }
        } else if ($edit != '') {
            // Check if the Gallery actually exists and isn't in the Trash
            $result = ($wpdb->get_var($wpdb->prepare("SELECT `id` FROM `{$wpdb->posts}` WHERE `post_type` = %s AND `post_status` != 'trash' AND `id` = %d", self::PT_GALLERY, $edit)) == $edit);

            // Pre-set $this->gallery with the actual post, so it can be used for other things too
            if ($result) {
                if (is_null($this->gallery))
                    $this->gallery = get_post($edit);

                $is_new = ($this->gallery->post_status == 'auto-draft');

                if ($is_new)
                    $this->gallery->post_title = '';

                // Set the 'post' global
                $post = $this->gallery;
            }
        } // else empty, return default (false)

        $this->np_cache['in_edit'] = $result; // Cache response (non-persistent)

        return $result;
    }

    /**
     * Helper to obtain an image based on user preferences
     *
     * This will return either a random image, or one in sequential order (ascending or descening)
     *
     * @param string $previous_image The URL of the previous image, if any (to avoid duplicates)
     * @param id $active_gallery_id Active gallery, or `false` if to be determined automatically (default)
     * @param string $size The size of the image to return (original size by default)
     * @param string $active_image_selection The selection method for the image, or `false` to determine automatically (default)
     * @return array
     */
    public function getImage($previous_image = '', $active_gallery_id = false, $size = false, $active_image_selection = false)
    {
        $image_id        = 0;
        $image_url       = false;
        $results         = array();
        $change_freq     = $this->getFilteredOptions('change_freq');
        $remember_last   = $this->getFilteredOptions('image_remember_last');
        $image_selection = ($active_image_selection === false) ? $this->getFilteredOptions('image_selection') : $active_image_selection;
        $gallery_id      = ($active_gallery_id === false) ? $this->getFilteredOptions('active_gallery') : $active_gallery_id;
        $cache_id        = 'get_image_' . md5($image_selection . $gallery_id . $change_freq);
        $cookie_id       = static::BASE_PUB_PREFIX . 'bg_id_' . $gallery_id; // Cookie ID for stored background image ID

        // Default results
        $defaults = array(
            'id'      => 0,
            'url'     => '',
            'alt'     => '',
            'desc'    => '',
            'caption' => '',
            'link'    => '',
            'thumb'   => '',
            'bg_link' => '',
        );

        // If we've already been through the motions, return the cached results
        if (isset($this->np_cache[$cache_id]))
            return $this->np_cache[$cache_id];

        if ($this->getGallery($gallery_id) != false) {
            $prev_id = $this->images->URLtoID($previous_image);

            // Use the last shown image if we're not trying to grab the next image
            if ($change_freq == static::CF_CUSTOM && $remember_last && empty($previous_image)) {
                $image_id  = Cookies::get($cookie_id, $image_id);
                $image_url = wp_get_attachment_image_src($image_id, $size);

                // We only need the URL if we have a valid image
                if ($image_url)
                    $image_url = $image_url[0];
            }

            // If there's no last shown image...
            if (!$image_url) {
                $image_id = $this->images->getImageId($gallery_id, $image_selection, $prev_id);

                if ($change_freq == static::CF_SESSION) {
                    // Grab the cookie if it exists, otherwise use the $image_id we've set earlier
                    $image_id  = Cookies::get($cookie_id, $image_id);
                    $image_url = wp_get_attachment_image_src($image_id, $size);

                    if ($image_url) {
                        // We only need the URL
                        $image_url = $image_url[0];

                        // Save random image in cookie
                        Cookies::set($cookie_id, $image_id, 0, false);
                    } else {
                        // Invalidate cookie
                        Cookies::delete($cookie_id);
                    }
                } else {
                    $image_url = wp_get_attachment_image_src($image_id, $size);

                    // Just the URL, please
                    if ($image_url)
                        $image_url = $image_url[0];
                }
            }
        }

        // Fetch extra details about the image, if we have a valid image URL
        if ($image_url) {
            // Disable background image output
            if (!defined('BACKGROUND_IMAGE'))
                define('BACKGROUND_IMAGE', '');

            // Since 3.4
            if (Helpers::checkWPVersion('3.4', '>=')) {
                add_theme_support('custom-background', array('default-image' => ''));
            }

            $results = array(
                'url'       => $image_url,
                'id'        => $image_id,
                'alt'       => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                'link'      => apply_filters('myatu_bgm_image_link', $gallery_id, post_permalink($image_id)), /* filtered since 1.0.34 */
                'bg_link'   => get_post_meta($image_id, Filter\MediaLibrary::META_LINK, true),
                'thumb'     => ($thumb = wp_get_attachment_image_src($image_id, 'thumbnail')) ? $thumb[0] : '',
            );

            // Get the image caption and description
            if (($image = get_post($image_id))) {
                $results['desc']    = wpautop($image->post_content);
                $results['caption'] = $image->post_excerpt;

                // If the caption is empty, substitute it with the title - since 1.0.20
                if (empty($results['caption']))
                    $results['caption'] = $image->post_title;
            }

            // Store the last shown image, if need be
            if ($change_freq == static::CF_CUSTOM && $remember_last)
                Cookies::set($cookie_id, $image_id, 0);
        }

        // Store into cache
        $this->np_cache[$cache_id] = array_merge($defaults, $results);

        return $this->np_cache[$cache_id];
    }

    /**
     * Determines, based on user settings, if the background can be displayed
     *
     * @return bool Returns `true` if the background can be displayed, false otherwise
     */
    public function canDisplayBackground()
    {
        if (isset($this->np_cache['can_display_background']))
            return($this->np_cache['can_display_background']);

        // Obtain a list of custom posts that can be displayed (or not)
        $display_custom_post_types = $this->options->display_custom_post_types;

        if (is_array($display_custom_post_types) && isset($display_custom_post_types[get_post_type()]))
            $this->np_cache['can_display_background'] = $display_custom_post_types[get_post_type()];

        // This isn't a custom post or not specified in settings, so use these
        if (!isset($this->np_cache['can_display_background'])) {
            /* When is_home() is set, it does not report is_page() (even though it is). We use this
             * to figure out if we're at the greeting page */
            $current_url = wp_guess_url();
            if ($qa = strpos($current_url, '?'))
                $current_url = substr($current_url, 0, $qa);

            $is_at_door  = (trailingslashit(home_url()) == trailingslashit($current_url));

            $this->np_cache['can_display_background']  = (
                ($this->options->display_on_front_page  && $is_at_door)     ||
                ($this->options->display_on_single_post && is_single())     ||
                ($this->options->display_on_single_page && ((is_page() && !$is_at_door) || (is_home() && !$is_at_door))) ||
                ($this->options->display_on_archive     && is_archive())    ||
                ($this->options->display_on_search      && is_search())     ||
                ($this->options->display_on_error       && is_404())
            );
        }

        return $this->np_cache['can_display_background'];
    }

    /**
     * Returns if the image can be displayed
     *
     * @param string $path Path to the image
     * @return bool
     */
    public function isDisplayableImage($path)
    {
        $info   = @getimagesize($path);
        $result = false;

        if (!empty($info) && in_array($info[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
            $result = true;

        return $result;
    }

    /**
     * Returns the gallery (post) object
     *
     * @param int $gallery_id The gallery ID
     * @return object The gallery (post) object, or `false` if the gallery ID was invalid
     */
    public function getGallery($gallery_id)
    {
        if ($gallery_id && ($gallery = get_post($gallery_id)) != false && $gallery->post_status != 'trash' && $gallery->post_type == static::PT_GALLERY)
            return $gallery;

        return false;
    }

    /**
     * Returns a list of galleries, for settings
     *
     * @param int $active_gallery The ID of the active gallery (to set 'select')
     * @return array Array containing the galleries, by ID, Name, Description and Selected
     */
    public function getSettingGalleries($active_gallery)
    {
        if (isset($this->np_cache['setting_galleries'])) {
            $galleries = $this->np_cache['setting_galleries'];

            foreach ($galleries as $gallery_idx => $gallery)
                $galleries[$gallery_idx]['selected'] = ($active_gallery == $gallery['id']);

            return $galleries;
        }

        $galleries = array();

        $gallery_posts = get_posts(array(
            'orderby' => 'title',
            'order' => 'ASC',
            'numberposts' => -1,
            'post_type' => static::PT_GALLERY)
        );

        foreach ($gallery_posts as $gallery_post) {
            // Truncate the string, if neccesary
            list($gallery_name) = explode("\n", wordwrap($gallery_post->post_title, 55));
            if (strlen($gallery_name) < strlen($gallery_post->post_title))
                $gallery_name .= ' ...';

            $galleries[] = array(
                'id'       => $gallery_post->ID,
                'name'     => sprintf('%s (%d)', $gallery_name, $this->images->getCount($gallery_post->ID)),
                'desc'     => $gallery_post->post_content,
                'selected' => ($active_gallery == $gallery_post->ID),
            );
        }

        // Store into non-persistent cache
        $this->np_cache['setting_galleries'] = $galleries;

        return $galleries;
    }

    /**
     * Returns a list of overlays, for settings
     *
     * This iterates through the plugin sub-directory specified in DIR_OVERLAYS
     * and for each disiplayable image it finds, it will try to find an accompanying
     * .txt file containing a short, one-line description.
     *
     * The filter `myatu_bgm_overlays` allows more overlays to be added by 3rd parties. All that
     * would be required for this, is to add an array to the existing array with a `value`
     * containing the full pathname (not URL!) to the overlay image and a short one-line description
     * in `desc`. A `selected` key will be handled by this function.
     *
     * @param string $active_overlays The active overlay (to set 'select')
     * @return array Array containing the overlays, by Value, Description, Preview (embedded data image preview) and Selected
     */
    public function getSettingOverlays($active_overlay)
    {
        // Return from cache
        if (isset($this->np_cache['overlays'])) {
            $overlays = $this->np_cache['overlays'];

            // Ensure we have a 'selected' item.
            foreach ($overlays as $overlay_key => $overlay)
                if (!isset($overlay['value']) || !isset($overlay['desc'])) {
                    unset($overlays[$overlay_key]);
                } else {
                    $overlays[$overlay_key]['selected'] = ($active_overlay == $overlay['value']);
                }

            return $overlays;
        }

        $overlays = array();
        $iterator = new \RecursiveIteratorIterator(new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($this->getPluginDir() . static::DIR_OVERLAYS, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && $this->isDisplayableImage($fileinfo->getPathname())) {
                $img_file  = $fileinfo->getPathname();
                $desc      = basename($img_file);
                $desc_file = dirname($img_file) . '/' . basename($img_file, '.' . pathinfo($img_file, PATHINFO_EXTENSION)) . '.txt';

                // Grab the description from an accompanying file, if possible
                if (@is_file($desc_file) && ($handle = @fopen($desc_file, 'r')) != false) {
                    $desc = fgetss($handle);
                    fclose($handle);
                }

                $overlays[] = array(
                    'value'    => $img_file,
                    'desc'     => $desc,
                );
            }
        }

        // Allow WP filtering of overlays
        $overlays = apply_filters(static::BASE_PUB_PREFIX . 'overlays', $overlays);

        // Ensure we have a 'selected' item.
        foreach ($overlays as $overlay_key => $overlay)
            if (!isset($overlay['value']) || !isset($overlay['desc'])) {
                unset($overlays[$overlay_key]);
            } else {
                $overlays[$overlay_key]['selected'] = ($active_overlay == $overlay['value']);
            }

        // Sort overlays
        usort($overlays, function($a, $b){ return strcasecmp($a['desc'], $b['desc']); });

        // Store in non-persistent cache
        $this->np_cache['overlays'] = $overlays;

        return $overlays;
    }

    /**
     * Obtains an array of available Meta boxes
     *
     * @return array Array containing a list of meta boxes
     */
    protected function getMetaBoxes()
    {
        if (!isset($this->np_cache['meta_boxes']))
            $this->np_cache['meta_boxes'] = \Pf4wp\Dynamic\Loader::get(__NAMESPACE__ . '\\Meta', $this->getPluginDir() . static::DIR_META, true);

        return $this->np_cache['meta_boxes'];
    }

    /**
     * Clears all transients related to Background Manager
     */
    protected function clearTransients()
    {
        global $wpdb;

        return $wpdb->get_results("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient%_myatu_bgm_%'");
    }

    /**
     * Removes the original WP Background manager menu and callback
     */
    protected function doRemoveWPBackground()
    {
        if (Helpers::checkWPVersion('3.4', '<')) {
            @remove_custom_background(); // Since WP 3.1
        } else {
            // Since WP 3.4
            if (get_theme_support('custom-background')) {
                remove_theme_support('custom-background');
            }
        }
    }

    /* ----------- Events ----------- */

    /**
     * Perform additional action registration
     *
     * This will replace WordPress' Custom Background with ours
     */
    public function onRegisterActions()
    {
        // Create an public instances
        $this->galleries = new Galleries($this);
        $this->images    = new Images($this);

        // Register post types
        register_post_type(self::PT_GALLERY, array(
            'labels' => array(
                'name'          => __('Background Image Sets', $this->getName()),
                'singular_name' => __('Background Image Set', $this->getName()),
            ),
            'public'              => true,             // Make it available in the Admin 'attach' feature of the Media Library
            'exclude_from_search' => true,             // But hide it from the front-end search...
            'publicly_queryable'  => false,            // ...and front-end query (display)...
            'show_in_nav_menus'   => false,            // ...and hide it as a menu...
            'show_ui'             => false,            // Don't generate its own UI in the Admin
            'hierarchical'        => false,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => array('title'),   // In case onGetEditPostLink() borks
        ));

        // Since 1.0.30 - Customize Theme screen for WP 3.4
        if (Helpers::checkWPVersion('3.4', '>=')) {
            $this->customizer = new \Myatu\WordPress\BackgroundManager\Customizer\Customizer($this);
        }

        // If we're performing an AJAX call, the other bits aren't required
        if (Helpers::DoingAjax())
			return;

        add_action('admin_menu',         array($this, 'onRemoveWPBackground'), 5, 0);
        add_action('wp_head',            array($this, 'onWpHead'));
        add_action('get_edit_post_link', array($this, 'onGetEditPostLink'), 10, 3);
        add_action('add_attachment',     array($this, 'onAddAttachment'), 20);          // Adds 'Background Image' to Library
        add_action('edit_attachment',    array($this, 'onAddAttachment'), 20);
        add_action('admin_bar_menu',     array($this, 'onAdminBarMenu'), 90);

        // @see: onAddAttachment()
        add_theme_support('custom-background');
    }

    /**
     * Called when a WP filter needs to be activated
     */
    public function onFilter($filter)
    {
        switch ($filter) {
            case Filter\MediaLibrary::FILTER :
                new Filter\MediaLibrary($this);
                break;
        }
    }

    /**
     * Called when the plugin is activated
     *
     * This will import the original background into a new image set.
     */
    public function onActivation()
    {
        global $wpdb;

        // Retrieve the background image URL and ID, or return if none specified
        if (!($background_image_url = get_theme_mod('background_image')) ||
            !($background_image_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `guid` = %s", $background_image_url))))
            return;

        $galleries = new Galleries($this);

        // Create a new gallery to hold the original background.
        $gallery_id = $galleries->save(0, __('Imported Background'), __('Automatically created Image Set, containing the original background image specified in WordPress.'));

        // If we created a valid gallery, activate it, add the original background image and remove the theme modification.
        if ($gallery_id && ($image = get_post($background_image_id))) {
            $image->post_content = ''; // Clear the URL from the content, as this will display in the info tab otherwise.

            wp_insert_attachment($image, false, $gallery_id); // Causes an update instead, as image->ID is set
            remove_theme_mod('background_image');

            // Set the gallery to the active one
            $this->options->active_gallery = $gallery_id;
        }

        unset($galleries);
    }

    /**
     * Called when the plugin is de-activated
     */
    public function onDeactivation()
    {
        $this->clearTransients();
    }

    /**
     * Called when the plugin has been upgraded
     */
    public function onUpgrade($previous_version, $current_version)
    {
        $this->clearTransients();

        $this->options->last_upgrade = $current_version;
    }

    /**
     * Initialize the Admin pages
     */
    public function onAdminInit()
    {
        // Initialize meta boxes
        foreach ($this->getMetaBoxes() as $meta_box)
            new $meta_box['class']($this);
    }

    /**
     * Action called when a media attachment is added
     *
     * It will check if the attachment's parent is a gallery. If that
     * is the case, it will add an additional meta to indicate to
     * WordPress it is a background, a la the original custom background
     * provided by WordPress itself, for a backward compatibility.
     *
     * @param int $id Attachement ID
     */
    public function onAddAttachment($id)
    {
        // We only worry about images with a valid parent
        if (!wp_attachment_is_image($id) || !($attachment = get_post($id)) || !($parent = get_post($attachment->post_parent)))
            return;

        // Check if the parent is a gallery, and if so, set the internal (!) custom_background meta.
        if ($parent->post_type == self::PT_GALLERY)
            update_post_meta($id, '_wp_attachment_is_custom_background', get_option('stylesheet'));
    }

    /**
     * Respond to AJAX requests
     *
     * @param string $function The function to perform
     * @param mixed $data The data passed by the Ajax call
     * @return void (Use $this->ajaxResponse())
     */
    public function onAjaxRequest($function, $data)
    {
        global $wpdb;

        switch ($function) {
            /** Returns all the Image IDs within a gallery */
            case 'image_ids' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;

                $id = (int)$data; // Gallery ID

                // This returns the array as an object, where the object property names are the values (ids) of the images
                $this->ajaxResponse((object)array_flip($this->images->getAllImageIds($id)));
                break;

            /** Returns the number of images in the gallery */
            case 'image_count' :
                $id = (int)$data; // Gallery ID

                $this->ajaxResponse($this->images->getCount($id));
                break;

            /** Returns the hash of the images in a gallery */
            case 'images_hash' :
                $id = (int)$data; // Gallery ID

                $this->ajaxResponse($this->images->getHash($id));
                break;

            /** Returns HTML containing pagination links */
            case 'paginate_links' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;

                $id       = (int)$data['id']; // Gallery ID
                $per_page = (int)$data['pp'];
                $base     = $data['base']; // "Base" directory (rather than taking the AJAX url as base)
                $current  = (int)$data['current'];

                if ($current == 0)
                    $current = 1;

                $page_links = paginate_links( array(
                    'base'         => add_query_arg('paged', '%#%', $base),
                    'format'       => '',
                    'prev_text'    => __('&laquo;'),
                    'next_text'    => __('&raquo;'),
                    'total'        => ceil($this->images->getCount($id) / $per_page),
                    'current'      => $current,
                ));

                $this->ajaxResponse($page_links);

                break;

            /** Deletes or removes one or more images from a gallery */
            case 'delete_images' :
            case 'remove_images' :
                if (!current_user_can('edit_theme_options')) // PRIVILEGED
                    return;

                $ids    = explode(',', $data); // Image (post/attachment) IDs
                $result = true;

                foreach($ids as $id) {
                    if (!empty($id)) {
                        if ($function == 'delete_images') {
                            // Delete
                            $result = wp_delete_attachment($id);
                        } else {
                            // Remove
                            $result = $wpdb->update($wpdb->posts, array('post_parent' => 0), array('id' => $id, 'post_type' => 'attachment'));
                        }
                    }

                    if ($result === false)
                        break;

                    $result = true;
                }

                $this->ajaxResponse($result);

                break;

            /** Changes the order of an image */
            case 'change_order' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;

                $inc = (boolean)$data['inc']; // Increase?
                $ids = $this->images->getSortedByOrder(explode(',', $data['ids']), $inc);

                foreach ($ids as $id) {
                    $this->images->changeOrder($id, $inc);
                }

                $this->ajaxResponse(true);

                break;

            /** Select an image randomly or in sequential order from the active gallery */
            case 'select_image' :
                // Extract the URL of the previous image
                if (!preg_match('#^(?:url\(\\\\?[\'\"])?(.+?)(?:\\\\?[\'\"]\))?$#i', $data['prev_img'], $matches))
                    return;
                $prev_image = $matches[1];

                if (isset($data['selector']) && in_array($data['selector'], array(Images::SO_RANDOM, Images::SO_ASC, Images::SO_DESC))) {
                    // Override the selector (by the preview)
                    $image = $this->getImage($prev_image, (int)$data['active_gallery'], false, $data['selector']);
                } else {
                    $image = $this->getImage($prev_image, (int)$data['active_gallery']);
                }

                // Add transition type
                if ($this->options->background_transition == 'random') {
                    // Filter and select random transition
                    $transitions = array_diff_key($this->getBgOptions('transition'), array('none', 'random'));
                    $rand_sel    = array_rand($transitions);

                    $image['transition'] = $transitions[$rand_sel];
                } else {
                    $image['transition'] = $this->options->background_transition;
                }

                // Add transition speed
                $image['transition_speed'] = ((int)$this->options->transition_speed >= 100 && (int)$this->options->transition_speed <= 15000) ? $this->options->transition_speed : 600;

                $this->ajaxResponse((object)$image, empty($image['url']));

                break;

            /** Returns the embedded data for a given overlay */
            case 'overlay_data' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;

                if (($embed_data = Helpers::embedDataUri($data, 'image/png', (defined('WP_DEBUG') && WP_DEBUG))) != false)
                    $this->ajaxResponse($embed_data);

                break;

            default:
                break;
        }
    }

    /**
     * This provides the correct edit link to WordPress for our post types
     *
     * This can be noted in the Library, where clicking on the attachment's link
     * to a PT_GALLERY post type will bring us to the edit form here.
     *
     * @param string $url The original URL
     * @param int $id The post ID
     * @param string $context The context where the link is used (ie., 'display')
     * @return string The original or modified URL
     */
    public function onGetEditPostLink($url, $id, $context)
    {
        if (get_post_type($id) == self::PT_GALLERY) {
            $url = add_query_arg('edit', $id, $this->edit_gallery_link);

            if ($context == 'display')
                $url = esc_url($url);
        }

        return $url;
    }

    /**
     * This modifies the Admin Bar
     *
     * @param mixed $wp_admin_bar Admin bar object
     * @internal
     */
    public function onAdminBarMenu($wp_admin_bar)
    {
        try {
            if (!is_admin() && $wp_admin_bar->get_node('background') && function_exists('get_user_option') && ($home_url = get_user_option('myatu_bgm_home_url'))) {
                $wp_admin_bar->remove_node('background');

                $wp_admin_bar->add_node(array(
                    'parent' => 'appearance',
                    'id'     => 'background',
                    'title'  => __('Background'),
                    'href'   => $home_url
                ));
            }

            // Remove the 'View Post' from the admin bar
            if (is_admin())
                $wp_admin_bar->remove_node('view');
        } catch (\Exception $e) { /* Silent, to prevent public side from becoming inaccessible on error */ }
    }

    /**
     * Event called that remove WP's original Background manager from the Admin menu's
     */
    public function onRemoveWPBackground()
    {
        $this->doRemoveWPBackground();
    }

    /**
     * Build the menu
     */
    public function onBuildMenu()
    {
        $mymenu = new \Pf4wp\Menu\SubHeadMenu($this->getName());

        // Add settings menu
        $main_menu = $mymenu->addMenu(__('Background'), array(new Pages\Settings($this), 'onSettingsMenu'));
        $main_menu->page_title = $this->getDisplayName();
        $main_menu->large_icon = 'icon-themes';
        $main_menu->context_help = new ContextHelp($this, 'settings');

        // Add image sets (galleries) submenu
        $gallery_menu = $mymenu->addSubmenu(__('Image Sets', $this->getName()), array(new Pages\Galleries($this), 'onGalleriesMenu'));
        $gallery_menu->count = $this->getGalleryCount();
        $gallery_menu->context_help = new ContextHelp($this, 'galleries');
        if (!$this->inEdit())
            $gallery_menu->per_page = 15; // Add a `per page` screen setting

        // If there are items in the Trash, display this menu too:
        if ($count = $this->getGalleryCount(false)) {
            $trash_menu = $mymenu->addSubmenu(__('Trash', $this->getName()), array(new Pages\Galleries($this, true), 'onGalleriesMenu'));
            $trash_menu->count = $count;
            $trash_menu->context_help = new ContextHelp($this, 'trash');
            $trash_menu->per_page = 15;
        }

        // Import menu
        $import_menu = $mymenu->addSubMenu(__('Import', $this->getName()), array(new Pages\Import($this), 'onImportMenu'));
        $import_menu->context_help = new ContextHelp($this, 'import');

        // Make it appear under WordPress' `Appearance` (theme_options)
        $mymenu->setType(\Pf4wp\Menu\MenuEntry::MT_THEMES);

        // Give the 'Home' a different title
        $mymenu->setHomeTitle(__('Settings', $this->getName()));

        // Theme options URL
        $theme_options_url = menu_page_url('theme_options', false);
        $theme_options_url = ($theme_options_url) ? $theme_options_url : admin_url('themes.php'); // As of WP3.3

        // Set an edit link
        $this->edit_gallery_link = add_query_arg(
            array(
                \Pf4wp\Menu\CombinedMenu::SUBMENU_ID => $gallery_menu->getSlug(),
                'page' => $gallery_menu->getSlug(true),
                'edit' => 'new',
            ),
            $theme_options_url
        );

        // Set the import menu link
        $this->import_menu_link = add_query_arg(
            array(
                \Pf4wp\Menu\CombinedMenu::SUBMENU_ID => $import_menu->getSlug(),
                'page'           => $gallery_menu->getSlug(true),
                'run_import_job' => false,
                'nonce'          => false,
            ),
            $theme_options_url
        );

        // Add an 'Add New Image Set' link to the main title, if not editing an image set
        if (($this->inEdit() && $this->gallery->post_status != 'auto-draft') || (($active_menu = $mymenu->getActiveMenu()) == false) || $active_menu != $gallery_menu) {
            // Replace existing main page title with one that contains a link
            $main_menu->page_title_extra = sprintf(
                '<a class="add-new-h2" id="add_new_image_set" href="%s">%s</a>',
                esc_url($this->edit_gallery_link),
                __('Add New Image Set', $this->getName())
            );
        }

        // Display is usually called automatically, but we use it to grab the parent menu URL and set it in the user option
        $mymenu->display();

        if (($user = wp_get_current_user()) instanceof \WP_User)
            update_user_option($user->ID, 'myatu_bgm_home_url', $mymenu->getParentUrl());

        return $mymenu;
    }

    /**
     * Loads Base Admin Scripts
     */
    public function onAdminScripts()
    {
        list($js_url, $version, $debug) = $this->getResourceUrl();

        wp_enqueue_script('post');
        wp_enqueue_script('media-upload');
        wp_enqueue_script($this->getName() . '-functions', $js_url . 'functions' . $debug . '.js', array('jquery'), $version);
    }

    /**
     * Load Admin CSS
     */
    public function onAdminStyles()
    {
        list($css_url, $version, $debug) = $this->getResourceUrl('css');

        wp_enqueue_style($this->getName() . '-admin', $css_url . 'admin' . $debug . '.css', false, $version);
    }

    /* ----------- Public ----------- */

    /**
     * Public initalization
     */
    public function onPublicInit()
    {
        // Remove the original WP Background callback
        $this->doRemoveWPBackground();

        // This activates the *filters* provided by the Meta Boxes
        foreach ($this->getMetaBoxes() as $meta_box)
            new $meta_box['class']($this);
    }

    /**
     * Called on wp_head, rendering the stylesheet as late as possible
     *
     * This will provide a basic background image and colors, along with
     * tiling options.
     */
    public function onWpHead()
    {
        if (is_admin() || !$this->canDisplayBackground())
            return;

        $style = '';

        // Get option values after applying filters
        extract($this->getFilteredOptions());

        $custom_styles = apply_filters(static::BASE_PUB_PREFIX . 'custom_styles', $active_gallery, ''); // From Meta

        // Only add a background image here if we have a valid gallery and we're not using a full-screen image
        if ($this->getGallery($active_gallery) != false && $background_size != static::BS_FULL) {
            $random_image = $this->getImage();

            if ($random_image['url'])
                $style .= sprintf('background-image: url(\'%s\');', $random_image['url']);

            // Grab the background position
            if (!$background_position) {
                $bg_positions        = $this->getBgOptions('position');
                $background_position = $bg_positions[0];
            }
            $background_position  = explode('-', $background_position);

            $style .= sprintf('background-position: %s %s;', $background_position[0], $background_position[1]);

            // Set the background tiling
            $bg_repeats = $this->getBgOptions('repeat');
            $style .= sprintf('background-repeat: %s;', ($background_repeat) ? $background_repeat : $bg_repeats[0]);

            // Set background scrolling
            $style .= sprintf('background-attachment: %s;', ($background_scroll) ? $background_scroll : static::BST_SCROLL);

            // Set background sizing (stretching)
            if ($background_stretch_horizontal || $background_stretch_vertical) {
                $style .= sprintf('background-size: %s %s;',
                    ($background_stretch_horizontal) ? '100%' : 'auto',
                    ($background_stretch_vertical) ? '100%' : 'auto'
                );
            }
        } else {
            $style .= sprintf('background-image: none !important;');
        }

        if ($background_color)
            $style .= sprintf('background-color: #%s;', $background_color);

        if ($style || $custom_styles)
            printf('<style type="text/css" media="screen">body { %s } %s</style>'.PHP_EOL, $style, $custom_styles);
    }

    /**
     * Load public scripts
     */
    public function onPublicScripts()
    {
        if (!$this->canDisplayBackground())
            return;

        extract($this->getFilteredOptions());
        $is_preview = false; // If we're in the 3.4 Customize Theme Preview, this will be set to true.

        // 3.4+ filters
        if (Helpers::checkWPVersion('3.4', '>=')) {
            global $wp_customize;

            if (is_a($wp_customize, static::WP_CUSTOMIZE_MANAGER_CLASS)) {
                $is_preview = $wp_customize->is_preview();
            }
        }

        /* Only load the scripts if:
         * - there's custom change frequency
         * - the background is full screen
         * - there's a click-able image in the background set // since @1.0.45 - see http://wordpress.org/support/topic/plugin-background-manager-link-in-background-not-working
         * - or, there's an info tab with a short description
         */
        /*if ($change_freq != static::CF_CUSTOM &&
            $background_size != static::BS_FULL &&
            !$this->images->hasLinkedImages($active_gallery) && // since @1.0.45
            !($info_tab && $info_tab_desc))
            return;*/

        // Enqueue jQuery and base functions
        list($js_url, $version, $debug) = $this->getResourceUrl();

        wp_enqueue_script('jquery');
        wp_enqueue_script($this->getName() . '-functions', $js_url . 'functions' . $debug . '.js', array('jquery'), $version);
        wp_enqueue_script($this->getName() . '-flux',      $js_url . 'flux' . $debug . '.js',      array($this->getName() . '-functions'), $version);
        wp_enqueue_script($this->getName() . '-pub',       $js_url . 'pub' . $debug . '.js',       array($this->getName() . '-functions', $this->getName() . '-flux'), $version);

        // If the info tab is enabled along with the short description, also include qTip2
        if ($info_tab && $info_tab_desc)
            wp_enqueue_script('jquery.qtip', $js_url . 'vendor/qtip/jquery.qtip.min.js', array('jquery'), $version);

        // The change frequency is not 0 (disabled) if we have a custom frequency, valid gallery and it contans more than one image
        if ($change_freq == static::CF_CUSTOM && $this->getGallery($active_gallery) != false && $this->images->getCount($active_gallery) > 1) {
            $script_change_freq = ($change_freq_custom >= 1) ? $change_freq_custom : 10;
        } else {
            $script_change_freq = 0; // Disabled
        }

        // Current background variable
        $current_background = $this->getImage();
        $current_background['transition']       = $background_transition;
        $current_background['transition_speed'] = $script_change_freq;

        // Spit out variables for JavaScript to use
        $script_vars = array(
            'current_background'       => (object)$current_background,
            'change_freq'              => $script_change_freq,
            'active_gallery'           => $active_gallery,
            'is_fullsize'              => ($background_size == static::BS_FULL) ? 'true' : 'false',
            'is_preview'               => ($is_preview) ? 'true' : 'false',
            'initial_ease_in'          => ($initial_ease_in) ? 'true' : 'false',
            'info_tab_thumb'           => ($info_tab_thumb) ? 'true' : 'false',
            'bg_click_new_window'      => ($this->options->bg_click_new_window) ? 'true' : 'false',
            'bg_track_clicks'          => ($this->options->bg_track_clicks) ? 'true' : 'false',
            'bg_track_clicks_category' => $this->options->bg_track_clicks_category,

        );

        // Add to variables if in full screen mode
        if ($background_size == static::BS_FULL) {
            $script_vars = array_merge($script_vars, array(
                'fs_center' => ($full_screen_center) ? 'true' : 'false',
            ));
        }

        // Also add the active transtion, transition speed and available transitions if we're in a preview (3.4+)
        if ($is_preview) {
            $script_vars = array_merge($script_vars, array(
                'active_transition' => $background_transition,
                'transition_speed'  => $transition_speed,
                'image_selection'   => $image_selection,
                'transitions'       => array_values(array_diff_key($this->getBgOptions('transition'), array('none', 'random'))),
            ));
        }

        // Spit out the script variables (!! always attach to -functions !!)
        wp_localize_script($this->getName() . '-functions', 'myatu_bgm', $script_vars);
    }

    /**
     * Load public styles
     */
    public function onPublicStyles()
    {
        if (!$this->canDisplayBackground())
            return;

        $style = '';

        // Extract filtered options
        extract($this->getFilteredOptions());

        list($css_url, $version, $debug) = $this->getResourceUrl('css');

        // Default CSS for the public side
        wp_enqueue_style($this->getName() . '-pub', $css_url . 'pub' . $debug . '.css', false, $version);

        // qTip2 style, if required
        if ($info_tab && $info_tab_desc)
            wp_enqueue_style('jquery.qtip', $css_url . 'vendor/jquery.qtip.min.css', false, $version);

        // The image for the overlay, as CSS embedded data
        if ($active_overlay && ($data = Helpers::embedDataUri($active_overlay, 'image/png', (defined('WP_DEBUG') && WP_DEBUG))) != false) {
            $opacity_style = '';

            if ($overlay_opacity < 100)
                $opacity_style = sprintf('-moz-opacity:.%s; filter:alpha(opacity=%1$s); opacity:.%1$s', str_pad($overlay_opacity, 2, '0', STR_PAD_LEFT));

            $style .= sprintf('#myatu_bgm_overlay{background:url(\'%s\') repeat fixed top left transparent; %s}', $data, $opacity_style);
        }

        // The info icon
        if ($info_tab)
            $style .= sprintf('#myatu_bgm_info_tab{%s}', $this->getCornerStyle($info_tab_location, 5, 5));

        // The "Pin It" button
        if ($pin_it_btn) {
            // Horizontal spacer depends whether the info tab is shown as well
            $hspacer = ($info_tab && ($info_tab_location == $pin_it_btn_location)) ? 35 : 10;

            $style .= sprintf('#myatu_bgm_pin_it_btn{%s}', $this->getCornerStyle($pin_it_btn_location, $hspacer, 5));
        }

        if ($style)
            printf('<style type="text/css" media="screen">%s</style>' . PHP_EOL, $style);
    }

    /**
     * Add a footer to the public side
     *
     * Instead of using a BODY background, this will use an IMG to generate a full
     * screen rendering of a random image and an overlay, provided either of
     * these options have been enabled by the user
     */
    public function onPublicFooter()
    {
        if (!$this->canDisplayBackground())
            return;

        // Extract filtered options
        extract($this->getFilteredOptions());

        $valid_gallery = ($this->getGallery($active_gallery) != false);

        $vars = array(
            'has_info_tab'   => $info_tab && $valid_gallery, // Only display if we have a valid gallery
            'info_tab_link'  => $info_tab_link,
            'has_pin_it_btn' => $pin_it_btn && $valid_gallery,
            'has_overlay'    => ($active_overlay != false),
            'opacity'        => str_pad($background_opacity, 2, '0', STR_PAD_LEFT), // Only available to full size background
            'is_fullsize'    => $background_size == static::BS_FULL,
            'random_image'   => $this->getImage(),
            'permalink'      => get_site_url() . $_SERVER['REQUEST_URI'],
        );

        $this->template->display('pub_footer.html.twig', $vars);
    }
}
