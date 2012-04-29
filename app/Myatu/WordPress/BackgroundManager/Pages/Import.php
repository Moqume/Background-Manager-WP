<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Pages;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * The Import page
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Pages
 * @since 1.0.39
 */
class Import
{
    protected $owner;
    protected $np_cache = array(); // Non-persistent cache

    public function __construct(Main $owner)
    {
        $this->owner = $owner;
    }

    /**
     * Obtains an array of available Importers
     *
     * @return array Array containing a list of importers, indexed by classname, containing a display name, description and namespace+class.
     */
    public function getImporters()
    {
        if (isset($this->np_cache['importers']))
            return $this->np_cache['importers'];

        $base_namespace = '\\Myatu\\WordPress\\BackgroundManager\\Importers';
        $importers      = apply_filters(Main::BASE_PUB_PREFIX . 'importers', \Pf4wp\Dynamic\Loader::get($base_namespace, $this->owner->getPluginDir() . Main::DIR_IMPORTERS));

        // Classes must be a subclass of Importers\Importer (checked, as we apply a filter)
        foreach ($importers as $importer_key => $importer)
            if (!is_subclass_of($importer['class'], $base_namespace . '\\Importer'))
                unset($importers[$importer_key]);

        // Sort importers by name
        uasort($importers, function($a, $b){ return strcasecmp($a['name'], $b['name']); });

        // Store in non-persistent cache
        $this->np_cache['importers'] = $importers;

        return $importers;
    }

    /**
     * Handles pre-Page Menu actions
     */
    public function onImportMenuLoad($current_screen)
    {
        // Check if there's a valid 'run_import_job'
        if (isset($_REQUEST['run_import_job']) && isset($_REQUEST['nonce'])) {
            if (!wp_verify_nonce($_REQUEST['nonce'], $this->owner->getName() . '_import_' . $_REQUEST['run_import_job']))
                wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));

            $importers = $this->getImporters();
            $importer  = $_REQUEST['run_import_job'];

            if (array_key_exists($importer, $importers)) {
                $class = $importers[$importer]['class'];

                // Run import job
                if (is_callable($class . '::import'))
                    $class::import($this->owner);
            }
        }

        list($js_url, $version, $debug) = $this->owner->getResourceUrl();

        add_thickbox();
        wp_enqueue_script($this->owner->getName() . '-import', $js_url . 'import' . $debug . '.js', array('jquery', $this->owner->getName() . '-functions'), $version);
    }

    /**
     * Settings Menu
     */
    public function onImportMenu($data, $per_page)
    {
        $importers      = $this->getImporters();
        $importer       = '';
        $pre_import     = '';
        $import_job_src = '';

        // If the form was submitted...
        if (isset($_REQUEST['importer']) && isset($_REQUEST['_nonce'])) {
            if (!wp_verify_nonce($_REQUEST['_nonce'], 'onImportMenu'))
                wp_die(__('You do not have permission to do that [nonce].', $this->owner->getName()));

            $importer = $_REQUEST['importer'];

            if (!array_key_exists($importer, $importers)) {
                $importer = ''; // Invalid importer specified, ignore
            } else {
                // Obtain any pre-import information from the user, if required
                $class = $importers[$importer]['class'];

                if (is_callable($class . '::preImport'))
                    $pre_import = $class::preImport($this->owner);

                // Scrub REQUEST variables, and pass them on to the import job source args
                $args = array();

                foreach ($_REQUEST as $post_key => $post_val) {
                    $post_key = preg_replace('#_desc$#', '', $post_key); // Both regular class names and descriptions are ignored

                    if (!array_key_exists($post_key, $importers) &&
                        !in_array($post_key, array('_nonce', 'page', 'sub', 'submit', 'importer', 'pre_import_done', '_wp_http_referer')))
                        $args[$post_key] = $post_val;
                }

                // Include a nonce in the import jobs source
                $import_job_src = add_query_arg(array_merge($args, array('run_import_job' => $importer, 'nonce' => wp_create_nonce($this->owner->getName() . '_import_' . $importer))));
            }
        }

        $vars = array(
            'nonce'           => wp_nonce_field('onImportMenu', '_nonce', true, false),
            'submit_button'   => get_submit_button(__('Continue Import', $this->owner->getName())),
            'importers'       => $importers,
            'importer'        => $importer,
            'show_pre_import' => (!empty($importer) && !empty($pre_import)),
            'pre_import'      => $pre_import,
            'run_import'      => (!empty($importer) && empty($pre_import)),
            'import_job_src'  => $import_job_src,
        );

        $this->owner->template->display('import.html.twig', $vars);
    }
}
