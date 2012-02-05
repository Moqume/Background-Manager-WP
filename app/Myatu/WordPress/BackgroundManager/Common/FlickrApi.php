<?php

/*
 * Copyright (c) 2010-2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Myatu\WordPress\BackgroundManager\Common;

use Myatu\WordPress\BackgroundManager\Main;

/**
 * Common library for the Flickr API
 *
 * This product uses the Flickr API but is not endorsed or certified by Flickr.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package BackgroundManager
 * @subpackage Common
 */
class FlickrApi
{
    protected $owner;
    protected $licenses = array();
    
    // Flickr URLs
    const FLICKR_TOKEN_URL  = 'http://www.flickr.com/services/oauth/request_token';
    const FLICKR_AUTH_URL   = 'http://www.flickr.com/services/oauth/authorize';
    const FLICKR_ACCESS_URL = 'http://www.flickr.com/services/oauth/access_token';
    const FLICKR_REST_URL   = 'http://api.flickr.com/services/rest';
    
    // API keys
    protected $key    = '70db4569f5dfe41200253065e8cd9c9f';
    protected $secret = '9dc88e8ed3e17d54';    
    
    /** Constructor */
    public function __construct(Main $owner)
    {
        $this->owner  = $owner;
    }
    
    /**
     * Start authorization process for Flickr
     *
     * Note: This will reset the access tokens!
     *
     * @param string $callback_url Callback URL
     * @return bool|string Returns the URL where to authorize the user (redirect to), or false on error
     */
    public function getAuthorizeUrl($callback_url = 'oob')
    {
        try {
            $consumer = new \HTTP_OAuth_Consumer($this->key, $this->secret);
            $consumer->getRequestToken(static::FLICKR_TOKEN_URL, $callback_url);
        } catch (\Exception $e) {
            return false;
        }
        
        $tokens = array(
            'token_type'   => 'request',
            'token'        => $consumer->getToken(),
            'token_secret' => $consumer->getTokenSecret(),
        );
        
        $this->owner->options->flickr_api = $tokens;
        
        $auth_url = $consumer->getAuthorizeUrl(static::FLICKR_AUTH_URL, array('perms' => 'read')); // Read Only
        
        unset($consumer); // Clear
        
        return $auth_url; 
    }
    
    /**
     * Deletes the access tokens
     */
    public function deleteAccessTokens()
    {
        $this->owner->options->flickr_api = null;
    }
    
    /**
     * Get access tokens for Flickr
     *
     * @return bool|string Returns the access tokens, or false if autorization process has not been started yet
     */
    public function getAccessTokens()
    {
        $tokens = $this->owner->options->flickr_api;
        
        // Check for valid tokens
        if (!is_array($tokens) || empty($tokens) || !isset($tokens['token_type']))
            return false;
        
        // Return with tokens if we already have access tokens
        if ($tokens['token_type'] == 'access')
            return $tokens;
        
        try {
            $consumer = new \HTTP_OAuth_Consumer($this->key, $this->secret, $tokens['token'], $tokens['token_secret']);
            
            if (isset($_REQUEST['oauth_verifier'])) {
                $consumer->getAccessToken(static::FLICKR_ACCESS_URL, $_REQUEST['oauth_verifier']);
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        // Additional data is returned by Flickr, which contains username, etc.
        $data = $consumer->getLastResponse()->getDataFromBody();
        
        $tokens = array(
            'token_type'   => 'access',
            'token'        => $consumer->getToken(),
            'token_secret' => $consumer->getTokenSecret(),
            'fullname'     => $data['fullname'],
            'user_nsid'    => $data['user_nsid'],
            'username'     => $data['username']
        );
        
        $this->owner->options->flickr_api = $tokens;
        
        unset($consumer); // Clear
        
        return $tokens;
    }
    
    /**
     * Verifies if valid Access Tokens are stored
     *
     * @returns bool
     */
    public function hasValidAccessTokens()
    {
        $valid  = false;
        $tokens = $this->getAccessTokens();
        
        // Don't bother performing a test login if tokens aren't there
        if (is_array($tokens) && isset($tokens['token_type']) && $tokens['token_type'] == 'access')
            // Perform a test login, to see if the tokens are still valid (ie., user did not revoke access)
            $valid = $this->isValid($this->call('test.login'));
        
        return $valid;
    }
    
    /**
     * Checks if the returned values are valid
     *
     * @see errorMessage()
     * @param mixed $results Results returned by a Flickr call
     * @return bool Returns true if valid, false otherwise
     */
    public function isValid($results)
    {
        if ($results)
            return (isset($results['stat']) && $results['stat'] == 'ok');
            
        return false;
    }
    
    /**
	 * Makes an Flickr call
     *
     * The Flickr call is either made anoymously - if there are no access tokens - or OAuth signed.
	 *
	 * @param string $flickr_func The Flickr function to call
	 * @param array $flickr_args Optional array containing the parameters for the function call
     * @param bool $force_anonymous Forces the call to be made anonymously (unsigned)
	 * @return mixed|bool Will return false if there was an error, data specific to the function otherwise
	 */	
	public function call($flickr_func, $flickr_args = array(), $force_anonymous = false)
    {
        $tokens = $this->getAccessTokens();
        
        $full_args = array_merge((array)$flickr_args, array(
            'method' => 'flickr.' . $flickr_func,
            'api_key' => $this->key,
            'format' => 'php_serial'
        ));
        
        if (is_array($tokens) && isset($tokens['token_type']) && $tokens['token_type'] == 'access' && !$force_anonymous) {
            // Signed call (OAuth)
            $result   = false;
            $consumer = new \HTTP_OAuth_Consumer($this->key, $this->secret, $tokens['token'], $tokens['token_secret']);
            
            try {
                $response = $consumer->sendRequest(static::FLICKR_REST_URL, $full_args);
                
                if ($response instanceof \HTTP_OAuth_Consumer_Response)
                    $result = @unserialize($response->getResponse()->getBody());
                
            } catch (\Exception $e) {}
            
            unset($consumer); // No longer needed
            
            return $result;
        } else {
            // Unsigned call
            $result = wp_remote_get(add_query_arg($full_args, static::FLICKR_REST_URL) );

            if ( is_wp_error($result) ) {
                return false;
            } else { 
                return @unserialize(wp_remote_retrieve_body($result));
            }
        }
    }
	/**
	 * Returns a user-friendly error message based on the call() results
	 *
	 * @param array $results The Flickr results to check for errors
	 * @return string A string containing a user-friendly error message or empty if no error
	 * @see call()
	 */
	public function errorMessage($results)
    {
		if ($results === false)
			return __('There was a problem contacting Flickr or the request was not authorized. Please try again.', $this->owner->getName());
			
		if (!array_key_exists('stat', $results))
			return __('Malformed results received from Flickr. Please try again.', $this->owner->getName());
				
		if ($results['stat'] != 'ok') {
			switch ($results['code']) {
				case 1:
					return __('The photo could not be found or has been removed from public view.', $this->owner->getName()); break;
					
				case 2:
					return __('You do not have permission to view this photo.', $this->owner->getName()); break;
                    
                case 98:
                    return __('The login details or authentication token passed were invalid.', $this->owner->getName()); break;
                    
                case 99:
                    return __('This requires user authentication but no user was not logged in, or the authenticated method call did not have the required permissions.', $this->owner->getName()); break;
					
				case 100:
					return __('There was a problem with the API Key. Please contact the plugin author.', $this->owner->getName()); break;
				
				case 105:
					return __('Flickr is currently too busy. Please try again later.', $this->owner->getName()); break;
					
				case 111:
					return __('Flickr does not support the requested format. Please contact the plugin author.', $this->owner->getName()); break;
					
				case 112:
					return __('Flickr does not support the requested function. Please contact the plugin author.', $this->owner->getName()); break;
					
				default:
					return sprintf( __('Flickr reported <i>"%s"</i>.', $this->owner->getName()), esc_attr($results['message']) ); break;
			}
		}
		
		return '';
	}
    
    /**
     * Obtains license name and URL based on ID
     * 
     * @param integer $id ID of License
     * @return array Array containing name and URL of license, if any.
     */
    public function getLicenseById($id)
    {
        $result = array('name' => '', 'url' => '');
        
        if (empty($this->licenses)) {
            if ($this->isValid($licenses = $this->call('photos.licenses.getInfo')) && isset($licenses['licenses']['license']))
            $this->licenses = $licenses['licenses']['license'];
        }
        
        foreach ($this->licenses as $license) {
            if (isset($license['id']) && $license['id'] == $id) {
                $result = array_merge($result, $license);
                break; // Matching license found
            }
        }
        
        return $result;
    }    
}