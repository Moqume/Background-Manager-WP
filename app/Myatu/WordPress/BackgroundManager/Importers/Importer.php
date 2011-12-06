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
class Importer
{
    const NAME = 'Name';
    const DESC = 'Description';
    
    /**
     * Protected constructor, as this is a purely static class
     */
    protected function __construct() {}
    
    /**
     * Returns whether this importer is active
     *
     * @return bool
     */
    static public function active()
    {
        return true;
    }
    
    /**
     * Returns information about the importer
     *
     * @return array An array containing a short name (`name`), description (`desc`) and whether it is active (`active`)
     */
    final static public function info()
    {        
        return array(
            'name'   => static::NAME,
            'desc'   => static::DESC,
            'active' => static::active(),
        );
    }
    
    /**
     * Returns a screen to display prior to performing the actual import
     *
     * This gives ample opportunity to request more details from the end-user
     * prior to running the import job. If this return nothing/empty, then
     * this step will be ignored.
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
        
        echo '<script type="text/javascript">/* <![CDATA[ */ mainWin.doImportProgress(' . $percentage . '); /* ]]> */</script>';
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
        /* Prevent this function from timing out, and set implicit flush on output buffer writes */
        set_time_limit(0);
        ignore_user_abort(true);
        while (ob_get_level())
            ob_end_clean();
        ob_implicit_flush(true);
        
        echo '<!DOCTYPE html><html><head></head><body><script type="text/javascript">/* <![CDATA[ */ mainWin = window.dialogArguments || opener || parent || top; /* ]]> */</script>';

        // Only perform this if actually active
        if (static::active())
            static::doImport($main);
        
        // Finalize progress
        static::setProgress(100);
        
        echo '</body></html>';
        
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