<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Importers;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * Importer base class
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class Importer extends \Pf4wp\Dynamic\DynamicBase
{
    /**
     * Protected constructor, as this is a purely static class
     */
    protected function __construct() {}
    
    /**
     * Returns a screen to display prior to performing the actual import
     *
     * This gives ample opportunity to request more details from the end-user
     * prior to running the import job. If this return nothing/empty, then
     * this step will be ignored.
     *
     * The function will remain to be called otherwise, until it either returns
     * empty, or includes the following field:
     * <code>
     *   <input type="hidden" name="pre_import_done" value="true" />
     * </code>
     *
     * @return string Text/HTML to be displayed
     */
    static public function preImport(Main $main) {}
    
    /**
     * Echoes (outputs) a small JavaScript that updates the import progress
     *
     * @param int $percentage The percentage into the progress (0-100)
     */
    final static protected function setProgress($percentage)
    {
        $percentage = ($percentage > 100) ? 100 : $percentage;
        
        printf('<script type="text/javascript">/* <![CDATA[ */ mainWin.doImportProgress(%d); /* ]]> */</script>...%1$d%%', $percentage);
    }
    
    /**
     * Perform Import Job (external call)
     *
     * This is called by the Background Manager directly. Implementations
     * should use doImport() instead.
     *
     * NOTE: This function does NOT return.
     *
     * @param object $main The object of the Main class
     * @see doImport()
     */
    final static public function import(Main $main)
    {
        // Prevent this function from timing out, and set implicit flush on output buffer writes
        set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level())
            ob_end_clean();
        ob_implicit_flush(true);
        
        // Begin of HTML document, padded to force buffer flush in FF too
        echo str_pad('<!DOCTYPE html><html><head></head><body style="font-size: 10px; font-family: sans-serif;"><script type="text/javascript">/* <![CDATA[ */ mainWin = window.dialogArguments || opener || parent || top; /* ]]> */</script>', 1000);
        
        // Start count, which will be visible if JS is disabled.
        echo '0%';

        // Only perform this if actually active
        if (static::isActive())
            static::doImport($main);
        
        // Finalize progress
        static::setProgress(100);
        
        // Finish HTML document and write a JS redirect
        printf(' %s<script type="text/javascript">/* <![CDATA[ */ mainWin.location="%s"; /* ]]> */</script></body></html>', __('Done!', $main->getName()), $main->import_menu_link);
        
        die();
    }
    
    /**
     * Perform the actual import job
     *
     * @param object $main The object of the Main class
     * @see import()
     */
    static public function doImport(Main $main) {}
}