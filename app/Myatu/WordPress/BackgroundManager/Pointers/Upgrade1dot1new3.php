<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pointers;

/**
 * Adds a pointer to introduce the 'Color' option in overrides in version 1.1
 *
 * Initialized in Main\onSettingsMenuLoad()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pointers
 */
class Upgrade1dot1new3 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#background_cat_color';
    protected $position = array('edge' => 'right');

    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p><strong>New in version 1.1!</strong></p><p>Now you can change the backgrond color when overriding a Category or Tag, too!</p>', $textdomain),
            __('Add some color', $textdomain)
        );
    }    
}