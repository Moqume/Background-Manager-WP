<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Myatu\WordPress\BackgroundManager\Customizer;

/**
 * Provides a slide controller for the WP Theme Customizer
 *
 * @since 1.0.30
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Customizer
 */
class SlideControl extends \WP_Customize_Control
{
    public $type        = 'slide';
    public $min         = 1;
    public $max         = 100;
    public $step        = 1;
    public $range       = 'min';
    public $show_value  = true;
    public $reverse     = false;
    public $left_label  = '';
    public $right_label = '%';
    public $owner;

    /**
     * Enqueue the required Javascript and CSS stylesheet
`    */
	public function enqueue() {
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-slider');
        
        if (!is_a($this->owner, '\Myatu\WordPress\BackgroundManager\Main'))
            return;
        
        // Extra CSS to include
        list($css_url, $version, $debug) = $this->owner->getResourceUrl('css');
        wp_enqueue_style('jquery-ui-slider', $css_url . 'vendor/jquery-ui-slider' . $debug . '.css', false, $version);
	}

    /**
     * Render the slide control
     */
	public function render_content() {
        if (!is_a($this->owner, '\Myatu\WordPress\BackgroundManager\Main'))
            return;
        
        $vars = array(
            'id'            => strtr($this->id, '-', '_'),
            'label'         => $this->label,
            'value'         => $this->value(),
            'link'          => $this->get_link(),
            'is_rtl'        => is_rtl(),
            'min'           => $this->min,
            'max'           => $this->max,
            'step'          => $this->step,
            'range'         => $this->range,
            'reverse'       => $this->reverse,
            'show_value'    => $this->show_value,
            'left_label'    => $this->left_label,
            'right_label'   => $this->right_label,
        );
        
        $this->owner->template->display('customizer_slide_control.html.twig', $vars);
	}
}