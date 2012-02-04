<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Importers;

use Myatu\WordPress\BackgroundManager\Main;
use Myatu\WordPress\BackgroundManager\Common\FlickrApi;
use Myatu\WordPress\BackgroundManager\Galleries;
use Myatu\WordPress\BackgroundManager\Images;

/**
 * Importer for Flickr
 *
 * This product uses the Flickr API but is not endorsed or certified by Flickr.
 * 
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Importers
 */
class Flickr extends Importer
{
    const NAME = 'Flickr Photo Sets';
    const DESC = 'Imports photo sets at from Flickr. This product uses the Flickr API but is not endorsed or certified by Flickr.';
    
    /**
     * Check if the user has permission to add (upload) files
     */
    static public function active()
    {
        if (!current_user_can('upload_files')) 
            return false;
            
        return true;
    }
       
    /**
     * Pre-import settings
     *
     * Allows the selection of a particular gallery
     */
    static public function preImport(Main $main)
    {
        if (isset($_REQUEST['flickr_photoset']) && !empty($_REQUEST['flickr_photoset']))
            return; // A photoset has been selected
        
        $flickr = new FlickrApi($main);
        $vars   = array();
        $tokens = false;
        
        // Domain
        $domain = '';
        if (preg_match('#^http[s]?:\/\/.+?(?=\/|$)#i', get_site_url(), $matches))
            list($domain) = $matches;

        // Callback URL
        $importer     = is_array($class = explode('\\', get_class())) ? $class[count($class)-1] : 'Flickr';
        $callback_url = $domain . add_query_arg(array(
            'logout'   => false,
            'importer' => $importer,
            '_nonce'   => wp_create_nonce('onImportMenu'),
        ));
        
        // Logout URL
        $vars['logout_url'] = add_query_arg(array(
            'logout' => true,
        ), $callback_url);
       
        // Perform logout, if requested
        if (isset($_REQUEST['logout']))
            $flickr->deleteAccessTokens();            
        
        // If we do not have valid access tokens, ask the user what to do.
        if (!$flickr->hasValidAccessTokens()) {
            if (!isset($_REQUEST['do_login'])) {
                // We have not been authorized to access Flickr, except public side. Ask the user what to do.
                $vars['ask_auth'] = true;
            } else if ($do_login = ($_REQUEST['do_login'] == 'yes')) {
                // User has decided to login to Flickr
                
                $url = $flickr->getAuthorizeUrl($callback_url);
                
                if ($url) {
                    $vars['auth_redir'] = $url;
                } else {
                    // Something went wrong, repeat the process (asking what the user wants to do)
                    $vars['errors'] = __('Unable to obtain a Flickr authorization URL. Please try again later.', $main->getName());
                    $vars['ask_auth'] = true;
                }
            } else {
                // User has decided to continue anonymously, clear any invalid tokens if present
                $flickr->deleteAccessTokens();
            }
        } else {
            // Valid access tokens, we grab the tokens, which contains a username
            $tokens = $flickr->getAccessTokens();
            
            if ($tokens)
                $vars['username'] = $tokens['username'];
        }
        
        // Set anonymous flag
        $vars['anonymous'] = ($tokens === false);
        
        // Set a username from whom we obtain the photoset
        $flickr_username =  (isset($_REQUEST['flickr_username'])) ? $_REQUEST['flickr_username'] : '';
        $vars['flickr_username'] = $flickr_username;
        
        // If we have a username specified, or are using our authorized tokens...
        if ($tokens || $flickr_username) {
            $photoset_list = false;
            
            if ($tokens && (empty($flickr_username) || strcasecmp($flickr_username, $tokens['username']) == 0)) {
                // Obtain photoset list from authenticated user
                $photoset_list = $flickr->call('photosets.getList');
            } else {
                // Obtain photoset list from another user
                $flickr_id = false;
                
                if (!strpos($flickr_username, '@')) { // @ cannot be the first match, so safe to use a "!"
                    // Obtain NSID by Username
                    $flickr_id_result = $flickr->call('people.findByUsername', array('username' => $flickr_username));
                    
                    if ($flickr->isValid($flickr_id_result) && isset($flickr_id_result['user']['nsid']))
                        $flickr_id = $flickr_id_result['user']['nsid'];
                } else {
                    // Obtain NSID by ID
                    $flickr_id_result = $flickr->call('people.getInfo', array('user_id' => $flickr_username));

                    if ($flickr->isValid($flickr_id_result) && isset($flickr_id_result['person']['nsid']))
                        $flickr_id = $flickr_id_result['person']['nsid']; // This is redundant, I know
                }
                
                if ($flickr_id) {
                    $photoset_list = $flickr->call('photosets.getList', array('user_id' => $flickr_id));
                } else {
                    $vars['errors'] = sprintf(__('"%s" is not a valid Flickr user.', $main->getName()), $flickr_username);
                }
            }
            
            if ($flickr->isValid($photoset_list) && isset($photoset_list['photosets'])) {
                // Flickr reserves the option to return paginated results.
                
                if (isset($photoset_list['photosets']['photoset']) && is_array($photoset_list['photosets']['photoset']))
                    foreach($photoset_list['photosets']['photoset'] as $photoset)
                        $vars['photosets'][$photoset['id']] = sprintf('%s (%d)', $photoset['title']['_content'], $photoset['photos']);
                
                // Sort the array
                if (isset($vars['photosets']))
                    asort($vars['photosets']);
            }
        }
        
        return $main->template->render('importer_flickr.html.twig', $vars);
    }
       
    /**
     * Performs the import from Flickr
     *
     * @param object $main The object of the Main class
     */
    static public function doImport(Main $main)
    {
        // Just in case
        if (!isset($_REQUEST['flickr_photoset']) || empty($_REQUEST['flickr_photoset']))
            return;
            
        $galleries = new Galleries($main);
        $images    = new Images($main);            
        $flickr    = new FlickrApi($main);        
        
        // Create local Image Set
        if ($flickr->isValid($photoset_info = $flickr->call('photosets.getInfo', array('photoset_id' => $_REQUEST['flickr_photoset'])))) {
            $image_set  = sprintf(__('%s (Imported)', $main->getName()), $photoset_info['photoset']['title']['_content']);
            $gallery_id = $galleries->save(0, $image_set, $photoset_info['photoset']['description']['_content']);
            
            if (!$gallery_id) {
                $main->addDelayedNotice(sprintf(__('Unable to create Image Set <strong>%s</strong>', $main->getName()), $image_set), true);
                return;
            }               
        } else {
            $main->addDelayedNotice(__('Invalid or inaccessible Flickr Photo Set selected', $main->getName()), true);
            return;
        }
        
        $page      = 1;
        $pb_chunk  = 0;
        $failed    = 0;
        
        // Iterate photos on Flickr
        while ($flickr->isValid($photoset = $flickr->call('photosets.getPhotos', array('photoset_id' => $_REQUEST['flickr_photoset'], 'media' => 'photos', 'page' => $page))) && isset($photoset['photoset'])) {
            $photoset  = $photoset['photoset'];
            $pages     = $photoset['pages'];
            $total     = $photoset['total'];
            $pb_chunks = ceil(100 / $total -1); // For progress bar
            
            // Iterate each photo in current 'page'
            foreach ($photoset['photo'] as $photo) {
                $image_url    = '';
                $description  = '';
                $title        = $photo['title'];
                $can_download = true;
                
                // Attempt to obtain additional information about the photo, including the license
                if ($flickr->isValid($info = $flickr->call('photos.getInfo', array('photo_id' => $photo['id'], 'secret' => $photo['secret']))) && isset($info['photo'])) {
                    $info         = $info['photo'];
                    $license_info = $flickr->getLicenseById($info['license']); // Obtain license details
                    $can_download = ($info['usage']['candownload'] == 1);                    
                    $description  = sprintf(__('<p>%s</p><p>By: <a href="http://www.flickr.com/photos/%s/%s/">%s</a> (%s)</p>', $main->getName()),
                        $info['description']['_content'],
                        $info['owner']['nsid'],     // User ID
                        $info['id'],                // Photo ID
                        $info['owner']['username'], // Username
                        (!empty($license_info['url'])) ? sprintf('<a href="%s">%s</a>', $license_info['url'], $license_info['name']) : $license_info['name']
                    );
                }
                
                // Select the largest size available to us
                if ($can_download && $flickr->isValid($sizes = $flickr->call('photos.getSizes', array('photo_id' => $photo['id'])))) {
                    $current_w = 0;
                    $current_h = 0;
                    
                    foreach($sizes['sizes']['size'] as $size) {
                        if ($size['width'] > $current_w || $size['height'] > $current_h) {
                            $image_url = $size['source'];
                            $current_w = $size['width'];
                            $current_h = $size['height'];
                        }
                    }
                }
                
                // If we have an URL, download it and insert the photo into the local Image Set
                if (!empty($image_url)) {
                    if (!Images::importImage($image_url, $gallery_id, $title, $description))
                        $failed++;
                }
                
                // Update progress bar
                $pb_chunk++;
                static::setProgress($pb_chunk * $pb_chunks);
            }
            
            // Go to next page of photos on Flickr
            if ($page < $pages) {
                $page++;
            } else {
                break;
            }
        }
        
        if ($failed > 0)
            $main->addDelayedNotice(sprintf(__('%d photos could not be added.', $main->getName()), $failed), true);
        
        $main->addDelayedNotice(__('Completed import from Flickr', $main->getName()));
    }
}