<?php
/*
Plugin Name: Background Manager
Plugin URI: http://www.myatus.co.uk/
Description: Background Manager
Version: 0.0.0.1
Author: Mike Green (Myatu)
Author URI: http://www.myatus.co.uk/
*/

/* Direct call check */

if (!function_exists('add_action')) return;

/* Bootstrap */

$_pf4wp_file = __FILE__;

require dirname(__FILE__).'/vendor/pf4wp/lib/bootstrap.php'; // use dirname()!

if (!isset($_pf4wp_check_pass) || !isset($_pf4wp_ucl) || !$_pf4wp_check_pass) return;

/* Register Namespaces */

$_pf4wp_ucl->registerNamespaces(array(
    'Symfony\\Component\\ClassLoader'   => __DIR__.'/vendor/pf4wp/lib/vendor',
    'Pf4wp'                             => __DIR__.'/vendor/pf4wp/lib',
));
$_pf4wp_ucl->registerPrefixes(array(
    'Twig_' => __DIR__.'/vendor/Twig/lib',
));
$_pf4wp_ucl->registerNamespaceFallbacks(array(
    __DIR__.'/app',
));
$_pf4wp_ucl->register();

/* Fire her up, Scotty! */

Myatu\WordPress\BackgroundManager\Main::register(__FILE__);
