<?php
/*
Plugin Name: Background Manager
Plugin URI: http://j.mp/bgmwp
Description: Background Manager allows you to display a random image as the website background at each visit or as a timed slideshow, without the need to edit the theme.
Version: 1.0
Author: Mike Green (Myatu)
Author URI: http://www.myatus.co.uk/
*/

/* Direct call check */

if (!function_exists('add_action')) return;

/* Bootstrap */

$_pf4wp_file = __FILE__;
$_pf4wp_version_check_wp = '3.2.1'; // Min version for WP

require dirname(__FILE__).'/vendor/pf4wp/lib/bootstrap.php'; // use dirname()!

if (!isset($_pf4wp_check_pass) || !isset($_pf4wp_ucl) || !$_pf4wp_check_pass) return;

/* Register Namespaces */

$_pf4wp_ucl->registerNamespaces(array(
    'Symfony\\Component\\ClassLoader'   => __DIR__.'/vendor/pf4wp/lib/vendor',
    'Pf4wp'                             => __DIR__.'/vendor/pf4wp/lib',
));
$_pf4wp_ucl->registerPrefixes(array(
    'Twig_' => __DIR__.'/vendor/Twig/lib',
    'HTTP_' => __DIR__.'/vendor/OAuth/lib',
    'Net_'  => __DIR__.'/vendor/OAuth/lib',
));
$_pf4wp_ucl->registerNamespaceFallbacks(array(
    __DIR__.'/app',
));
$_pf4wp_ucl->register();

// Additional include path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__.'/vendor/OAuth/lib');

/* Fire her up, Scotty! */

call_user_func('Myatu\\WordPress\\BackgroundManager\\Main::register', __FILE__);
