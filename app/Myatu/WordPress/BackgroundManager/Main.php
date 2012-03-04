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

/* Guided Help using FeaturePointers */

/** Step 1 of Guided Help. @see onSettingsMenuLoad() */
class PointerAddNewStep1 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#add_new_image_set';
    protected $position = array('align' => 'left');
    
    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p>Start by adding a new <em>Image Set</em><p><p>Once you\'re done, come back here and set it as the active <em>Background Image Set</em>.</p>', $textdomain),
            __('Let\'s Get Started!', $textdomain)
        );
    }
}

/** Step 2 of Guided Help. @see onGalleriesMenuLoad() (Edit section) */
class PointerAddNewStep2 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#add_add_image';
    protected $position = array('edge' => 'left');

    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p>Click on this icon to start adding some images.</p><p>Save using the <strong>Add Image Set</strong> button. Don\'t forget to add a title once you\'re done!</p>', $textdomain),
            __('Add Some Images!', $textdomain)
        );
    }    
}


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
    
    /* Possible background positions */
    private $bg_positions = array('top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right');
    
    /* Possible background tiling options */
    private $bg_repeats = array('repeat', 'repeat-x', 'repeat-y', 'no-repeat');
    
    /* Possible corner locations */
    private $corner_locations = array('top-left', 'top-right', 'bottom-left', 'bottom-right');
    
    /* Possible transition options */
    private $bg_transitions = array(
        'none', 'random',
        'slidedown', 'slideup', 'slideleft', 'slideright',
        'coverdown', 'coverup', 'coverleft', 'coverright',
        'crossfade',
    );
    
    /** Instance containing current gallery being edited (if any) */
    private $gallery = null;
    
    /** Instance to a List\Galleries - @see onGalleriesMenu(), onTrashMenu() */
    private $list;
    
    /** The link to edit Galleries - @see onBuildMenu() */
    private $edit_gallery_link = '';
    
    /** The link to the Import menu - @see onBuildMenu() */
    public $import_menu_link  = '';
    
    /** Instance of Images - @see onAdminInit() */
    public $images;
    
    /** Instance of Galleries - @see onAdminInit() */
    public $galleries;

    /** Non-persistent Cache */
    private $np_cache = array();
   
    /** The default options */
    protected $default_options = array(
        'change_freq'            => 'load',      // static::CF_LOAD
        'change_freq_custom'     => 10,
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
        'info_tab_location'      => 'bottom-left',
        'info_tab_thumb'         => true,
        'info_tab_desc'          => true,
        'pin_it_btn_location'    => 'bottom-left', // Since 1.0.20
    );
        
    /* Enable public-side Ajax - @see onAjaxRequest() */
    public $public_ajax = true;
  
    
    /* ----------- Helpers ----------- */
    
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
        global $wpdb;
        
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
            }
        } // else empty, return default (false)
        
        $this->np_cache['in_edit'] = $result; // Cache response (non-persistent)
        
        return $result;
    }
    
    /**
     * Helper that obtains a random image, including all details about it
     *
     * @filter myatu_bgm_active_gallery
     * @param string $previous_image The URL of the previous image, if any (to avoid duplicates)
     * @param id $active_id Active gallery, or `false` if to be determined automatically (default)
     * @param string $size The size of the image to return (original size by default)
     * @return array
     */
    public function getRandomImage($previous_image = '', $active_id = false, $size = false)
    {
        $bailout      = 0; // Loop bailout
        $random_id    = 0;
        $random_image = '';
        $desc         = '';
        $caption      = '';
        $alt          = '';
        $link         = '';
        $thumb        = '';
        $meta         = '';
        $bg_link      = '';
        
        if ($active_id === false) {
            $gallery_id = apply_filters('myatu_bgm_active_gallery', $this->options->active_gallery);
        } else {
            $gallery_id = $active_id;
        }
        
        if ($this->getGallery($gallery_id) != false) {
            // Create an instance of Images, if needed
            if (!isset($this->images))
                $this->images = new Images($this);
            
            switch ($this->options->change_freq) {
                case static::CF_SESSION:
                    $cookie_id = 'myatu_bgm_bg_id_' . $gallery_id; // Cookie ID for stored background image ID
                    
                    while (true) {
                        $bailout++;
                        
                        $random_id    = Cookies::get($cookie_id, $this->images->getRandomImageId($gallery_id));                       
                        $random_image = wp_get_attachment_image_src($random_id, $size);
                        
                        if ($random_image) {
                            // We only need the URL
                            $random_image = $random_image[0];
                        
                            // Save random image in cookie
                            Cookies::set($cookie_id, $random_id, 0, false);
                            
                            break;
                        } else {
                            // Invalidate cookie
                            Cookies::delete($cookie_id);
                        }
                        
                        // The bailout clause
                        if ($bailout > 2) {
                            $random_image = '';
                            break;
                        }
                    }
                    
                    break;
                    
                case static::CF_CUSTOM:
                    $only_one_available = ($this->images->getCount($gallery_id) == 1);
                    
                    while (true) {
                        $bailout++;
                        
                        $random_id    = $this->images->getRandomImageId($gallery_id);
                        $random_image = wp_get_attachment_image_src($random_id, $size);
                        
                        if ($random_image) {
                            $random_image = $random_image[0]; // URL
                            
                            // Make sure it isn't the same as the previous image, if specified
                            if ((!empty($previous_image) && $random_image != $previous_image) || empty($previous_image))
                                break;
                        }
                        
                        // Bailout clause
                        if ($bailout > 99 || $only_one_available) {
                            $random_image = ''; // Invalidate result
                            break;
                        }
                    }
                    
                    break;
                    
                default: // CF_LOAD
                    $random_id    = $this->images->getRandomImageId($gallery_id);
                    $random_image = wp_get_attachment_image_src($random_id, $size);
                    
                    // All we need is the URL here
                    if ($random_image)
                        $random_image = $random_image[0];
                    
                    break;
            }
        }
        
        // Set the BACKGROUND_IMAGE definition and fetch additional details about the image
        if ($random_image) {
            if (!defined('BACKGROUND_IMAGE'))
                define('BACKGROUND_IMAGE', $random_image);
            
            // Get the ALT image meta
            $alt  = get_post_meta($random_id, '_wp_attachment_image_alt', true);
            $link = post_permalink($random_id);
            
            // Get the image metadata
            $meta = get_post_meta($random_id, '_wp_attachment_metadata', true);
            if ($meta && is_array($meta)) {
                $meta = $meta['image_meta']; // All we grab is the EXIF/IPTC meta
            } else {
                $meta = '';
            }
            
            // Get background link
            $bg_link = get_post_meta($random_id, Filter\MediaLibrary::META_LINK, true);
            
            // Get a thumbnail image link
            $thumb = wp_get_attachment_image_src($random_id, 'thumbnail');
            if ($thumb) {
                $thumb = $thumb[0];
            } else {
                $thumb = '';
            }
            
            // Get the image caption and description
            if (($image = get_post($random_id))) {
                $desc    = $image->post_content;
                $caption = $image->post_excerpt;
                
                // If the caption is empty, substitute it with the title - since 1.0.20
                if (empty($caption))
                    $caption = $image->post_title;
            }
        }

        return array(
            'id'      => $random_id, 
            'url'     => $random_image, 
            'alt'     => $alt, 
            'desc'    => wpautop($desc), 
            'caption' => $caption,
            'link'    => $link, 
            'thumb'   => $thumb,
            'meta'    => $meta,
            'bg_link' => $bg_link
        );
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
            $is_at_door = (home_url() == wp_guess_url());

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
     * @filter myatu_bgm_overlays
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
            if ($fileinfo->isFile() && file_is_displayable_image($fileinfo->getPathname())) {
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
        $overlays = apply_filters('myatu_bgm_overlays', $overlays);
        
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
     * Obtains an array of available Importers
     *
     * @filter myatu_bgm_importers
     * @return array Array containing a list of importers, indexed by classname, containing a display name, description and namespace+class.
     */
    protected function getImporters()
    {
        if (isset($this->np_cache['importers']))
            return $this->np_cache['importers'];
        
        $importers = array();
        $iterator  = new \RecursiveIteratorIterator(new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($this->getPluginDir() . static::DIR_IMPORTERS, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        $files     = iterator_to_array(new \RegexIterator($iterator, '/^.+\.php?$/i', \RecursiveRegexIterator::GET_MATCH));
        
        foreach ($files as $file) {
            $class_name = basename($file[0], '.php');
            $class      = __NAMESPACE__ . '\\Importers\\' . $class_name;
            
            // Obtain information about the Importer, and add it to the array
            if (is_callable($class . '::info'))
                $importers[$class_name] = array_merge($class::info(), array('class' => $class));
        }
        
        // Allow filtering of importers
        $importers = apply_filters('myatu_bgm_importers', $importers);
        
        // Classes must be a subclass of Importers\Importer
        foreach ($importers as $importer_key => $importer)
            if (!is_subclass_of($importer['class'], __NAMESPACE__ . '\\Importers\\Importer'))
                unset($importers[$importer_key]);
        
        // Sort importers by name
        uasort($importers, function($a, $b){ return strcasecmp($a['name'], $b['name']); });
        
        // Store in non-persistent cache
        $this->np_cache['importers'] = $importers;
        
        return $importers;
    }
    
    /**
     * Clears all transients related to Background Manager
     */
    protected function clearTransients()
    {
        global $wpdb;
        
        return $wpdb->get_results("DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '_transient%_myatu_bgm_%'");

    }
    
    /* ----------- Events ----------- */
    
    /**
     * Perform additional registerActions()
     *
     * This will replace WordPress' Custom Background with ours
     */
    public function registerActions()
    {
        parent::registerActions();
        
        // Remove the original 'Background' menu and WP's callback
        remove_custom_background(); // Since WP 3.1
        
        // Register additional actions
        add_action('wp_head', array($this, 'onWpHead'));
        add_action('get_edit_post_link', array($this, 'onGetEditPostLink'), 10, 3);
        add_action('add_attachment', array($this, 'onAddAttachment'), 20);      // Adds 'Background Image' to Library
        add_action('edit_attachment', array($this, 'onAddAttachment'), 20);
        add_action('admin_bar_menu', array($this, 'onAdminBarMenu'), 90);
        
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
        
        // @see: onAddAttachment()
        add_theme_support('custom-background');
        
        // Check if there are any on-demand filters requested
        $filters = array();
        if (isset($_REQUEST['filter']))
            $filters = explode(',', $_REQUEST['filter']);
        
        // And check the referer arguments as well if this is a POST request
        if (!empty($_POST) && isset($_SERVER['HTTP_REFERER'])) {
            $referer_args = explode('&', ltrim(strstr($_SERVER['HTTP_REFERER'], '?'), '?'));
            foreach ($referer_args as $referer_arg)
                if (!empty($referer_arg) && strpos($referer_arg, '=') !== false) {
                    list($arg_name, $arg_value) = explode('=', $referer_arg);
                    if ($arg_name == 'filter') {
                        $filters = array_replace($filters, explode(',', $arg_value));
                        break;
                    }
                }
        }
        
        // Remove any possible duplicates
        $filters = array_unique($filters);
        
        foreach ($filters as $filter)
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
        
        // Create a new gallery to hold the original background.
        $galleries  = new Galleries($this);
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
    }
    
    /**
     * Initialize the Admin pages
     */
    public function onAdminInit()
    {      
        // Create an public instances
        $this->galleries = new Galleries($this);
        $this->images    = new Images($this);
        
        // Initialize meta boxes
        if (current_user_can('edit_theme_options')) {
            new Meta\Submit($this);
            new Meta\Stylesheet($this);
            new Meta\Single($this); // for Posts and Pages
            new Meta\Tags($this);
            new Meta\Categories($this);
        }
    }
    
    /**
     * Initialize the Public side
     */
    public function onPublicInit()
    {
        // This activates the *filters* provided by the Meta Boxes
        new Meta\Stylesheet($this);
        new Meta\Single($this);
        new Meta\Tags($this);
        new Meta\Categories($this);
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
        // as onAdminInit does not get called before Ajax requests, set up the Images instance if needed
        if (!isset($this->images))
            $this->images = new Images($this);
        
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
            
            /** Deletes one or more images from a gallery */
            case 'delete_images' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;
                    
                $ids    = explode(',', $data); // Image (post/attachment) IDs
                $result = true;

                foreach($ids as $id) {
                    if (!empty($id))
                        $result = wp_delete_attachment($id);
                        
                    if ($result === false)
                        break;
                        
                    $result = true;
                }
                
                $this->ajaxResponse($result);
                
                break;
            
            /** Returns a randomly selected image from the active gallery */
            case 'random_image' :
                // Extract the URL of the previous image
                if (!preg_match('#^(?:url\(\\\\?[\'\"])?(.+?)(?:\\\\?[\'\"]\))?$#i', $data['prev_img'], $matches))
                    return;
                
                $prev_image   = $matches[1];
                $random_image = $this->getRandomImage($prev_image, (int)$data['active_gallery']);
                
                // Remove 'meta', as we do not use this in the front-end
                unset($random_image['meta']);
                
                // Add transition type
                if ($this->options->background_transition == 'random') {
                    // Filter and select random transition
                    $transitions = array_diff_key($this->bg_transitions, array('none', 'random'));
                    $rand_sel    = array_rand($transitions);

                    $random_image['transition'] = $transitions[$rand_sel];
                } else {
                    $random_image['transition'] = $this->options->background_transition;
                }
                
                // Add transition speed
                $random_image['transition_speed'] = ((int)$this->options->transition_speed >= 100 && (int)$this->options->transition_speed <= 7500) ? $this->options->transition_speed : 600;
                
                $this->ajaxResponse((object)$random_image, empty($random_image['url']));
                
                break;
            
            /** Returns the embedded data for a given overlay */
            case 'overlay_data' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;
                    
                if (($embed_data = Helpers::embedDataUri($data, false, (defined('WP_DEBUG') && WP_DEBUG))) != false)
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
     * This replaces the `Background` menu on the public Admin Bar with our own, if it is shown
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
        } catch (\Exception $e) { /* Silent, to prevent public side from becoming inaccessible on error */ }
    }
    
    /**
     * Build the menu
     */
    public function onBuildMenu()
    {
        $mymenu = new \Pf4wp\Menu\SubHeadMenu($this->getName());
        
        // Add settings menu
        $main_menu = $mymenu->addMenu(__('Background'), array($this, 'onSettingsMenu'));
        $main_menu->page_title = $this->getDisplayName();
        $main_menu->large_icon = 'icon-themes';
        $main_menu->context_help = new ContextHelp($this, 'settings');
        
        // Add image sets (galleries) submenu
        $gallery_menu = $mymenu->addSubmenu(__('Image Sets', $this->getName()), array($this, 'onGalleriesMenu'));
        $gallery_menu->count = $this->getGalleryCount();
        $gallery_menu->context_help = new ContextHelp($this, 'galleries');
        if (!$this->inEdit())
            $gallery_menu->per_page = 15; // Add a `per page` screen setting
        
        // If there are items in the Trash, display this menu too:
        if ($count = $this->getGalleryCount(false)) {
            $trash_menu = $mymenu->addSubmenu(__('Trash', $this->getName()), array($this, 'onTrashMenu'));
            $trash_menu->count = $count;
            $trash_menu->context_help = new ContextHelp($this, 'trash');
            $trash_menu->per_page = 15;
        }
        
        // Import menu
        $import_menu = $mymenu->addSubMenu(__('Import', $this->getName()), array($this, 'onImportMenu'));
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
                'page' => $gallery_menu->getSlug(true),
                'run_import_job' => false,
                'nonce' => false,
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
        
        // Display is usually called automatically, but we use it to grab the parent menu URL and set it in the user option (varies by translation!)
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
    
    /**
     * Handles pre-Settings Menu actions
     *
     * @see onSettingsMenu()
     * @param object $current_screen The current screen object
     */
    public function onSettingsMenuLoad($current_screen)
    {
        // Extra scripts to include
        list($js_url, $version, $debug) = $this->getResourceUrl();

        // Color picker
        wp_enqueue_script('farbtastic');        
        
        // Slider
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-slider');
        
        // Default Functions
        wp_enqueue_script($this->getName() . '-settings', $js_url . 'settings' . $debug . '.js', array($this->getName() . '-functions'), $version);
        
        // Extra CSS to include
        list($css_url, $version, $debug) = $this->getResourceUrl('css');
        
        // Color picker
        wp_enqueue_style('farbtastic');
        
        // Slider
        wp_enqueue_style('jquery-ui-slider', $css_url . 'vendor/jquery-ui-slider' . $debug . '.css', false, $version);
        
        // Guided Help, Step 1 ("Get Started")
        new PointerAddNewStep1($this->getName());
        
        // Save settings if POST is set
        if (!empty($_POST) && isset($_POST['_nonce'])) {
            if (!wp_verify_nonce($_POST['_nonce'], 'onSettingsMenu'))
                wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
            
            $this->options->active_gallery                = (int)$_POST['active_gallery'];      
            $this->options->change_freq                   = (in_array($_POST['change_freq'], array(static::CF_SESSION, static::CF_LOAD, static::CF_CUSTOM))) ? $_POST['change_freq'] : null;
            $this->options->change_freq_custom            = (int)$_POST['change_freq_custom'];
            $this->options->background_size               = (in_array($_POST['background_size'], array(static::BS_FULL, static::BS_ASIS))) ? $_POST['background_size'] : null;
            $this->options->background_scroll             = (in_array($_POST['background_scroll'], array(static::BST_FIXED, static::BST_SCROLL))) ? $_POST['background_scroll'] : null;
            $this->options->background_position           = (in_array($_POST['background_position'], $this->bg_positions)) ? $_POST['background_position'] : null;
            $this->options->background_repeat             = (in_array($_POST['background_repeat'], $this->bg_repeats)) ? $_POST['background_repeat'] : null;
            $this->options->background_transition         = (in_array($_POST['background_transition'], $this->bg_transitions)) ? $_POST['background_transition'] : null;
            $this->options->transition_speed              = (int)$_POST['transition_speed'];
            $this->options->background_stretch_vertical   = (!empty($_POST['background_stretch_vertical']));
            $this->options->background_stretch_horizontal = (!empty($_POST['background_stretch_horizontal']));
            $this->options->active_overlay                = (string)$_POST['active_overlay'];
            $this->options->display_on_front_page         = (!empty($_POST['display_on_front_page']));
            $this->options->display_on_single_post        = (!empty($_POST['display_on_single_post']));
            $this->options->display_on_single_page        = (!empty($_POST['display_on_single_page']));
            $this->options->display_on_archive            = (!empty($_POST['display_on_archive']));
            $this->options->display_on_search             = (!empty($_POST['display_on_search']));
            $this->options->display_on_error              = (!empty($_POST['display_on_error']));
            $this->options->info_tab                      = (!empty($_POST['info_tab']));
            $this->options->info_tab_location             = (in_array($_POST['info_tab_location'], $this->corner_locations)) ? $_POST['info_tab_location'] : null;
            $this->options->info_tab_thumb                = (!empty($_POST['info_tab_thumb']));
            $this->options->info_tab_link                 = (!empty($_POST['info_tab_link']));
            $this->options->info_tab_desc                 = (!empty($_POST['info_tab_desc']));
            $this->options->pin_it_btn                    = (!empty($_POST['pin_it_btn']));
            $this->options->pin_it_btn_location           = (in_array($_POST['pin_it_btn_location'], $this->corner_locations)) ? $_POST['pin_it_btn_location'] : null;
            
            // Opacity (1-100)
            if (($opacity = (int)$_POST['background_opacity']) <= 100 && $opacity > 0)
                $this->options->background_opacity = $opacity;
                
            if (($opacity = (int)$_POST['overlay_opacity']) <= 100 && $opacity > 0)
                $this->options->overlay_opacity = $opacity;

            
            // Display settings for Custom Post Types
            $display_on = array();
            
            foreach (get_post_types(array('_builtin' => false, 'public' => true), 'objects') as $post_type_key => $post_type) {
                // Iterate over existing custom post types, filtering out whether it can be shown or not
                if ($post_type_key !== static::PT_GALLERY)
                    $display_on[$post_type_key] = (!empty($_POST['display_on'][$post_type_key]));
            }
            
            $this->options->display_custom_post_types = $display_on;
            
            // Slightly different, the background color is saved as a theme mod only.
            $background_color = ltrim($_POST['background_color'], '#');            
            if (empty($background_color)) {
                remove_theme_mod('background_color');
            } else if (preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $background_color)) {
                set_theme_mod('background_color', $background_color);
            }
            
            AdminNotice::add(__('Settings have been saved', $this->getName()));
        }
    }
    
    /**
     * Settings Menu
     */
    public function onSettingsMenu($data, $per_page)
    {
        global $wp_version, $wpdb;
        
        // Generate a list of galleries, including a default of "None", and set a flag if we can use collages
        $galleries = array_merge(array(
            array(
                'id' => 0, 
                'name' => __('-- None (deactivated) --', $this->getName()), 
                'selected' => ($this->options->active_gallery == false),
            )
        ), $this->getSettingGalleries($this->options->active_gallery));
        
        // Grab the overlays and add a default of "None"
        $overlays = array_merge(array(
            array(
                'value'    => '',
                'desc'     => __('-- None (deactivated) --', $this->getName()),
                'selected' => ($this->options->active_overlay == false),
            ),
        ), $this->getSettingOverlays($this->options->active_overlay));
        
        
        // Give the background positions a human readable titles
        $bg_position_titles = array(
            __('Top Left', $this->getName()), __('Top Center', $this->getName()), __('Top Right', $this->getName()), 
            __('Center Left', $this->getName()), __('Center', $this->getName()), __('Center Right', $this->getName()), 
            __('Bottom Left', $this->getName()), __('Bottom Center', $this->getName()), __('Bottom Right', $this->getName())
        );
        
        // Give the background tiling options human readable titles
        $bg_repeat_titles = array(
            __('Tile horizontal and vertical', $this->getName()), __('Tile horizontal', $this->getName()), 
            __('Tile vertical', $this->getName()), __('No Tiling', $this->getName()),
        );
        
        // Give the corner locations titles
        $corner_location_titles = array(
            __('Top Left', $this->getName()), __('Top Right', $this->getName()), 
            __('Bottom Left', $this->getName()), __('Bottom Right', $this->getName())
        );
        
        // Give the transitions titles
        $bg_transition_titles = array(
            __('-- None (deactivated) --', $this->getName()), __('Random', $this->getName()),
            __('Slide Downward', $this->getName()), __('Slide Upward', $this->getName()), __('Slide to Left', $this->getName()), __('Slide to Right', $this->getName()),
            __('Cover Downward', $this->getName()), __('Cover Upward', $this->getName()), __('Cover to Left', $this->getName()), __('Cover to Right', $this->getName()),
            __('Crossfade', $this->getName()),
        );        
        
        // Grab Custom Post Types
        $custom_post_types         = array();
        $display_custom_post_types = $this->options->display_custom_post_types;
        
        foreach (get_post_types(array('_builtin' => false, 'public' => true), 'objects') as $post_type_key => $post_type) {
            if ($post_type_key !== static::PT_GALLERY)
                $custom_post_types[$post_type_key] = array(
                    'name'    => $post_type->labels->name,
                    'display' => (isset($display_custom_post_types[$post_type_key])) ? $display_custom_post_types[$post_type_key] : true,
                );
        }
        
        // Generate some debug information
        $plugin_version = $this->getVersion();
        $active_plugins = array();
        $mem_peak  = (function_exists('memory_get_peak_usage')) ? memory_get_peak_usage() / 1048576 : 0;
        $mem_usage = (function_exists('memory_get_usage')) ? memory_get_usage() / 1048576 : 0;
        $mem_max   = (int) @ini_get('memory_limit');
        
        foreach (\Pf4wp\Info\PluginInfo::getInfo(true) as $plugin)
            $active_plugins[] = sprintf("'%s' by %s", $plugin['Name'], $plugin['Author']);
            
        $debug_info = array(
            'Generated On'                       => gmdate('D, d M Y H:i:s') . ' GMT',
            $this->getDisplayName() . ' Version' => $plugin_version,
            'PHP Version'                        => PHP_VERSION,
            'Memory Usage'                       => sprintf('%.2f MB Peak, %.2f MB Current, %d MB Max', $mem_peak, $mem_usage, $mem_max),
            'Available PHP Extensions'           => implode(', ', get_loaded_extensions()),
            'Pf4wp Version'                      => PF4WP_VERSION,
            'Pf4wp APC Enabled'                  => (PF4WP_APC) ? 'Yes' : 'No',
            'WordPress Version'                  => $wp_version,
            'WordPress Debug Mode'               => (defined('WP_DEBUG') && WP_DEBUG) ? 'Yes' : 'No',
            'Active WordPress Theme'             => get_current_theme(),
            'Active Wordpress Plugins'           => implode(', ', $active_plugins),
            'Browser'                            => $_SERVER['HTTP_USER_AGENT'],
            'Server'                             => $_SERVER['SERVER_SOFTWARE'],
            'Server OS'                          => php_uname(),
            'Database Version'                   => $wpdb->get_var('SELECT VERSION()'),
        );        
        
        // Template exports:
        $vars = array(
            'nonce'                         => wp_nonce_field('onSettingsMenu', '_nonce', true, false),
            'submit_button'                 => get_submit_button(),
            'galleries'                     => $galleries,
            'overlays'                      => $overlays,
            'background_color'              => get_background_color(),
            'background_size'               => $this->options->background_size,
            'background_scroll'             => $this->options->background_scroll,
            'background_position'           => $this->options->background_position,
            'background_repeat'             => $this->options->background_repeat,
            'background_stretch_vertical'   => $this->options->background_stretch_vertical,
            'background_stretch_horizontal' => $this->options->background_stretch_horizontal,
            'background_opacity'            => $this->options->background_opacity,
            'overlay_opacity'               => $this->options->overlay_opacity,
            'background_transition'         => $this->options->background_transition,
            'transition_speed'              => ((int)$this->options->transition_speed >= 100 && (int)$this->options->transition_speed <= 7500) ? $this->options->transition_speed : 600,
            'change_freq_custom'            => ((int)$this->options->change_freq_custom >= 10) ? $this->options->change_freq_custom : 10,
            'change_freq'                   => $this->options->change_freq,
            'display_on_front_page'         => $this->options->display_on_front_page,
            'display_on_single_post'        => $this->options->display_on_single_post,
            'display_on_single_page'        => $this->options->display_on_single_page,
            'display_on_archive'            => $this->options->display_on_archive,
            'display_on_search'             => $this->options->display_on_search,
            'display_on_error'              => $this->options->display_on_error,
            'custom_post_types'             => $custom_post_types,
            'info_tab'                      => $this->options->info_tab,
            'info_tab_location'             => $this->options->info_tab_location,
            'info_tab_thumb'                => $this->options->info_tab_thumb,
            'info_tab_link'                 => $this->options->info_tab_link,
            'info_tab_desc'                 => $this->options->info_tab_desc,
            'bg_positions'                  => array_combine($this->bg_positions, $bg_position_titles),
            'bg_repeats'                    => array_combine($this->bg_repeats, $bg_repeat_titles),
            'bg_transitions'                => array_combine($this->bg_transitions, $bg_transition_titles),
            'corner_locations'              => array_combine($this->corner_locations, $corner_location_titles),
            'plugin_base_url'               => $this->getPluginUrl(),
            'debug_info'                    => $debug_info,
            'plugin_name'                   => $this->getDisplayName(),
            'plugin_version'                => $plugin_version,
            'plugin_home'                   => \Pf4wp\Info\PluginInfo::getInfo(false, $this->getPluginBaseName(), 'PluginURI'),
            'pin_it_btn'                    => $this->options->pin_it_btn,
            'pin_it_btn_location'           => $this->options->pin_it_btn_location,
        );
        
        $this->template->display('settings.html.twig', $vars);
    }
    
    /**
     * Handles Pre-Galleries Menu functions
     * 
     * Before loading the Galleries Menu, load the list and handle any pending 
     * user actions. This is also shared with onTrashMenuLoad(), due to its 
     * shared code.
     *
     * @see onTrashMenuLoad()
     * @param object $current_screen The current screen object
     */
    public function onGalleriesMenuLoad($current_screen) {
        if (!isset($this->list))
            $this->list = new Lists\Galleries($this);

        // Render any requested iframes (and 'die' afterwards)
        if (isset($_REQUEST['iframe'])) {
            switch (strtolower(trim($_REQUEST['iframe']))) {
                case 'images' :
                    $this->onIframeImages();
                    break;
                    
                case 'edit_image' :
                    $this->onIframeEditImage();
                    break;
            }
        }      
            
        // "Simple" actions
        if (isset($_REQUEST['ids']) && ($action = $this->list->current_action()) !== false) {
            switch (strtolower($action)) {
                case 'restore':
                case 'restore_all':
                    $this->galleries->restoreUserAction(($action == 'restore_all'));
                    break;
                
                case 'trash':
                case 'trash_all':
                    $this->galleries->trashUserAction(($action == 'trash_all'));
                    break;
                    
                case 'delete':
                case 'delete_all':
                    $this->galleries->deleteUserAction(($action == 'delete_all'));
                    break;                    
            }                    
        }
        
        // Edit screen initialization
        if ($this->inEdit() && !$this->list->isTrash()) {
            /* Set the current screen to 'bgm_gallery' - a requirement for 
             * edit form meta boxes for this post type
             */
            set_current_screen(self::PT_GALLERY);
            
            // Override the context help
            $this->getMenu()->getActiveMenu()->context_help = new ContextHelp($this, 'edit');
            
            // Respond to a save edit action (this will not return if the gallery was saved)
            if (isset($_POST['submit']))
                $this->galleries->saveUserAction();
            
            // Add thickbox and other javascripts
            list($js_url, $version, $debug) = $this->getResourceUrl();

            add_thickbox();
            wp_enqueue_script($this->getName() . '-gallery-edit', $js_url . 'gallery_edit' . $debug . '.js', array('jquery', $this->getName() . '-functions'), $version);
            wp_localize_script(
                $this->getName() . '-gallery-edit', 'bgmL10n', array(
                    'warn_delete_all_images' => __('You are about to permanently delete the selected images. Are you sure?', $this->getName()),
                    'warn_delete_image'      => __('You are about to permanently delete this image. Are you sure?', $this->getName()),
                    'l10n_print_after'       => 'try{convertEntities(bgmL10n);}catch(e){};'
                ) 
            );
            
            // Enqueue editor buttons (since WordPress 3.3)
            wp_enqueue_style('editor-buttons');
            
            // Guided Help ("Add Images")
            new PointerAddNewStep2($this->getName());            

            // Set the 'images per page'
            $active_menu                 = $this->getMenu()->getActiveMenu();
            $active_menu->per_page       = 30;
            $active_menu->per_page_label = __('images per page', $this->getName());
            
            // Set the layout two 1 or 2 column width
            add_screen_option('layout_columns', array('max' => 2, 'default' => 2) );
            
            // Perform last-moment meta box registrations a la WordPress
            do_action('add_meta_boxes', self::PT_GALLERY, $this->gallery);
            do_action('add_meta_boxes_' . self::PT_GALLERY, $this->gallery);
            
            do_action('do_meta_boxes', self::PT_GALLERY, 'normal', $this->gallery);
            do_action('do_meta_boxes', self::PT_GALLERY, 'advanced', $this->gallery);
            do_action('do_meta_boxes', self::PT_GALLERY, 'side', $this->gallery);
        }
    }
    
    /**
     * Galleries Menu
     *
     * This is also shared with onTrashMenu(), due to its shared code
     *
     * @see onTrashMenu()
     */
    public function onGalleriesMenu($data, $per_page)
    {
        // Basic sanity check
        if (!isset($this->list) || !isset($this->galleries))
            return; 
            
        // Show the editor instead of the list
        if ($this->inEdit()) {
            $this->editGallery($per_page);
            return;
        }
            
        $this->list->setPerPage($per_page);
        $this->list->prepare_items();
        
        $vars = array(
            'trash' => $this->list->isTrash(),
            'list'  => $this->list->render(),
        );
        
        $this->template->display('galleries.html.twig', $vars);
    }
    
    /**
     * Handles pre-Trash Menu functions
     *
     * Before loading the Trash Menu, load the list with `Trash` enabled.
     *
     * @see onGalleriesMenuLoad()
     * @param object $current_screen Object containing the current screen (Wordpress)
     */
    public function onTrashMenuLoad($current_screen)
    {
        $this->list = new Lists\Galleries($this, true); // !!
        
        $this->onGalleriesMenuLoad($current_screen);
        
        // Empty Trash action
        if (isset($_POST['empty_trash']))
            $this->galleries->emptyTrashUserAction();        
    }
    
    /**
     * Trash Menu
     *
     * @see onGalleriesMenu()
     */
    public function onTrashMenu($data, $per_page)
    {
        $this->onGalleriesMenu($data, $per_page);
    }
    
    /**
     * Import Menu Loader
     *
     * This will check the form response for a valid import job request, and 
     * performs it accordingly.
     */
    public function onImportMenuLoad($current_screen)
    {
        // Check if there's a valid 'run_import_job'
        if (isset($_REQUEST['run_import_job']) && isset($_REQUEST['nonce'])) {
            if (!wp_verify_nonce($_REQUEST['nonce'], $this->getName() . '_import_' . $_REQUEST['run_import_job']))
                wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
                
            $importers = $this->getImporters();
            $importer  = $_REQUEST['run_import_job'];
            
            if (array_key_exists($importer, $importers)) {
                $class = $importers[$importer]['class'];
                
                // Run import job
                if (is_callable($class . '::import'))
                    $class::import($this);
            }
        }

        list($js_url, $version, $debug) = $this->getResourceUrl();
        
        add_thickbox();
        wp_enqueue_script($this->getName() . '-import', $js_url . 'import' . $debug . '.js', array('jquery', $this->getName() . '-functions'), $version);
    }
    
    /**
     * Import Menu
     */
    public function onImportMenu($data)
    {
        $importers      = $this->getImporters();
        $importer       = '';
        $pre_import     = '';
        $import_job_src = '';
        
        // If the form was submitted...
        if (isset($_REQUEST['importer']) && isset($_REQUEST['_nonce'])) {
            if (!wp_verify_nonce($_REQUEST['_nonce'], 'onImportMenu'))
                wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
            
            $importer = $_REQUEST['importer'];
            
            if (!array_key_exists($importer, $importers)) {
                $importer = ''; // Invalid importer specified, ignore
            } else {
                // Obtain any pre-import information from the user, if required
                $class = $importers[$importer]['class'];
                
                if (is_callable($class . '::preImport'))
                    $pre_import = $class::preImport($this);
                
                // Scrub REQUEST variables, and pass them on to the import job source args
                $args = array();
                
                foreach ($_REQUEST as $post_key => $post_val) {
                    $post_key = preg_replace('#_desc$#', '', $post_key); // Both regular class names and descriptions are ignored
                    
                    if (!array_key_exists($post_key, $importers) &&
                        !in_array($post_key, array('_nonce', 'page', 'sub', 'submit', 'importer', 'pre_import_done', '_wp_http_referer')))
                        $args[$post_key] = $post_val;
                }

                // Include a nonce in the import jobs source
                $import_job_src = add_query_arg(array_merge($args, array('run_import_job' => $importer, 'nonce' => wp_create_nonce($this->getName() . '_import_' . $importer))));
            }
        }
        
        $vars = array(
            'nonce'           => wp_nonce_field('onImportMenu', '_nonce', true, false),
            'submit_button'   => get_submit_button(__('Continue Import', $this->getName())),
            'importers'       => $importers,
            'importer'        => $importer,
            'show_pre_import' => (!empty($importer) && !empty($pre_import)),
            'pre_import'      => $pre_import,
            'run_import'      => (!empty($importer) && empty($pre_import)),
            'import_job_src'  => $import_job_src,
        );
        
        $this->template->display('import.html.twig', $vars);
    }
       
    /**
     * Edit an existing or new gallery
     *
     * This will render the edit form, in place of the gallery list, unless
     * the user does not have the privileges to edit any theme options.
     *
     * @filter image_upload_iframe_src, myatu_bgm_media_buttons
     * @see onGalleriesMenu()
     */
    public function editGallery($per_page)
    {
        // Get the main meta box output (for Twig)
        ob_start();
        do_meta_boxes(self::PT_GALLERY, 'normal', $this->gallery);
        do_meta_boxes(self::PT_GALLERY, 'advanced', $this->gallery);
        $meta_boxes_main = ob_get_clean();
        
        // Get the side meta box output (for Twig)
        ob_start();
        do_meta_boxes(self::PT_GALLERY, 'side', $this->gallery);
        $meta_boxes_side = ob_get_clean();
        
        // Check if we start by displaying the right-side column;
        $screen  = get_current_screen();
        $columns = (int)get_user_option('screen_layout_'.$screen->id);
        if ($columns == 0)
            $columns = 2;
        
        // Image upload button iframe src (href)
        $image_media_library = add_query_arg(array('post_id' => ($this->gallery) ? $this->gallery->ID : '', 'type' => 'image'), admin_url('media-upload.php'));
        $image_media_library = apply_filters('image_upload_iframe_src', $image_media_library); // As used by WordPress
        
        $media_buttons['image']['id']    = 'add_image';
        $media_buttons['image']['url']   = add_query_arg('filter', Filter\MediaLibrary::FILTER, $image_media_library); // Add filter
        $media_buttons['image']['icon']  = admin_url('images/media-button-image.gif');
        $media_buttons['image']['title'] = __('Add an Image', $this->getName());
        
        // Allow additional media buttons to be specified
        $media_buttons = apply_filters('myatu_bgm_media_buttons', $media_buttons);
        
        // Ensure that media buttons have a `TB_iframe` as the last query arg
        foreach ($media_buttons as $media_button_key => $media_button_value) {
            if (isset($media_button_value['url']))
                $media_buttons[$media_button_key]['url'] = add_query_arg('TB_iframe', true, remove_query_arg('TB_iframe', $media_buttons[$media_button_key]['url']));
        }
        
        // Iframes
        $images_iframe_src = add_query_arg(array('iframe' => 'images', 'edit' => $this->gallery->ID, 'orderby' => false, 'order' => false, 'pp' => $per_page, 'paged' => false));
        $image_edit_src    = add_query_arg(array('iframe' => 'edit_image', 'edit' => false, 'orderby' => false, 'order' => false, 'post_id' => $this->gallery->ID, 'filter' => Filter\MediaLibrary::FILTER));
        
        $vars = array(
            'has_right_sidebar' => ($columns == 2) ? 'has-right-sidebar' : '',
            'nonce'             => wp_nonce_field(self::NONCE_EDIT_GALLERY . $this->gallery->ID, '_nonce', true, false),
            'nonce_meta_order'  => wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false, false),
            'nonce_meta_clsd'   => wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false, false),
            'images_iframe_src' => $images_iframe_src,  // iframe source
            'image_edit_src'    => $image_edit_src,     // iframe source
            'gallery'           => $this->gallery,
            'post_type'         => self::PT_GALLERY,
            'meta_boxes_main'   => $meta_boxes_main,
            'meta_boxes_side'   => $meta_boxes_side,
            'media_buttons'     => $media_buttons,
            'is_new'            => $this->gallery->post_status != 'auto-draft',
            'edit'              => $this->gallery->ID,
            'images_per_page'   => $per_page,
            'images_count'      => $this->images->getCount($this->gallery->ID),
            'images_hash'       => $this->images->getHash($this->gallery->ID),
            'img_large_loader'  => $this->getPluginUrl() . static::DIR_IMAGES . 'large_loader.gif',
            'image_del_is_perm' => (!EMPTY_TRASH_DAYS || !MEDIA_TRASH) ? true : false,
        );
        
        $this->template->display('edit_gallery.html.twig', $vars);
    }
    
    /** Images Iframe */
    public function onIframeImages()
    {
        if (!isset($this->gallery->ID))
            die; // Something didn't go quite right
        
        // Only if Javascript is disabled will we get here, which adds a image to the gallery directly
        if (!empty($_POST) && isset($_POST['_nonce'])) {
            if (!wp_verify_nonce($_POST['_nonce'], 'image-upload'))
                wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
            
            // Check if there's a valid image, and if so, let the Media Library handle the upload
            if (!empty($_FILES) && $_FILES['upload_file']['error'] == 0 && file_is_valid_image($_FILES['upload_file']['tmp_name']))
                media_handle_upload('upload_file', $this->gallery->ID);
        }
        
        iframe_header();
        
        $items_per_page = isset($_GET['pp']) ? $_GET['pp'] : 30;
        $page_num       = isset($_GET['paged']) ? $_GET['paged'] : 1;
        
        // Grab the total amount of items (images) and figure out how many pages that is
        $total_items = $this->images->getCount($this->gallery->ID);
        if (($total_pages = ceil($total_items / $items_per_page)) < 1)
            $total_pages = 1;        
        
        // Get a valid page number
        if ($page_num > $total_pages) {
            $page_num = $total_pages;
        } else if ($page_num < 1) {
            $page_num = 1;
        }
        
        // Grab the images
        $images = $this->images->get($this->gallery->ID, 
            array(
                'orderby'     => 'date',
                'order'       => 'desc',
                'numberposts' => $items_per_page,
                'offset'      => ($page_num-1) * $items_per_page,
            )
        );
        
        // The page links (for non-JS browsers)
        $page_links = paginate_links(array(
            'base'         => add_query_arg('paged', '%#%'),
            'format'       => '',
            'prev_text'    => __('&laquo;'),
            'next_text'    => __('&raquo;'),
            'total'        => $total_pages,
            'current'      => $page_num,
        ));      
        
        $vars = array(
            'images'            => $images,
            'current_page'      => $page_num,
            'image_edit_img'    => includes_url('js/tinymce/plugins/wpeditimage/img/image.png'),    // Use familiar images
            'image_delete_img'  => includes_url('js/tinymce/plugins/wpeditimage/img/delete.png'),
            /* For non-JS: */
            'page_links'        => $page_links,
            'nonce'             => wp_nonce_field('image-upload', '_nonce', false, false),
        );
        
        $this->template->display('gallery_image.html.twig', $vars);

        iframe_footer();
        die();
    }
    
    /** Edit Image iframe **/
    public function onIframeEditImage()
    {
        if (!isset($_GET['id']))
            die; // How did you get here? Hmm!
        
        $id       = (int)$_GET['id'];
        $post     = get_post($id);
        $vars     = array();
        $did_save = false;
        
        // Handle save request
        if (isset($_REQUEST['save'])) {
            $errors   = media_upload_form_handler();
            $did_save = true;
        }
        
        // Queue additional scripts and styles
        wp_enqueue_script('image-edit');
        wp_enqueue_style('media');
        
        // Send iframe header
        iframe_header();
        
        if ($id == 0 || $post == false || $post->post_type != 'attachment' || $post->post_status == 'trash') {
            // Invalid ID or item was deleted
            $vars = array('deleted'=>true);
        } else {
            $vars = array(
                'did_save'   => $did_save,
                'has_error'  => isset($errors[$id]),
                'nonce'      => wp_nonce_field('media-form', '_wpnonce', false, false), // Same as used by media_upload_form_handler()
                'media_item' => get_media_item($id, array('toggle'=>false, 'show_title'=>false, 'send'=>false, 'delete'=>false, 'errors'=>(isset($errors[$id]) ? $errors[$id] : null))),
                'submit'     => get_submit_button(__( 'Save all changes'), 'button', 'save', false),
            );
        }
        
        // Render template
        $this->template->display('gallery_edit_image.html.twig', $vars);

        // Send iframe footer and then 'die'.
        iframe_footer();
        die();
    }
    
    /* ----------- Public ----------- */
    
    /**
     * Called on wp_head, rendering the stylesheet as late as possible
     *
     * This will provide a basic background image and colors, along with 
     * tiling options.
     *
     * @filter myatu_bgm_custom_styles, myatu_bgm_active_gallery
     */
    public function onWpHead()
    {
        if (is_admin() || !$this->canDisplayBackground())
            return;
        
        $style      = '';
        $gallery_id = apply_filters('myatu_bgm_active_gallery', $this->options->active_gallery);
        
        // Only add a background image here if we have a valid gallery and we're not using a full-screen image
        if ($this->getGallery($gallery_id) != false && $this->options->background_size != static::BS_FULL) {
            $random_image = $this->getRandomImage();
            
            if ($random_image['url'])
                $style .= sprintf('background-image: url(\'%s\');', $random_image['url']);
            
            // Set the background position
            $bg_position = ($this->options->background_position) ? $this->options->background_position : $this->bg_positions[0];
            $bg_position = explode('-', $bg_position);
            
            $style .= sprintf('background-position: %s %s;', $bg_position[0], $bg_position[1]);
            
            // Set the background tiling          
            $style .= sprintf('background-repeat: %s;', ($this->options->background_repeat) ? $this->options->background_repeat : $this->bg_repeats[0]);
            
            // Set background scrolling
            $style .= sprintf('background-attachment: %s;', ($this->options->background_scroll) ? $this->options->background_scroll : static::BST_SCROLL);
            
            // Set background sizing (stretching)
            if ($this->options->background_stretch_vertical || $this->options->background_stretch_horizontal) {
                $style .= sprintf('background-size: %s %s;',
                    ($this->options->background_stretch_horizontal) ? '100%' : 'auto',
                    ($this->options->background_stretch_vertical) ? '100%' : 'auto'
                );
            }
        } else {
            $style .= sprintf('background-image: none !important;');
        }
            
        if ($color = get_background_color())
            $style .= sprintf('background-color: #%s;', $color);
            
        $custom_styles = apply_filters('myatu_bgm_custom_styles', $gallery_id, '');
        
        if ($style || $custom_styles )
            printf('<style type="text/css" media="screen">body { %s } %s</style>'.PHP_EOL, $style, $custom_styles);
    }
    
    /**
     * Load public scripts
     *
     * @filter myatu_bgm_active_gallery
     */
    public function onPublicScripts()
    {
        if (!$this->canDisplayBackground())
            return;
        
        // If image is selected per browser session, set a cookie now (before headers are sent)
        if ($this->options->change_freq == static::CF_SESSION)
            $this->getRandomImage();

        /* Only load the scripts if: 
         * - there's custom change frequency
         * - the background is full screen 
         * - or, there's an info tab with a short description
         */
        if ($this->options->change_freq != static::CF_CUSTOM && 
            $this->options->background_size != static::BS_FULL && 
            !($this->options->info_tab && $this->options->info_tab_desc))
            return;
        
        // Enqueue jQuery and base functions
        list($js_url, $version, $debug) = $this->getResourceUrl();
        
        wp_enqueue_script('jquery');
        wp_enqueue_script($this->getName() . '-functions', $js_url . 'functions' . $debug . '.js', array('jquery'), $version);
        
        // If the info tab is enabled along with the short description, also include qTip2
        if ($this->options->info_tab && $this->options->info_tab_desc)
            wp_enqueue_script('jquery.qtip', $js_url . 'vendor/qtip/jquery.qtip.min.js', array('jquery'), $version);
        
        // Don't worry about the rest if the change frequency isn't custom or there's no short description for the info tab
        if ($this->options->change_freq != static::CF_CUSTOM && !$this->options->info_tab_desc)
            return;
        
        // (the rest)
        wp_enqueue_script($this->getName() . '-pub', $js_url . 'pub' . $debug . '.js', array($this->getName() . '-functions'), $version);
        
        // Make the change frequency available to JavaScript
        $gallery_id = apply_filters('myatu_bgm_active_gallery', $this->options->active_gallery);
        if ($this->options->change_freq == static::CF_CUSTOM && $this->getGallery($gallery_id) != false) {
            $change_freq = ($this->options->change_freq_custom) ? $this->options->change_freq_custom : 10;
        } else {
            $change_freq = 0; // Disabled
        }
        
        // Spit out variables for JavaScript to use
        wp_localize_script(
            $this->getName() . '-pub', 'background_manager_vars', array(
                'change_freq'    => $change_freq,
                'active_gallery' => $gallery_id,
                'is_fullsize'    => ($this->options->background_size == static::BS_FULL) ? 'true' : 'false',
            ) 
        );
    }    
    
    /**
     * Load public styles
     *
     * @filter myatu_bgm_active_overlay
     */
    public function onPublicStyles()
    {
        if (!$this->canDisplayBackground())
            return;
        
        list($css_url, $version, $debug) = $this->getResourceUrl('css');
        
        // Default CSS for the public side
        wp_enqueue_style($this->getName() . '-pub', $css_url . 'pub' . $debug . '.css', false, $version);
        
        // qTip2 style, if required
        if ($this->options->info_tab && $this->options->info_tab_desc)
            wp_enqueue_style('jquery.qtip', $css_url . 'vendor/jquery.qtip.min.css', false, $version);
        
        $style   = '';
        $overlay = apply_filters('myatu_bgm_active_overlay', $this->options->active_overlay);
        
        // The image for the overlay, as CSS embedded data
        if ($overlay && ($data = Helpers::embedDataUri($overlay, false, (defined('WP_DEBUG') && WP_DEBUG))) != false) {
            $opacity = '';
            if ($this->options->overlay_opacity < 100)
                $opacity = sprintf('-moz-opacity:.%s; filter:alpha(opacity=%1$s); opacity:.%1$s', str_pad($this->options->overlay_opacity, 2, '0', STR_PAD_LEFT));
            
            $style .= sprintf('#myatu_bgm_overlay{background:url(\'%s\') repeat fixed top left transparent; %s}', $data, $opacity);
        }
        
        // The info icon
        if ($this->options->info_tab)
            $style .= sprintf('#myatu_bgm_info_tab{%s}', $this->getCornerStyle($this->options->info_tab_location, 5, 5));
        
        // The "Pin It" button
        if ($this->options->pin_it_btn) {
            // Horizontal spacer depends whether the info tab is shown as well
            $hspacer = ($this->options->info_tab && ($this->options->info_tab_location == $this->options->pin_it_btn_location)) ? 35 : 10;
            
            $style .= sprintf('#myatu_bgm_pin_it_btn{%s}', $this->getCornerStyle($this->options->pin_it_btn_location, $hspacer, 5));
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
     *
     * @filter myatu_bgm_active_overlay
     */
    public function onPublicFooter()
    {
        if (!$this->canDisplayBackground())
            return;
            
        $overlay    = apply_filters('myatu_bgm_active_overlay', $this->options->active_overlay);
        $gallery_id = apply_filters('myatu_bgm_active_gallery', $this->options->active_gallery);
        
        $vars = array(
            'has_info_tab'   => $this->options->info_tab && ($this->getGallery($gallery_id) != false), // Only display if we have a valid gallery
            'info_tab_thumb' => $this->options->info_tab_thumb,
            'info_tab_link'  => $this->options->info_tab_link,
            'info_tab_desc'  => $this->options->info_tab_desc,
            'has_pin_it_btn' => $this->options->pin_it_btn  && ($this->getGallery($gallery_id) != false),
            'has_overlay'    => ($overlay != false),
            'opacity'        => str_pad($this->options->background_opacity, 2, '0', STR_PAD_LEFT), // Only available to full size background
            'is_fullsize'    => $this->options->background_size == static::BS_FULL,
            'random_image'   => $this->getRandomImage(),
            'permalink'      => get_site_url() . $_SERVER['REQUEST_URI'],
        );
        
        $this->template->display('pub_footer.html.twig', $vars);
    }
}