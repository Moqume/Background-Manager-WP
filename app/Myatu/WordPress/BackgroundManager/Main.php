<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager;

use Pf4wp\Notification\AdminNotice;
use Pf4wp\Info\PluginInfo;
use Pf4wp\Common\Helpers;

/**
 * The main class for the BackgroundManager
 *
 * It is the controller for all other functionality of BackgroundManager
 *
 * @author Mike Green <myatus@gmail.com>
 * @version 0.0.0.2
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
    
    /* Enable public-side Ajax */
    public $public_ajax = true;
    
    /* Possible background positions */
    private $bg_positions = array('top-left', 'top-center', 'top-right', 'center-left', 'center-center', 'center-right', 'bottom-left', 'bottom-center', 'bottom-right');
    
    /* Possible background tiling options */
    private $bg_repeats = array('repeat', 'repeat-x', 'repeat-y', 'no-repeat');
    
    /** Instance containing current gallery being edited (if any) */
    private $gallery = null;
    
    /** Instance to a List\Galleries - @see onGalleriesMenu(), onTrashMenu() */
    private $list;
    
    /** The link to edit Galleries - @see onBuildMenu() */
    private $edit_gallery_link = '';
    
    /** Cached response of inEdit() - @see inEdit() */
    private $in_edit;
    
    /** Instance of Photos - @see onAdminInit() */
    public $photos;
    
    /** Instance of Galleries - @see onAdminInit() */
    public $galleries;
    
    /** The default options (saved in the WP database) */
    protected $default_options = array();
    
    
    /* ----------- Helpers ----------- */

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
        
        if (isset($this->in_edit))
            return ($this->in_edit);
        
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
        
        $this->in_edit = $result; // Cache response (non-persistent)
        
        return $result;
    }
    
    /**
     * Helper to obtain the resource directory.
     *
     * @param bool $for_css If set to `true` obtain the resource directory for CSS, or for JS otherwise (default)
     * @return array Array containing Directory, Version and Debug string.
     */
    public function getResourceDir($for_css = false)
    {
        $dir = $this->getPluginUrl() . 'resources/' . (($for_css) ? 'css/' : 'js/');
        $version = PluginInfo::getInfo(true, $this->getPluginBaseName(), 'Version');
        $debug   = (defined('WP_DEBUG') && WP_DEBUG) ? '.dev' : '';
        
        return array($dir, $version, $debug);
    }
    
    /**
     * Helper that obtains a random image, including all details about it
     *
     * @param int $gallery_id Gallery ID to retrieve the image from
     * @param string $previous_image The URL of the previous image, if any (to avoid duplicates)
     * @return array Array containing Image ID, URL, Alt Text and Title.
     */
    public function getRandomImage($gallery_id, $previous_image = '')
    {
        $bailout      = 0; // Loop bailout
        $random_id    = 0;
        $random_image = '';
        $alt          = '';
        
        if ($gallery_id && ($gallery = get_post($gallery_id)) != false && $gallery->post_status != 'trash') {
            if (!isset($this->photos))
                $this->photos = new Photos($this);
            
            $only_one_available = ($this->photos->getCount($gallery_id) == 1);
            
            while (true) {
                $bailout++;
                
                $random_id    = $this->photos->getRandomPhotoId($gallery_id);
                $random_image = wp_get_attachment_image_src($random_id, 'large');
                
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
            
            // Fetch additional details about the image
            if ($random_image)
                $alt = get_post_meta($random_id, '_wp_attachment_image_alt', true);
        }
        
        return array($random_id, $random_image, $alt);
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
        
        // Register post types
        register_post_type(self::PT_GALLERY, array(
            'labels' => array(
                'name'          => __('Background Photo Sets', $this->getName()),
                'singular_name' => __('Background Photo Set', $this->getName()),
            ),
            'public'              => true,             // Make it available in the Admin 'attach' feature of the Media Library
            'exclude_from_search' => true,             // But hide it from the front-end search...
            'publicly_queryable'  => false,            // ...and front-end query (display)
            'show_ui'             => false,            // Don't generate its own UI in the Admin
            'hierarchical'        => false,
            'rewrite'             => false,
            'query_var'           => false,
            'supports'            => array('title'),   // In case onGetEditPostLink() borks
        ));
        
        /** @TODO: Attachement support */
        add_theme_support('custom-background');
        
        // Check if there are any on-demand filters requested
        $filters = array();
        if (isset($_REQUEST['filter']))
            $filters = explode(',', $_REQUEST['filter']);
        
        // And check the referer arguments as well if this is a POST request
        if (!empty($_POST) && isset($_SERVER['HTTP_REFERER'])) {
            $referer_args = explode('&', ltrim(strstr($_SERVER['HTTP_REFERER'], '?'), '?'));
            foreach ($referer_args as $referer_arg)
                if (!empty($referer_arg)) {
                    list($arg_name, $arg_value) = explode('=', $referer_arg);
                    if ($arg_name == 'filter') {
                        $filters = array_merge($filters, explode(',', $arg_value));
                        break;
                    }
                }
        }
        
        foreach ($filters as $filter)
            switch ($filter) {
                case Filter\MediaLibrary::FILTER :
                    new Filter\MediaLibrary($this);
                    break;
            }
    }
    
    /**
     * Initialize the Admin pages
     */
    public function onAdminInit()
    {
        // Create an public instances
        $this->galleries = new Galleries($this);
        $this->photos = new Photos($this);
        
        // Initialize meta boxes
        new Meta\Submit($this);
        new Meta\Stylesheet($this);
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
        // as onAdminInit does not get called before Ajax requests, set up the Photos instance if needed
        if (!isset($this->photos))
            $this->photos = new Photos($this);
        
        switch ($function) {
            case 'photo_ids' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;
                
                $id = (int)$data;
                
                // This returns the array as an object, where the object property names are the values (ids) of the photos
                $this->ajaxResponse((object)array_flip($this->photos->getAllPhotoIds($id)));
                break;
            
            case 'photo_count' :
                $id = (int)$data;
        
                $this->ajaxResponse($this->photos->getCount($id));
                break;
            
            case 'photos_hash' :
                $id = (int)$data;
                
                $this->ajaxResponse($this->photos->getHash($id));
                break;
                
            case 'paginate_links' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;
                    
                $id       = (int)$data['id'];
                $per_page = (int)$data['pp'];
                $base     = $data['base'];
                $current  = (int)$data['current'];
                
                if ($current == 0)
                    $current = 1;
                    
                $page_links = paginate_links( array(
                    'base'         => add_query_arg('paged', '%#%', $base),
                    'format'       => '',
                    'prev_text'    => __('&laquo;'),
                    'next_text'    => __('&raquo;'),
                    'total'        => ceil($this->photos->getCount($id) / $per_page),
                    'current'      => $current,
                ));
                
                $this->ajaxResponse($page_links);
                
                break;
                
            case 'delete_photos' : // PRIVILEGED
                if (!current_user_can('edit_theme_options'))
                    return;
                    
                $ids = explode(',', $data);
                
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
                
            case 'random_image' :
                list($random_id, $random_image, $alt) = $this->getRandomImage($this->options->active_gallery, $data);
                
                $this->ajaxResponse((object)array('url'=>$random_image, 'alt'=>$alt), empty($random_image));
                
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
     * Build the menu
     */
    public function onBuildMenu()
    {
        $mymenu = new \Pf4wp\Menu\SubHeadMenu($this->getName());
        
        // Add settings menu
        $main_menu = $mymenu->addMenu(__('Background', $this->getName()), array($this, 'onSettingsMenu'));
        $main_menu->page_title = $this->getDisplayName();
        $main_menu->large_icon = 'icon-themes';
        
        // Add photo sets (galleries) submenu
        $gallery_menu = $mymenu->addSubmenu(__('Photo Sets', $this->getName()), array($this, 'onGalleriesMenu'));
        $gallery_menu->count = $this->getGalleryCount();
        if (!$this->inEdit())
            $gallery_menu->per_page = 15; // Add a `per page` screen setting
        
        // If there are items in the Trash, display this menu too:
        if ($count = $this->getGalleryCount(false)) {
            $trash_menu = $mymenu->addSubmenu(__('Trash', $this->getName()), array($this, 'onTrashMenu'));
            $trash_menu->count = $count;
            $trash_menu->per_page = 15;
        }

        // Make it appear under WordPress' `Appearance` (theme_options)
        $mymenu->setType(\Pf4wp\Menu\MenuEntry::MT_THEMES);
        
        // Give the 'Home' a different title
        $mymenu->setHomeTitle(__('Settings', $this->getName()));

        // Set an edit link
        $this->edit_gallery_link = add_query_arg(
            array(
                \Pf4wp\Menu\CombinedMenu::SUBMENU_ID => $gallery_menu->getSlug(),
                'page' => $gallery_menu->getSlug(true),
                'edit' => 'new',
            ),
            menu_page_url('theme_options', false)
        );
        
        // Add an 'Add New Photo Set' link
        if (($this->inEdit() && $this->gallery->post_status != 'auto-draft') || (($active_menu = $mymenu->getActiveMenu()) == false) || $active_menu != $gallery_menu) {
            // Replace existing main page title with one that contains a link
            $main_menu->page_title = sprintf(
                '%s <a class="add-new-h2" href="%s">%s</a>',
                $main_menu->page_title,
                esc_url($this->edit_gallery_link),
                __('Add New Photo Set', $this->getName())
            );
        }
        
        return $mymenu;
    }
    
    /**
     * Loads Base Admin Scripts
     */
    public function onAdminScripts()
    {
        list($js_dir, $version, $debug) = $this->getResourceDir();

        wp_enqueue_script('post');
        wp_enqueue_script('media-upload');
        wp_enqueue_script($this->getName() . '-functions', $js_dir . 'functions' . $debug . '.js', array('jquery'), $version);
    }
    
    /**
     * Load Admin CSS
     */
    public function onAdminStyles()
    {
        list($css_dir, $version, $debug) = $this->getResourceDir(true);
        
        wp_enqueue_style($this->getName() . '-admin', $css_dir . 'admin.css', false, $version);
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
        list($js_dir, $version, $debug) = $this->getResourceDir();

        wp_enqueue_script('farbtastic');        
		wp_enqueue_style('farbtastic');
        wp_enqueue_script($this->getName() . '-settings', $js_dir . 'settings' . $debug . '.js', array($this->getName() . '-functions'), $version);        
        
        // Save settings if POST is set
        if (!empty($_POST)) {
            if (!wp_verify_nonce($_POST['_nonce'], 'onSettingsMenu'))
                wp_die(__('You do not have permission to do that [nonce].', $this->getName()));
            
            $this->options->active_gallery      = (int)$_POST['active_gallery'];            
            $this->options->change_freq         = (in_array($_POST['change_freq'], array(static::CF_SESSION, static::CF_LOAD, static::CF_CUSTOM))) ? $_POST['change_freq'] : 'session';
            $this->options->change_freq_custom  = (int)$_POST['change_freq_custom'];
            $this->options->background_position = (in_array($_POST['background_position'], $this->bg_positions)) ? $_POST['background_position'] : $this->bg_positions[0];
            $this->options->background_repeat   = (in_array($_POST['background_repeat'], $this->bg_repeats)) ? $_POST['background_repeat'] : $this->bg_repeats[0];
            
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
        // Generate a list of galleries, including a default of "None"
        $galleries = array(array(
            'id' => 0, 
            'name' => __('-- None (deactivated) --', $this->getName()), 
            'selected' => ($this->options->active_gallery == false),
        ));
        
        $gallery_posts = get_posts(array(
            'orderby' => 'title',
            'order' => 'ASC',
            'numberposts' => 0, 
            'post_type' => \Myatu\WordPress\BackgroundManager\Main::PT_GALLERY)
        );
        
        foreach ($gallery_posts as $gallery_post) {
            // Truncate the string, if neccesary
            list($gallery_name) = explode("\n", wordwrap($gallery_post->post_title, 55));
            if (strlen($gallery_name) < strlen($gallery_post->post_title))
                $gallery_name .= ' ...';
                
            $galleries[] = array(
                'id' => $gallery_post->ID,
                'name' => $gallery_name,
                'selected' => ($this->options->active_gallery == $gallery_post->ID),
            );
        }
        
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
        
        $vars = array(
            'nonce'               => wp_nonce_field('onSettingsMenu', '_nonce', true, false),
            'submit_button'       => get_submit_button(),
            'galleries'           => $galleries,
            'background_color'    => get_background_color(),
            'background_position' => ($this->options->background_position) ? $this->options->background_position : $this->bg_positions[0],
            'background_repeat'   => ($this->options->background_repeat) ? $this->options->background_repeat : $this->bg_repeats[0],
            'change_freq_custom'  => $this->options->change_freq_custom,
            'change_freq'         => ($this->options->change_freq) ? $this->options->change_freq : static::CF_LOAD,
            'bg_positions'        => array_combine($this->bg_positions, $bg_position_titles),
            'bg_repeats'          => array_combine($this->bg_repeats, $bg_repeat_titles),
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
                case 'photos' :
                    $this->onIframePhotos();
                    break;
                    
                case 'edit_photo' :
                    $this->onIframeEditPhoto();
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
            
            // Respond to a save edit action (this will not return if the gallery was saved)
            if (isset($_POST['submit']))
                $this->galleries->saveUserAction();
            
            // Add thickbox and other javascripts
            list($js_dir, $version, $debug) = $this->getResourceDir();

            add_thickbox();
            wp_enqueue_script($this->getName() . '-gallery-edit', $js_dir . 'gallery_edit' . $debug . '.js', array('jquery', $this->getName() . '-functions'), $version);
            wp_localize_script(
                $this->getName() . '-gallery-edit', 'bgmL10n', array(
                    'warn_delete_all_photos' => __('You are about to permanently delete the selected photos. Are you sure?', $this->getName()),
                    'warn_delete_photo' => __('You are about to permanently delete this photo. Are you sure?', $this->getName()),
                    'l10n_print_after' => 'try{convertEntities(bgmL10n);}catch(e){};'
                ) 
            );
            
            // Enqueue editor buttons (since WordPress 3.3)
            wp_enqueue_style('editor-buttons');

            // Set the 'photos per page'
            $active_menu                 = $this->getMenu()->getActiveMenu();
            $active_menu->per_page       = 30;
            $active_menu->per_page_label = __('photos per page', $this->getName());
            
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
    public function onTrashMenuLoad($current_screen) {
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
     * Edit an existing or new gallery
     *
     * This will render the edit form, in place of the gallery list, unless
     * the user does not have the privileges to edit any theme options.
     *
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
        $photos_iframe_src = add_query_arg(array('iframe' => 'photos', 'edit' => $this->gallery->ID, 'orderby' => false, 'order' => false, 'pp' => $per_page, 'paged' => false));
        $photo_edit_src    = add_query_arg(array('iframe' => 'edit_photo', 'edit' => false, 'orderby' => false, 'order' => false, 'post_id' => 0, 'filter' => Filter\MediaLibrary::FILTER));
        
        $vars = array(
            'has_right_sidebar' => ($columns == 2) ? 'has-right-sidebar' : '',
            'nonce'             => wp_nonce_field(self::NONCE_EDIT_GALLERY . $this->gallery->ID, '_nonce', true, false),
            'nonce_meta_order'  => wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false, false),
            'nonce_meta_clsd'   => wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false, false),
            'photos_iframe_src' => $photos_iframe_src,  // iframe source
            'photo_edit_src'    => $photo_edit_src,     // iframe source
            'gallery'           => $this->gallery,
            'post_type'         => self::PT_GALLERY,
            'meta_boxes_main'   => $meta_boxes_main,
            'meta_boxes_side'   => $meta_boxes_side,
            'media_buttons'     => $media_buttons,
            'is_new'            => $this->gallery->post_status != 'auto-draft',
            'edit'              => $this->gallery->ID,
            'photos_per_page'   => $per_page,
            'photos_count'      => $this->photos->getCount($this->gallery->ID),
            'photos_hash'       => $this->photos->getHash($this->gallery->ID),
            'img_large_loader'  => $this->getPluginUrl() . 'resources/images/large_loader.gif',
            'photo_del_is_perm' => (!EMPTY_TRASH_DAYS || !MEDIA_TRASH) ? true : false,
        );
        
        $this->template->display('edit_gallery.html.twig', $vars);
    }
    
    /** Photos Iframe */
    public function onIframePhotos()
    {
        if (!isset($this->gallery->ID))
            die; // Something didn't go quite right
        
        iframe_header();
        
        $items_per_page = isset($_GET['pp']) ? $_GET['pp'] : 30;
        $page_num       = isset($_GET['paged']) ? $_GET['paged'] : 1;
        
        // Grab the total amount of items (photos) and figure out how many pages that is
        $total_items = $this->photos->getCount($this->gallery->ID);
        if (($total_pages = ceil($total_items / $items_per_page)) < 1)
            $total_pages = 1;        
        
        // Get a valid page number
        if ($page_num > $total_pages) {
            $page_num = $total_pages;
        } else if ($page_num < 1) {
            $page_num = 1;
        }
        
        // Grab the photos
        $photos = $this->photos->get($this->gallery->ID, 
            array(
                'orderby'     => 'date',
                'order'       => 'desc',
                'numberposts' => $items_per_page,
                'offset'      => ($page_num-1) * $items_per_page,
            )
        );
        
        $vars = array(
            'photos'            => $photos,
            'current_page'      => $page_num,
            'photo_edit_img'    => includes_url('js/tinymce/plugins/wpeditimage/img/image.png'),    // Use familiar images
            'photo_delete_img'  => includes_url('js/tinymce/plugins/wpeditimage/img/delete.png'),
        );
        
        $this->template->display('gallery_photo.html.twig', $vars);

        iframe_footer();
        die();
    }
    
    /** Edit Photo iframe **/
    public function onIframeEditPhoto()
    {
        if (!isset($_GET['id']))
            die; // How did you get here? Hmm!
        
        // Handle save request
        if (isset($_REQUEST['save']))
            $errors = media_upload_form_handler();
            
        $id   = (int)$_GET['id'];
        $post = get_post($id);
        $vars = array();
        
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
                'nonce'      => wp_nonce_field('media-form', '_wpnonce', false, false),
                'media_item' => get_media_item($id, array('toggle'=>false, 'show_title'=>false, 'send'=>false, 'delete'=>false, 'errors'=>(isset($errors[$id]) ? $errors[$id] : null))),
                'submit'     => get_submit_button(__( 'Save all changes'), 'button', 'save', false),
            );
        }
        
        // Render template
        $this->template->display('gallery_edit_photo.html.twig', $vars);

        // Send iframe footer and then 'die'.
        iframe_footer();
        die();
    }
    
    /* ----------- Public ----------- */
    
    /**
     * Called on wp_head, rendering the stylesheet as late as possible
     *
     * This will provide a basic background image and colors, along with 
     * tiling options that does not require any JavaScript. 
     */
    public function onWpHead()
    {
        if (is_admin())
            return;
        
        $style = '';
        $gallery_id = $this->options->active_gallery;
        
        // Only add a background image here if we have a valid gallery and the change frequency isn't custom
        if ($gallery_id && ($gallery = get_post($gallery_id)) != false && $gallery->post_status != 'trash' && $this->options->change_freq != static::CF_CUSTOM) {
            if (!isset($this->photos))
                $this->photos = new Photos($this);
            
            // Fetch a random image (or from the session)
            if ($this->options->change_freq == 'session') {
                session_start();
                
                $random_image = (isset($_SESSION['myatu_bgm_bg'])) ? $_SESSION['myatu_bgm_bg'] : wp_get_attachment_image_src($this->photos->getRandomPhotoId($gallery_id), 'large');
                
                $_SESSION['myatu_bgm_bg'] = $random_image;
            } else {
                $random_image = wp_get_attachment_image_src($this->photos->getRandomPhotoId($gallery_id), 'large');
            }
            
            if ($random_image) {
                if (!defined('BACKGROUND_IMAGE'))
                    define('BACKGROUND_IMAGE', $random_image[0]); // Set for themes that use get_background_image()
                
                $style .= sprintf('background-image: url(\'%s\');', $random_image[0]);
            }
            
            // Set the background position
            $bg_position = ($this->options->background_position) ? $this->options->background_position : $this->bg_positions[0];
            $bg_position = explode('-', $bg_position);
            
            $style .= sprintf('background-position: %s %s;', $bg_position[0], $bg_position[1]);
            
            // Set the background tiling          
            $style .= sprintf('background-repeat: %s;', ($this->options->background_repeat) ? $this->options->background_repeat : $this->bg_repeats[0]);
        }
            
        if ($color = get_background_color())
            $style .= sprintf('background-color: #%s;', $color);       
        
        printf('<style type="text/css" media="screen">body { %s }</style>'.PHP_EOL, $style);
    }
    
    /**
     * Load public scripts
     *
     * The scripts are only loaded if the change frequency is set to a custom value.
     */
    public function onPublicScripts()
    {
        if ($this->options->change_freq != static::CF_CUSTOM)
            return;

        list($js_dir, $version, $debug) = $this->getResourceDir();

        wp_enqueue_script($this->getName() . '-functions', $js_dir . 'functions' . $debug . '.js', array('jquery'), $version);
        wp_enqueue_script($this->getName() . '-pub', $js_dir . 'pub' . $debug . '.js', array($this->getName() . '-functions'), $version);
        
        // Make the change frequency available to JavaScript
        $gallery_id = $this->options->active_gallery;
        if ($gallery_id && ($gallery = get_post($gallery_id)) != false && $gallery->post_status != 'trash') {
            $change_freq = ($this->options->change_freq_custom) ? $this->options->change_freq_custom : 10;
        } else {
            $change_freq = 0; // Disabled
        }
        
        echo (
            '<script type="text/javascript">' . PHP_EOL .
            '//<![CDATA[' . PHP_EOL . 
            'var myatu_bgm_change_freq = ' . $change_freq . '; ' . PHP_EOL .
            '//]]>' . PHP_EOL .
            '</script>' . PHP_EOL
        );        
    }    
    
    /**
     * Load public styles
     *
     * The styles are only loaded if the change frequency is set to a custom value.
     */
    public function onPublicStyles()
    {
        if ($this->options->change_freq != static::CF_CUSTOM)
            return;

        list($css_dir, $version, $debug) = $this->getResourceDir(true);
        
        wp_enqueue_style($this->getName() . '-pub', $css_dir . 'pub.css', false, $version);     
    }    
    
    /**
     * Add a footer to the public side
     *
     * This creates the initial fallback image, which at a later stage will be replaced
     * based on the custom setting if JavaScript is available (otherwise, the fallback image 
     * is the only image to display)
     */
    public function onPublicFooter()
    {
        if ($this->options->change_freq != static::CF_CUSTOM)
            return;
            
        list($random_id, $random_image, $alt) = $this->getRandomImage($this->options->active_gallery);

        printf('<img id="myatu_bgm_top" class="myatu_bgm_img" src="%s" alt="%s" />', esc_url($random_image), htmlspecialchars($alt));
        
        // Initially hide the image if JS is enabled.
        printf("<script type=\"text/javascript\">\r\n//<![CDATA[\r\njQuery('#myatu_bgm_top').hide();\r\n//]]>\r\n</script>");
    }
}