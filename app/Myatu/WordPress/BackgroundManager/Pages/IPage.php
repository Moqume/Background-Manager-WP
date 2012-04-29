<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pages;

/**
 * Page Interface
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pages
 * @since 1.0.39
 */
interface IPage
{
    /**
     * Handles pre-Page Menu actions
     *
     * Note: this is automatically called by the Menu system
     *
     * @see onMenu()
     * @param object $current_screen The current screen object
     */
    public function onMenuLoad($current_screen);

    /**
     * Settings Menu
     */
    public function onMenu($data, $per_page);
}
