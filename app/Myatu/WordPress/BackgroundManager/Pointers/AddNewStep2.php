<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pointers;

/**
 * Adds a pointer for the 2-step introduction (Step 2 of 2)
 *
 * Initialized in Main\onGalleriesMenuLoad()
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pointers
 */
class AddNewStep2 extends \Pf4wp\Pointers\FeaturePointer
{
    protected $selector = '#add_add_image';
    protected $position = array('edge' => 'left');

    public function onBeforeShow($textdomain)
    {
        $this->setContent(
            __('<p>Click on this icon to start adding some images.</p><p>Save using the <strong>Add Image Set</strong> button. Don\'t forget to add a title!</p>', $textdomain),
            __('Add Some Images!', $textdomain)
        );
    }    
}