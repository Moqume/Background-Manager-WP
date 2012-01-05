=== Background Manager ===
Contributors: Myatu
Tags: background, theme, photo, image, rotate, slideshow, random, flickr
Requires at least: 3.2.1
Tested up to: 3.3.1
Stable tag: 0.9.1

Display a random image as the website background at each visit or as a timed slideshow, without the need to edit the theme.

== Description ==

_Background Manager_ is a powerful replacement for the default WordPress background manager. 

It allows you to create multiple image sets, from which a random image is selected and displayed as the website background. The images can be uploaded from your local computer, selected from images available in your Media Library, or import them from other plugins and third-party sources.

With an easy to use menu, you can also define how the background image is displayed, such as full-screen (with ratio correction), tiled, fixed or scrolling, and define where the image is positioned. 

You also have the ability to add an overlay to the background images with a choice of pre-defined patterns. And of course it is also possible to add a background color.

Where supported, in full-screen mode the the background image is "eased in" when it is ready to be displayed. No longer will visitors with slower Internet connections have to endure watching a background image load from top down.

You can also limit the background images to certain parts of WordPress, for example only on the front page or a full-page post. And for each individual page or post, you can also override the images and overlay used as the background, which allows you to have pages or posts with a different background theme.

All this is done without the need to edit the theme or any other files!

= Features =

* Integration with WordPress Media Library
* Full-screen background images
* Full control over position for images in normal display mode (tiling, positioning, scrolling and stretching)
* "Ease in" of a full-screen background image
* Multiple _Image Sets_ to select a random image from
* Override the _Image Set_ for each Post, Page or Custom post type
* User defined display interval between the background images (timed slideshow)
* Fade-in/fade-out effect between the different background images displayed
* Pre-defined background overlays
* Enable or disable the background images on the Front page, Error pages, Custom post types, etc.
* Optional thumbnail/information tab for the visitor to learn more about the background
* Import from various sources, such as:
   * Flickr (including license and ownership)
   * NextGEN Gallery Plugin
   * WP Flickr Background Plugin

= License =

[GNU GPL version 3](http://www.gnu.org/licenses/gpl-3.0.txt)

This product uses the Flickr API but is not endorsed or certified by Flickr.

== Installation ==

1. Upload the contents of the ZIP file to the `/wp-content/plugins/` directory
1. Activate the plugin through the __Plugins__ menu in WordPress
1. Access the plugin via __Appearance__ -> __Background__ menu

Additional help is provided via the _Help_ tabs within the plugin

= Requirements =

* PHP version _5.3_ or better
* WordPress version _3.2.1_ or better

A browser with Javascript enabled is highly recommended. This plugin will ___NOT___ work
with PHP versions older than 5.3.

== Screenshots ==

1. A full-screen background behind the TwentyEleven theme, with the a thumbnail preview in the lower-left corner
2. Editing an Image Set within Background Manager

== Changelog ==

= 0.9.1 (January 5, 2012) =

* Fixed: Overlay images not shown where 'Fileinfo' PHP extension was disabled
* Added: Better handling of PHP versions older than 5.3, which before caused confusion due to cryptic error messages
* Added: Support for Custom Post Types (activation and background overrides)
* Changed: Flickr imports now include the owner, license and link to the original image in the description

= 0.9 (December 30, 2011) =
* Public BETA release of the plugin

