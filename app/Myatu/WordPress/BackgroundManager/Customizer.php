<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Myatu\WordPress\BackgroundManager;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * Adds support for the WP 3.4 Theme Customzier
 *
 * @since 1.0.30
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 */
class Customizer
{
    const PG_BGM    = 'myatu_bgm_background';
    const P_GALLERY = 'myatu_bgm_active_gallery';
    const P_COLOR   = 'myatu_bgm_active_color';
    
    protected $owner;
    protected $preview_values = array();
        
    /** 
     * Constructor
     *
     * @param Main $owner Reference to a WordpressPlugin / Owner object
     */
    public function __construct(Main $owner)
    {
        $this->owner = $owner;
        
        add_action('customize_register', array($this, 'onCustomizeRegister'), 20);  // Controls on Theme Customizer
        add_action('customize_preview_init', array($this, 'onPreviewInit'), 20);    // Called when a preview is requested
        
        // Actions to obtain preview values
        add_action('customize_preview_' . static::P_GALLERY, array($this, 'onActiveGalleryPreview'), 20);
        add_action('customize_preview_' . static::P_COLOR,   array($this, 'onBgColorPreview'), 20);
        
        // Actions to save the values
        add_action('customize_save_' . static::P_GALLERY, array($this, 'onActiveGallerySave'), 20);
        add_action('customize_save_' . static::P_COLOR,   array($this, 'onBgColorSave'), 20);
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
        
        if (!is_a($setting, '\WP_Customize_Setting'))
            return;
            
        $this->preview_values[$id] = $setting->post_value();
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
    
    
    /* ----------- Events ----------- */
    
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
        
        /* Section -> Setting -> Control */
        
        $customize->add_section(static::PG_BGM, array(
            'title'     => __('Background', $this->owner->getName()),
            'priority'  => 30,
		));
        
        /* Background Image Set */
        $galleries = array(0 => __('-- None (deactivated) --', $this->owner->getName()));
        
        foreach($this->owner->getSettingGalleries($this->owner->options->active_gallery) as $gallery) {
            $galleries[$gallery['id']] = $gallery['name'];
        }
        
        $customize->add_setting(static::P_GALLERY, array(
            'default'   => $this->owner->options->active_gallery,
            'type'      => 'custom',
        ));
        
        $customize->add_control(static::P_GALLERY, array(
            'label'     => __('Backgrond Image Set', $this->owner->getName()),
            'section'   => static::PG_BGM,
            'type'      => 'select',
            'choices'   => $galleries,
        ));
        
        /* Background Color */
		$customize->add_setting(static::P_COLOR, array(
			'default'           => get_background_color(),
			'sanitize_callback' => 'sanitize_hexcolor',
            'type'              => 'custom',
		));
        
        $customize->add_control(static::P_COLOR, array(
			'label'     => __('Background Color', $this->owner->getName()),
			'section'   => static::PG_BGM,
			'type'      => 'color',
		));       
    }
    
    /**
     * Event called when a preview is requested
     */
    public function onPreviewInit()
    {
        // Add a filters (late!)
        add_filter('myatu_bgm_active_gallery', array($this, 'onActiveGalleryFilter'), 90);
        add_filter('myatu_bgm_bg_color',       array($this, 'onBgColorFilter'), 90);
    }
    
    
    /* ----------- Updaters ----------- */
    
    public function onActiveGallerySave()
    {
        if ($this->getSaveValue(static::P_GALLERY, $value))
            $this->owner->options->active_gallery = $value;
    }
    
    public function onBgColorSave()
    {
        if (!$this->getSaveValue(static::P_COLOR, $value))
            return;
        
        $background_color = ltrim($value, '#');
        
        if (empty($background_color)) {
            remove_theme_mod('background_color');
        } else if (preg_match('/^([a-fA-F0-9]){3}(([a-fA-F0-9]){3})?$/', $background_color)) {
            set_theme_mod('background_color', $background_color);
        }
    }
    
    /* ----------- Preview Value Setters ----------- */
    
    public function onActiveGalleryPreview()
    {
        $this->setPreviewValue(static::P_GALLERY);
    }
    
    public function onBgcolorPreview()
    {
        $this->setPreviewValue(static::P_COLOR);
    }
    
    
    /* ----------- Filters (Preview values passed back to BGM) ----------- */
    
    public function onActiveGalleryFilter($orig)
    {
        return $this->getPreviewValue(static::P_GALLERY, $orig);
    }
    
    public function onBgColorFilter($orig)
    {
        return $this->getPreviewValue(static::P_COLOR, $orig);
    }

}