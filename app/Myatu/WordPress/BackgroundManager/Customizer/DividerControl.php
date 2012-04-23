<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Myatu\WordPress\BackgroundManager\Customizer;

/**
 * Provides a sub-divider
 *
 * @since 1.0.30
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Customizer
 */
class DividerControl extends \WP_Customize_Control
{
    public $type = 'divider';
    public $label = '';
    public $owner;

    /**
     * Render the slide control
     */
	public function render_content() {
        if (!is_a($this->owner, '\Myatu\WordPress\BackgroundManager\Main'))
            return;
            
        $this->owner->template->display('customizer_divider_control.html.twig', array('label' => $this->label));
	}
}