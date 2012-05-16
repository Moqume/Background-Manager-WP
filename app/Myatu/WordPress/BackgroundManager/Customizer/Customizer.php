<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Customizer;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Images;

/**
 * Adds support for the WP 3.4 Theme Customzier
 *
 * @since 1.0.30
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Customizer
 */
class Customizer
{
    const PG_BGM      = 'background';

    /** Preview Options */
    const P_GALLERY      = 'active_gallery';
    const P_SELECTOR     = 'image_selection';
    const P_OPACITY      = 'background_opacity';
    const P_CHANGE_FREQ  = 'change_freq';
    const P_CHANGE_FCST  = 'change_freq_custom';
    const P_OVERLAY      = 'active_overlay';
    const P_OVERLAY_O    = 'overlay_opacity';
    const P_COLOR        = 'background_color';
    const P_BG_SIZE      = 'background_size';
    const P_BG_POS       = 'background_position';
    const P_BG_TILE      = 'background_repeat';
    const P_BG_SCROLL    = 'background_scroll';
    const P_BG_ST_VER    = 'background_stretch_vertical';
    const P_BG_ST_HOR    = 'background_stretch_horizontal';
    const P_TRANSITION   = 'background_transition';
    const P_TRANS_SPD    = 'transition_speed';
    const P_INFO_TAB     = 'info_tab';
    const P_INFO_TAB_T   = 'info_tab_thumb';
    const P_INFO_TAB_D   = 'info_tab_desc';
    const P_INFO_TAB_LN  = 'info_tab_link';
    const P_INFO_TAB_L   = 'info_tab_location';
    const P_PIN_IT_BTN   = 'pin_it_btn';
    const P_PIN_IT_BTN_L = 'pin_it_btn_location';
    const P_FS_ADJUST    = 'full_screen_adjust';
    const P_FS_CENTER    = 'full_screen_center';

    /** Magic method prefixes, see @__call */
    const M_PREVIEW = 'OnPreview_';
    const M_SAVE    = 'OnSave_';
    const M_FILTER  = 'OnFilter_';

    protected $owner;
    protected $preview_values = array();
    protected $active_customizations = array();

    /**
     * Constructor
     *
     * @param Main $owner Reference to a WordpressPlugin / Owner object
     */
    public function __construct(Main $owner)
    {
        $this->owner = $owner;
        $this->active_customizations = array(
            // ID => array('option' => special function called to save option, 'label' => Display label, 'priority' => Display order priority (optional), 'sanitize' => Sanitize callback name (optional)
            static::P_GALLERY       => array('label' => __('Image Set', $this->owner->getName()),               'priority' => 10),
            static::P_SELECTOR      => array('label' => __('Image selection order', $this->owner->getName()),   'priority' => 11),
            static::P_CHANGE_FREQ   => array('label' => __('Select an image', $this->owner->getName()),         'priority' => 12),
            static::P_CHANGE_FCST   => array('label' => __('Interval (seconds)', $this->owner->getName()),      'priority' => 13, 'sanitize' => 'onSanitizeCustomFreq'),
            static::P_COLOR         => array('label' => __('Background Color', $this->owner->getName()),        'priority' => 14, 'sanitize' => 'onSanitizeColor', 'option' => array($this, 'onSaveColor')),
            static::P_BG_SIZE       => array('label' => __('Size', $this->owner->getName()),                    'priority' => 20),
            static::P_BG_POS        => array('label' => __('Position', $this->owner->getName()),                'priority' => 21),
            static::P_BG_TILE       => array('label' => __('Tiling', $this->owner->getName()),                  'priority' => 21),
            static::P_BG_SCROLL     => array('label' => __('Scrolling', $this->owner->getName()),               'priority' => 21),
            static::P_OPACITY       => array('label' => __('Opacity', $this->owner->getName()),                 'priority' => 21, 'sanitize' => 'onSanitizeOpacity'),
            static::P_BG_ST_VER     => array('label' => __('Stretch Vertical', $this->owner->getName()),        'priority' => 22, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_BG_ST_HOR     => array('label' => __('Stretch Horizontal', $this->owner->getName()),      'priority' => 22, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_TRANSITION    => array('label' => __('Transition Effect', $this->owner->getName()),       'priority' => 30),
            static::P_TRANS_SPD     => array('label' => __('Transition Speed', $this->owner->getName()),        'priority' => 31, 'sanitize' => 'onSanitizeTransitionSpeed'),
            static::P_FS_ADJUST     => array('label' => __('Adjust Image Size', $this->owner->getName()),       'priority' => 32, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_FS_CENTER     => array('label' => __('Center Image', $this->owner->getName()),            'priority' => 33, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_OVERLAY       => array('label' => __('Overlay', $this->owner->getName()),                 'priority' => 40),
            static::P_OVERLAY_O     => array('label' => __('Overlay Opacity', $this->owner->getName()),         'priority' => 41, 'sanitize' => 'onSanitizeOpacity'),
            static::P_INFO_TAB      => array('label' => __('Display [ + ] Icon', $this->owner->getName()),      'priority' => 50, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_INFO_TAB_T    => array('label' => __('Show thumbnail', $this->owner->getName()),          'priority' => 51, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_INFO_TAB_D    => array('label' => __('Show description', $this->owner->getName()),        'priority' => 51, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_INFO_TAB_LN   => array('label' => __('Enable linking', $this->owner->getName()),          'priority' => 51, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_INFO_TAB_L    => array('label' => __('Location', $this->owner->getName()),                'priority' => 52),
            static::P_PIN_IT_BTN    => array('label' => __('Display "Pin It" Button', $this->owner->getName()), 'priority' => 60, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_PIN_IT_BTN_L  => array('label' => __('Location', $this->owner->getName()),                'priority' => 61),
        );

        // Set actions
        add_action('customize_register', array($this, 'onCustomizeRegister'));          // Controls on Theme Customizer
        add_action('customize_preview_init', array($this, 'onPreviewInit'));            // Called when a preview is requested
        add_action('customize_controls_enqueue_scripts', array($this, 'onEnqueue'));    // Called when ready to queue control scripts

        // "Magic" actions (@see __call)
        foreach (array_keys($this->active_customizations) as $customization) {
            add_action('customize_preview_' . Main::BASE_PUB_PREFIX . $customization, array($this, static::M_PREVIEW . $customization));
            add_action('customize_save_' . Main::BASE_PUB_PREFIX . $customization, array($this, static::M_SAVE . $customization));
        }
    }

    /**
     * Magic handler
     *
     * @param string $name Full function name
     * @param array $arguments Arguments passed to the function
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        list($func, $id) = explode('_', $name, 2);

        // Re-add trailing delimiter
        $func .= '_';

        switch ($func) {
            case static::M_PREVIEW :
                $this->setPreviewValue($id);
                break;

            case static::M_FILTER :
                return $this->getPreviewValue($id, $arguments[0]);
                break;

            case static::M_SAVE :
                if ($this->getSaveValue($id, $value)) {
                    $save_details = $this->active_customizations[$id];

                    if (array_key_exists('option', $save_details)) {
                        // Complex save
                        call_user_func($save_details['option'], $value);
                    } else {
                        // Simple save
                        $this->owner->options->$id = $value;
                    }
                }
                break;

            default :
                throw new \BadFunctionCallException('Function ' . $name . ' does not exist');
                break;
        }
    }

    /* ----------- Helpers ----------- */

    /**
     * Overrides the original value if a preview value is present
     *
     * @param string $id Setting ID
     * @param mixed $original Original value (or null)
     * @return mixed
     */
    protected function getPreviewValue($id, $original = null)
    {
        if (isset($this->preview_values[$id]) && !is_null($this->preview_values[$id]))
            return $this->preview_values[$id];

        return $original;
    }

    /**
     * Stores the preview value
     *
     * @param string $id Setting ID
     */
    protected function setPreviewValue($id)
    {
        global $wp_customize;

        if (!is_a($wp_customize, '\WP_Customize'))
            return;

        $setting = $wp_customize->get_setting(Main::BASE_PUB_PREFIX . $id);

        if (is_a($setting, '\WP_Customize_Setting')) {
            $value = $setting->post_value();

            if (!is_null($value) && array_key_exists('sanitize', $this->active_customizations[$id]))
                $value = call_user_func(array($this, $this->active_customizations[$id]['sanitize']), $value);

            $this->preview_values[$id] = $value;
        }
    }

    /**
     * Sets the value to be saved, returns true if we should save
     *
     * @param string $id Setting ID
     * @param mixed $value Value to be saved
     */
    protected function getSaveValue($id, &$value)
    {
        $this->setPreviewValue($id);
        $value = $this->getPreviewValue($id);

        return !is_null($value);
    }

    /**
     * Adds a setting and control in one go
     *
     * NOTE! The exposed ID is $id prefixed by the BASE_PUB_PREFIX
     *
     * @param string $id ID of control item
     * @param array $details Array containing extra details about the item
     * @param string $type A string specifying the control type
     * @param array $choices Array containing key=>value pairs of possible choices (valid for 'radio', 'select')
     */
    protected function addSettingControl($id, $details, $type = 'text', $choices = array())
    {
        global $wp_customize;

        if (!is_a($wp_customize, '\WP_Customize'))
            return;

        $priority = isset($details['priority']) ? $details['priority'] : 10;

        $wp_customize->add_setting(Main::BASE_PUB_PREFIX . $id, array(
            'default'   => $this->owner->options->$id,
            'type'      => 'myatu_bgm',
        ));

        if (is_string($type)) {
            $wp_customize->add_control(Main::BASE_PUB_PREFIX . $id, array(
                'label'     => $details['label'],
                'priority'  => $priority,
                'section'   => static::PG_BGM,
                'type'      => $type,
                'choices'   => $choices,
            ));
        }
    }

    /**
     * Adds a divider control
     *
     * @param int $priority Display priority
     * @param string $label Optional label to display
     */
    protected function addDividerControl($priority, $label = '')
    {
        global $wp_customize;

        if (!is_a($wp_customize, '\WP_Customize'))
            return;

        $id = 'divider_' . strtr(strtolower($label), ' -', '__');

        $wp_customize->add_setting($id, array('type' => 'none'));
        $wp_customize->add_control(new DividerControl($wp_customize, $id, array(
            'priority'  => $priority,
            'section'   => static::PG_BGM,
            'owner'     => $this->owner,
            'label'     => $label,
        )));
    }

    /* ----------- Events ----------- */

    /**
     * Enqueue Scripts
     */
    public function onEnqueue()
    {
        // Enqueue JS
        list($js_url, $version, $debug) = $this->owner->getResourceUrl();
        wp_enqueue_script($this->owner->getName() . '-functions', $js_url . 'functions' . $debug . '.js', array('jquery'), $version);
        wp_enqueue_script($this->owner->getName() . '-customize', $js_url . 'customize' . $debug . '.js', array($this->owner->getName() . '-functions'), $version);

        // Enueue CSS
        list($css_url, $version, $debug) = $this->owner->getResourceUrl('css');
        wp_enqueue_style($this->owner->getName() . '-customize', $css_url . 'customize' . $debug . '.css', false, $version);
    }

    /**
     * Event called when a preview is requested
     *
     * Registers the filters for retrieving the preview values
     */
    public function onPreviewInit()
    {
        global $wp_customize;

        // Initialize the filters
        foreach (array_keys($this->active_customizations) as $customization) {
            // 'myatu_bgm_' . 'active_overlay' ... array($this, 'OnFilter_' . 'active_overlay'
            add_filter(Main::BASE_PUB_PREFIX . $customization, array($this, static::M_FILTER . $customization), 90);
        }
    }

    /**
     * Register controls for Customize Theme settings
     *
     * Adds support for the WP 3.4+ 'Customize Theme' screen
     */
    public function onCustomizeRegister()
    {
        global $wp_customize;

        if (!is_a($wp_customize, '\WP_Customize'))
            return;

        $wp_customize->add_section(static::PG_BGM, array(
            'title'     => __('Background', $this->owner->getName()),
            'priority'  => 30,
		));

        // Iterate active customizations and create controls for them
        foreach ($this->active_customizations as $id=>$details) {
            // Determine display priority for controls
            $priority = isset($details['priority']) ? $details['priority'] : 10;

            switch($id) {
                case static::P_GALLERY :
                    // Background Image Set
                    $choices = array(0 => __('-- None (deactivated) --', $this->owner->getName()));

                    foreach($this->owner->getSettingGalleries($this->owner->options->active_gallery) as $gallery) {
                        $choices[$gallery['id']] = $gallery['name'];
                    }

                    $this->addSettingControl($id, $details, 'select', $choices);
                    break;

                case static::P_SELECTOR :
                    $choices = array(Images::SO_RANDOM => __('Random'), Images::SO_ASC => __('Ascending'), Images::SO_DESC => __('Descending'));
                    $this->addSettingControl($id, $details, 'radio', $choices);

                    break;

                case static::P_CHANGE_FREQ :
                    $choices = array(
                        'session' => __('At each browser session', $this->owner->getName()),
                        'load'    => __('On a page (re)load', $this->owner->getName()),
                        'custom'  => __('At a specified interval', $this->owner->getName()),
                    );

                    $this->addSettingControl($id, $details, 'radio', $choices);
                    break;

                case static::P_CHANGE_FCST :
                    $this->addSettingControl($id, $details, 'text');
                    break;

                case static::P_COLOR :
                    // Background Color - Note that the exposed ID needs to be prefixed
                    $wp_customize->add_setting(Main::BASE_PUB_PREFIX . $id, array(
                        'default'           => get_background_color(),
                        'type'              => 'myatu_bgm',
                    ));

                    $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, Main::BASE_PUB_PREFIX . $id, array(
                        'label'     => __('Background Color', $this->owner->getName()),
                        'priority'  => $priority,
                        'section'   => static::PG_BGM,
                    )));
                    break;

                case static::P_BG_SIZE :
                    // Background Layout (Size)
                    $choices = array(
                        'as-is' => __('Normal', $this->owner->getName()),
                        'full'  => __('Full Screen', $this->owner->getName()),
                    );

                    $this->addSettingControl($id, $details, 'radio', $choices);
                    $this->addDividerControl($priority-1, __('Background Layout', $this->owner->getName()));
                    break;

                case static::P_BG_POS :
                    // Background Layout (Position)
                    $this->addSettingControl($id, $details, 'radio', $this->owner->getBgOptions('position', true));
                    break;

                case static::P_BG_TILE :
                    // Background Layout (Tiling/Repeat)
                    $this->addSettingControl($id, $details, 'radio', $this->owner->getBgOptions('repeat', true));
                    break;

                case static::P_OVERLAY :
                    // Overlay
                    $choices = array('' => __('-- None (deactivated) --', $this->owner->getName()));

                    foreach($this->owner->getSettingOverlays($this->owner->options->active_overlay) as $overlay) {
                        $choices[$overlay['value']] = $overlay['desc'];
                    }

                    $this->addSettingControl($id, $details, 'select', $choices);
                    $this->addDividerControl($priority-1, __('Background Overlay', $this->owner->getName()));
                    break;

                case static::P_OPACITY :
                case static::P_OVERLAY_O :
                    // Overlay Opacity
                    $wp_customize->add_setting(Main::BASE_PUB_PREFIX . $id, array(
                        'default'   => $this->owner->options->$id,
                        'type'      => 'myatu_bgm',
                    ));

                    $wp_customize->add_control(new SlideControl($wp_customize, Main::BASE_PUB_PREFIX . $id, array(
                        'label'     => $details['label'],
                        'priority'  => $priority,
                        'section'   => static::PG_BGM,
                        'owner'     => $this->owner,
                    )));
                    break;

                case static::P_BG_SCROLL :
                    $choices = array(
                        'scroll' => __('Scroll with the screen'),
                        'fixed'  => __('Fixed'),
                    );

                    $this->addSettingControl($id, $details, 'radio', $choices);
                    break;

                case static::P_BG_ST_VER   :
                case static::P_BG_ST_HOR   :
                case static::P_INFO_TAB_T  :
                case static::P_INFO_TAB_D  :
                case static::P_INFO_TAB_LN :
                case static::P_FS_ADJUST   :
                case static::P_FS_CENTER   :
                    $this->addSettingControl($id, $details, 'checkbox');
                    break;

                case static::P_TRANSITION :
                    $this->addSettingControl($id, $details, 'select', $this->owner->getBgOptions('transition', true));
                    $this->addDividerControl($priority-1, __('Background Transitioning Effect', $this->owner->getName()));
                    break;

                case static::P_TRANS_SPD :
                    // Overlay Opacity
                    $wp_customize->add_setting(Main::BASE_PUB_PREFIX . $id, array(
                        'default'   => $this->owner->options->$id,
                        'type'      => 'myatu_bgm',
                    ));

                    $wp_customize->add_control(new SlideControl($wp_customize, Main::BASE_PUB_PREFIX . $id, array(
                        'label'       => $details['label'],
                        'priority'    => $priority,
                        'section'     => static::PG_BGM,
                        'owner'       => $this->owner,
                        'show_value'  => false,
                        'left_label'  => '-',
                        'right_label' => '+',
                        'reverse'     => true,
                        'min'         => 100,
                        'max'         => 15000,
                        'step'        => 100,
                        'range'       => false,
                    )));
                    break;

                case static::P_INFO_TAB :
                    $this->addSettingControl($id, $details, 'checkbox');
                    $this->addDividerControl($priority-1, __('Background Information', $this->owner->getName()));
                    break;

                case static::P_PIN_IT_BTN :
                    $this->addSettingControl($id, $details, 'checkbox');
                    $this->addDividerControl($priority-1, __('Pinterest', $this->owner->getName()));
                    break;

                case static::P_INFO_TAB_L :
                case static::P_PIN_IT_BTN_L :
                    $this->addSettingControl($id, $details, 'radio', $this->owner->getBgOptions('corner', true));
                    break;

            } // switch
        } // foreach
    }

    /**
     * Sanitize the color
     *
     * @param mixed $value The value obtained from the customizer
     */
    public function onSanitizeColor($value)
    {
        $value = ltrim($value, '#');

        if (!empty($value) && !preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $value))
            return null;

        return $value;
    }

    /**
     * Saves the color
     *
     * The color is saved in the 'background_color' theme modification instead of an option
     *
     * @param string $value The color value to save
     */
    public function onSaveColor($value)
    {
        (empty($value)) ? remove_theme_mod('background_color') : set_theme_mod('background_color', $value);
    }

    /**
     * Sanitize the opacity
     *
     * @param mixed $value The value obtained from the customizer
     */
    public function onSanitizeOpacity($value)
    {
        $value = (int)$value;

        if ($value > 100 || $value < 1)
            return null;

        return $value;
    }

    /**
     * Sanitize the checkbox return value
     *
     * @param mixed $value The value obtained from the customizer
     */
    public function onSanitizeCheckbox($value)
    {
        if ($value == 'false' || $value == '1')
            return true;

        return false;
    }

    /**
     * Sanitize the transition speed
     *
     * @param mixed $value The value obtained from the customizer
     */
    public function onSanitizeTransitionSpeed($value)
    {
        $value = (int)$value;

        if ($value > 15000 || $value < 100)
            return null;

        return $value;
    }

    /**
     * Sanitize the custom change frequency (seconds)
     *
     * @param mixed $value The value obtained from the customizer
     */
    public function onSanitizeCustomFreq($value)
    {
        $value = (int)$value;

        if ($value < 1)
            $value = 10;

        return $value;
    }
}
