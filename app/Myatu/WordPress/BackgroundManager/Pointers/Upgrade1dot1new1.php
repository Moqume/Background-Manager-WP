<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pointers;

/**
 * Adds a pointer to introduce the new Random/Sequential display order in version 1.1
 *
 * Initialized in Main\onSettingsMenuLoad()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pointers
 */
class Upgrade1dot1new1 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#image_sel';
    protected $position = array('align' => 'left');

    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p><strong>New in version 1.1!</strong></p><p>Now you can select the order in which background images are displayed.</p>', $textdomain),
            __('Random or sequential?', $textdomain)
        );
    }    
}