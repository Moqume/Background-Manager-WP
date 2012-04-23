<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pointers;

/**
 * Adds a pointer for the 2-step introduction (Step 1 of 2)
 *
 * Initialiazed in Main\onSettingsMenuLoad()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pointers
 */
class AddNewStep1 extends \Pf4wp\Pointers\FeaturePointer
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