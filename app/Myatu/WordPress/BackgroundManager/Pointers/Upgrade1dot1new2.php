<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pointers;

/**
 * Adds a pointer to introduce the 'Adjust Image Size' option in version 1.1
 *
 * Initialized in Main\onSettingsMenuLoad()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pointers
 */
class Upgrade1dot1new2 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#full_screen_adjust_fs';
    protected $position = array('align' => 'left');

    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p><strong>New in version 1.1!</strong></p><p>Large images can now be automatically adjusted to the size of the browser window, while maintianing the aspect ratio.</p>', $textdomain),
            __('Automatic Image Adjusting', $textdomain)
        );
    }    
}