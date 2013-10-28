<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pages;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Images;

use Pf4wp\Notification\AdminNotice;

/**
 * The Settings page
 *
 * Note: Pages are in ..\Main\onBuildMenu()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pages
 * @since 1.0.39
 */
class Settings
{
    protected $owner;

    public function __construct(Main $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Helper that returns the settings handled by this page
     *
     * The array returned either contains the sanitize options or value
     *
     * @param bool $want_values If set to `true`, the option values are included as the returned values, otherwise the sanitize options (default)
     * @return array
     */
    public function getSettingOptions($want_values = false)
    {
        $results = array(
            'active_gallery'                => 'int',
            'image_selection'               => array('in_array', array(Images::SO_RANDOM, Images::SO_ASC, Images::SO_DESC)),
            'change_freq'                   => array('in_array', array(Main::CF_SESSION, Main::CF_LOAD, Main::CF_CUSTOM)),
            'change_freq_custom'            => array('range', array(1, 60, 10)),
            'background_size'               => array('in_array', array(Main::BS_FULL, Main::BS_ASIS)),
            'background_opacity'            => array('range', array(0, 100, $this->owner->options->background_opacity)),
            'background_scroll'             => array('in_array', array(Main::BST_FIXED, Main::BST_SCROLL)),
            'background_position'           => array('in_array', $this->owner->getBgOptions('position')),
            'background_repeat'             => array('in_array', $this->owner->getBgOptions('repeat')),
            'background_transition'         => array('in_array', $this->owner->getBgOptions('transition')),
            'transition_speed'              => array('range', array(100, 15000, 600)),
            'background_stretch_vertical'   => 'bool',
            'background_stretch_horizontal' => 'bool',
            'active_overlay'                => 'string',
            'overlay_opacity'               => array('range', array(0, 100, $this->owner->options->overlay_opacity)),
            'display_on_front_page'         => 'bool',
            'display_on_single_post'        => 'bool',
            'display_on_single_page'        => 'bool',
            'display_on_archive'            => 'bool',
            'display_on_search'             => 'bool',
            'display_on_error'              => 'bool',
            'display_on_mobile'             => 'bool',
            'info_tab'                      => 'bool',
            'info_tab_location'             => array('in_array', $this->owner->getBgOptions('corner')),
            'info_tab_thumb'                => 'bool',
            'info_tab_link'                 => 'bool',
            'info_tab_desc'                 => 'bool',
            'pin_it_btn'                    => 'bool',
            'pin_it_btn_location'           => array('in_array', $this->owner->getBgOptions('corner')),
            'full_screen_center'            => 'bool',
            'single_post_override'          => array('in_array', $this->owner->getBgOptions('role')),
            'initial_ease_in'               => 'bool',
            'bg_click_new_window'           => 'bool',
            'bg_track_clicks'               => 'bool',
            'bg_track_clicks_category'      => 'string',
            'image_remember_last'           => 'bool',
        );

        if ($want_values) {
            foreach ($results as $result_key => $result_value) {
                $results[$result_key] = $this->owner->options->$result_key;
            }
        }

        return $results;
    }

    /**
     * Handles pre-Settings Menu actions
     */
    public function onSettingsMenuLoad($current_screen)
    {
        // Extra scripts to include
        list($js_url, $version, $debug) = $this->owner->getResourceUrl();

        // Color picker
        wp_enqueue_script('farbtastic');

        // Slider
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-slider');

        // Default Functions
        wp_enqueue_script($this->owner->getName() . '-settings', $js_url . 'settings' . $debug . '.js', array($this->owner->getName() . '-functions'), $version);

        // Extra CSS to include
        list($css_url, $version, $debug) = $this->owner->getResourceUrl('css');

        // Color picker
        wp_enqueue_style('farbtastic');

        // Slider
        wp_enqueue_style('jquery-ui-slider', $css_url . 'vendor/jquery-ui-slider' . $debug . '.css', false, $version);

        // Guided Help, Step 1 ("Get Started")
        new \Myatu\WordPress\BackgroundManager\Pointers\AddNewStep1($this->owner->getName());

        // Intro to new features in 1.1
        if ($this->owner->options->last_upgrade == '1.1') {
            new \Myatu\WordPress\BackgroundManager\Pointers\Upgrade1dot1new1($this->owner->getName());
        }

        // Save settings if POST is set
        if (!empty($_POST) && isset($_POST['_nonce'])) {
            if (!wp_verify_nonce($_POST['_nonce'], 'onSettingsMenu'))
                wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));

            // Set options from $_POST
            $this->owner->options->load($_POST, $this->getSettingOptions());

            // Additional options not handled by load():

            // Display settings for Custom Post Types
            $display_on = array();

            foreach (get_post_types(array('_builtin' => false, 'public' => true), 'objects') as $post_type_key => $post_type) {
                // Iterate over existing custom post types, filtering out whether it can be shown or not
                if ($post_type_key !== Main::PT_GALLERY)
                    $display_on[$post_type_key] = (!empty($_POST['display_on'][$post_type_key]));
            }

            $this->owner->options->display_custom_post_types = $display_on;

            // Slightly different, the background color is saved as a theme mod only.
            $background_color = ltrim($_POST['background_color'], '#');
            if (empty($background_color)) {
                remove_theme_mod('background_color');
            } else if (preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $background_color)) {
                set_theme_mod('background_color', $background_color);
            }

            AdminNotice::add(__('Settings have been saved', $this->owner->getName()));
        }
    }

    /**
     * Settings Menu
     */
    public function onSettingsMenu($data, $per_page)
    {
        //global $wp_version, $wpdb;

        // Generate a list of galleries, including a default of "None", and set a flag if we can use collages
        $galleries = array_merge(array(
            array(
                'id' => 0,
                'name' => __('-- None (deactivated) --', $this->owner->getName()),
                'selected' => ($this->owner->options->active_gallery == false),
            )
        ), $this->owner->getSettingGalleries($this->owner->options->active_gallery));

        // Grab the overlays and add a default of "None"
        $overlays = array_merge(array(
            array(
                'value'    => '',
                'desc'     => __('-- None (deactivated) --', $this->owner->getName()),
                'selected' => ($this->owner->options->active_overlay == false),
            ),
        ), $this->owner->getSettingOverlays($this->owner->options->active_overlay));

        // Grab Custom Post Types
        $custom_post_types         = array();
        $display_custom_post_types = $this->owner->options->display_custom_post_types;

        foreach (get_post_types(array('_builtin' => false, 'public' => true), 'objects') as $post_type_key => $post_type) {
            if ($post_type_key !== Main::PT_GALLERY)
                $custom_post_types[$post_type_key] = array(
                    'name'    => $post_type->labels->name,
                    'display' => (isset($display_custom_post_types[$post_type_key])) ? $display_custom_post_types[$post_type_key] : true,
                );
        }

        // Template exports:
        $vars = array(
            'nonce'             => wp_nonce_field('onSettingsMenu', '_nonce', true, false),
            'submit_button'     => get_submit_button(),
            'galleries'         => $galleries,
            'overlays'          => $overlays,
            'background_color'  => get_background_color(),
            'custom_post_types' => $custom_post_types,
            'bg_positions'      => $this->owner->getBgOptions('position', true),
            'bg_repeats'        => $this->owner->getBgOptions('repeat', true),
            'bg_transitions'    => $this->owner->getBgOptions('transition', true),
            'corner_locations'  => $this->owner->getBgOptions('corner', true),
            'roles'             => $this->owner->getBgOptions('role', true),
            'plugin_base_url'   => $this->owner->getPluginUrl(),
            'debug_info'        => $this->owner->getDebugInfo(),
            'plugin_name'       => $this->owner->getDisplayName(),
            'plugin_version'    => $this->owner->getVersion(),
            'plugin_home'       => \Pf4wp\Info\PluginInfo::getInfo(false, $this->owner->getPluginBaseName(), 'PluginURI'),
        );

        // Merge template exports with values of options
        $vars = array_merge($vars, $this->getSettingOptions(true));

        $this->owner->template->display('settings.html.twig', $vars);
    }
}
