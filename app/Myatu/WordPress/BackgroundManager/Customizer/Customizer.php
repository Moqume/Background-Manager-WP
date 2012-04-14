<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Myatu\WordPress\BackgroundManager\Customizer;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * Adds support for the WP 3.4 Theme Customzier
 *
 * @since 1.0.30
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager\Customize
 */
class Customizer
{
    const PG_BGM      = 'myatu_bgm_background';
    
    /** Preview Options (same names as filters) */
    const P_GALLERY   = 'myatu_bgm_active_gallery';
    const P_OPACITY   = 'myatu_bgm_opacity';
    const P_OVERLAY   = 'myatu_bgm_active_overlay';
    const P_OVERLAY_O = 'myatu_bgm_overlay_opacity';
    const P_COLOR     = 'myatu_bgm_bg_color';
    const P_BG_SIZE   = 'myatu_bgm_bg_size';
    const P_BG_POS    = 'myatu_bgm_bg_pos';
    const P_BG_TILE   = 'myatu_bgm_bg_repeat';
    const P_BG_SCROLL = 'myatu_bgm_bg_scroll';
    const P_BG_ST_VER = 'myatu_bgm_bg_stretch_ver';
    const P_BG_ST_HOR = 'myatu_bgm_bg_stretch_hor';
    
    /** Magics */
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
            // ID => array('option' => under what name to save the option, 'label' => Display label, 'priority' => Display order priority (optional), 'sanitize' => Sanitize callback name (optional)
            static::P_GALLERY       => array('option' => 'active_gallery',              'label' => __('Image Set', $this->owner->getName()),            'priority' => 10),
            static::P_COLOR         => array('option' => array($this, 'onSaveColor'),   'label' => __('Background Color', $this->owner->getName()),     'priority' => 11, 'sanitize' => 'onSanitizeColor'),
            static::P_BG_SIZE       => array('option' => 'background_size',             'label' => __('Size', $this->owner->getName()),                 'priority' => 20), 
            static::P_BG_POS        => array('option' => 'background_position',         'label' => __('Position', $this->owner->getName()),             'priority' => 21),
            static::P_BG_TILE       => array('option' => 'background_repeat',           'label' => __('Tiling', $this->owner->getName()),               'priority' => 21),
            static::P_BG_SCROLL     => array('option' => 'background_scroll',           'label' => __('Scrolling', $this->owner->getName()),            'priority' => 21),
            static::P_BG_ST_VER     => array('option' => 'background_stretch_vertical', 'label' => __('Stretch Vertical', $this->owner->getName()),     'priority' => 21, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_BG_ST_HOR     => array('option' => 'background_stretch_horizontal', 'label' => __('Stretch Horizontal', $this->owner->getName()), 'priority' => 21, 'sanitize' => 'onSanitizeCheckbox'),
            static::P_OPACITY       => array('option' => 'background_opacity',          'label' => __('Opacity', $this->owner->getName()),              'priority' => 21, 'sanitize' => 'onSanitizeOpacity'),
            static::P_OVERLAY       => array('option' => 'active_overlay',              'label' => __('Overlay', $this->owner->getName()),              'priority' => 30), 
            static::P_OVERLAY_O     => array('option' => 'overlay_opacity',             'label' => __('Overlay Opacity', $this->owner->getName()),      'priority' => 31, 'sanitize' => 'onSanitizeOpacity'),
        );        
        
        // Set actions
        add_action('customize_register', array($this, 'onCustomizeRegister'));          // Controls on Theme Customizer
        add_action('customize_preview_init', array($this, 'onPreviewInit'));            // Called when a preview is requested
        add_action('customize_controls_enqueue_scripts', array($this, 'onEnqueue'));    // Called when ready to queue control scripts
        
        // "Magic" actions (@see __call)
        foreach (array_keys($this->active_customizations) as $customization) {
            add_action('customize_preview_' . $customization, array($this, static::M_PREVIEW . $customization));
            add_action('customize_save_' . $customization, array($this, static::M_SAVE . $customization));
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
                    
                    if (isset($save_details['option'])) {
                        if (is_string($save_details['option'])) {
                            // Simple save
                            $this->owner->options->$save_details['option'] = $value;
                        } else {
                            // Complex save
                            call_user_func($save_details['option'], $value);
                        }                    
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
        global $customize;
        
        if (!is_a($customize, '\WP_Customize'))
            return;
        
        $setting = $customize->get_setting($id);
        
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
     * @param string $id ID of control item
     * @param array $details Array containing extra details about the item
     * @param string $type A string specifying the control type
     * @param array $choices Array containing key=>value pairs of possible choices (valid for 'radio', 'select')
     */
    protected function addSettingControl($id, $details, $type = 'text', $choices = array())
    {
        global $customize;
        
        if (!is_a($customize, '\WP_Customize'))
            return;
            
        $priority = isset($details['priority']) ? $details['priority'] : 10;
            
        $customize->add_setting($id, array(
            'default'   => $this->owner->options->$details['option'],
            'type'      => 'myatu_bgm',
        ));
        
        if (is_string($type)) {
            $customize->add_control($id, array(
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
        global $customize;
        
        if (!is_a($customize, '\WP_Customize'))
            return;
            
        $id = 'divider_' . rand();
            
        $customize->add_setting($id, array('type' => 'none'));
        $customize->add_control(new DividerControl($customize, $id, array(
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
        wp_enqueue_script($this->owner->getName() . '-customize', $js_url . 'customize' . $debug . '.js', array('jquery'), $version);
    }
    
    
    /**
     * Event called when a preview is requested
     *
     * Registers the filters for retrieving the preview values
     */
    public function onPreviewInit()
    { 
        global $customize;
        
        // Initialize the filters
        foreach (array_keys($this->active_customizations) as $customization) {
            add_filter($customization, array($this, static::M_FILTER . $customization), 90);
        }
    }
    
    /**
     * Register controls for Customize Theme settings
     *
     * Adds support for the WP 3.4+ 'Customize Theme' screen
     */
    public function onCustomizeRegister()
    {
        global $customize;
        
        if (!is_a($customize, '\WP_Customize'))
            return;
        
        $customize->add_section(static::PG_BGM, array(
            'title'     => __('Background', $this->owner->getName()),
            'priority'  => 30,
		));
        
        // Iterate active customizations and create controls for them
        foreach ($this->active_customizations as $id=>$details) {
            if (!isset($details['option']))
                continue;
            
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
                
                case static::P_COLOR :
                    // Background Color
                    $customize->add_setting($id, array(
                        'default'           => get_background_color(),
                        'type'              => 'myatu_bgm',
                    ));
                    
                    $customize->add_control($id, array(
                        'label'     => __('Background Color', $this->owner->getName()),
                        'priority'  => $priority,
                        'section'   => static::PG_BGM,
                        'type'      => 'color',
                    ));
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
                    $customize->add_setting($id, array(
                        'default'   => $this->owner->options->$details['option'],
                        'type'      => 'myatu_bgm',
                    ));
                    
                    $customize->add_control(new SlideControl($customize, $id, array(
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
                    
                case static::P_BG_ST_VER :
                case static::P_BG_ST_HOR :
                    $this->addSettingControl($id, $details, 'checkbox');
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
     */
    public function onSanitizeCheckbox($value)
    {
        if ($value == 'true')
            return true;
            
        return false;
    }
}