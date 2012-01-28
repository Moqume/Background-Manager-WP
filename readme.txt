=== Background Manager ===
Contributors: Myatu
Tags: background, theme, photo, image, rotate, slideshow, random, flickr
Requires at least: 3.2.1
Tested up to: 3.3.1
Stable tag: 1.0.6

Display a random image as the website background at each visit or as a timed slideshow, without the need to edit the theme.

== Description ==

_Background Manager_ is a powerful replacement for the default WordPress background manager. 

It allows you to create multiple image sets, from which a random image is selected and displayed as the website background. The images can be uploaded from your local computer, selected from images available in your Media Library, or import them from other plugins and third-party sources.

With an easy to use menu, you can also define how the background image is displayed, such as full-screen (with ratio correction), tiled, fixed or scrolling, and define where the image is positioned. 

You also have the ability to add an overlay to the background images with a choice of pre-defined patterns. And of course it is also possible to add a background color.

Where supported, in full-screen mode the the background image is "eased in" when it is ready to be displayed. No longer will visitors with slower Internet connections have to endure watching a background image load from top down.

You can also limit the background images to certain parts of WordPress, for example only on the front page or a full-page post. And for each individual page or post, you can also override the images and overlay used as the background, which allows you to have pages or posts with a different background theme.

Each individual background image can also be linked to a specific URL, which allows a visitor to click anywhere on the background and be redirected to another page or website.

All this is done without the need to edit the theme or any other files!

= Demo =

Visit the [Background Manager Demo Site](http://j.mp/bgmdemo) for a live demonstration of the plugin.

= Features =

* Integration with WordPress Media Library
* Full-screen background images
* Full control over position for images in normal display mode (tiling, positioning, scrolling and stretching)
* "Ease in" of a full-screen background image
* Multiple _Image Sets_ to select a random image from
* Override the _Image Set_ for each Post, Page or Custom post type
* User defined display interval between the background images (timed slideshow)
* Optional transition effects between the different background images displayed:
    * Fade-in/Fade-out (Crossfade)
    * Slide (Top, bottom, left or right)
    * Cover (Top, bottom, left or right)
* 18 pre-defined background overlays
* Enable or disable the background images on the Front page, Error pages, Custom post types, etc.
* Optional thumbnail/information tab for the visitor to learn more about the background
* Import from various sources, such as:
   * Flickr (including license and ownership)
   * NextGEN Gallery Plugin
   * WP Flickr Background Plugin
* Define the background opacity (available in Full Screen only)
* Background image links (click-able backgrounds)
* Uses AJAX to load background images, keeping the website's footprint small and improve caching
* Graceful degradation for visitors without JavaScript

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

= 1.0.6 (January 28, 2012) =
* __Added:__ Background image links
* __Added:__ Support for overlay opacity
* __Added:__ New background overlays
    * Black and White Grid
    * Black and White Horizontal Line (dense)
    * Jeans effect
* Improved: The Slide and Cover transition effects have been improved, handling various image sizes and smaller browser windows better.
* Changed: The Preview window on the _Settings_ page will now remain in view, which allows the user to scroll the page down to additional options/settings and see any changes without having to refer back to the Preview.
* Fixed: 'Background' menu entry on front-end admin bar could potentially cause a fatal error due to unchecked use of admin-privileged function, changed to a user option storing home url instead

= 1.0 (January 21, 2012) =
* __Added:__ Support for additional transition effects for full-screen images, including the ability to disable it.
* __Added:__ Ability to select transition effect speed.
* Fixed: Background images were always scaled down to 1024 pixels.
* Fixed: 'Background' menu entry on front-end admin bar directed user to incorrect URL.
* Fixed: Under certain conditions, the fade-in of a full-screen image happened too quick after the on-ready `hide()`, causing the image to disappear.
* Fixed: Full-screen imgLoaded() (JS) event was not unbound at subsequent use, causing undesired results with transition effects.
* Changed: Using cookies instead of a PHP session to store background image(s) IDs used for the browser session, to better accomodate the EU Directive regarding non-essential cookies.
* Changed: More fluid crossfading of images

= 0.9.3 (January 14, 2012) =
* Fixed: Background overrides for individual pages and posts were not honored when 'Select a random image' was set to 'At each browser session'.
* Fixed: Under certain circumstances, PHP crashes when generating the embedded URI data for overlays, causing the
web pages not to finish rendering.
* Changed: Preview image is now centered
* __Added:__ Support for background opacity

= 0.9.1 (January 5, 2012) =

* Fixed: Overlay images not shown where 'Fileinfo' PHP extension was disabled
* __Added:__ Better handling of PHP versions older than 5.3, which before caused confusion due to cryptic error messages
* __Added:__ Support for Custom Post Types (activation and background overrides)
* Changed: Flickr imports now include the owner, license and link to the original image in the description

= 0.9 (December 30, 2011) =
* Public BETA release of the plugin

== Upgrade Notice ==

= 1.0.6 =

New: Background image links, support for overlay opacity and more overlays. Improved transition effects.

== Frequently Asked Questions ==

= Help, it's broken! What do I do now? =

If something does not appear to be working as it should, [search the forum](http://wordpress.org/tags/background-manager) or [write a new topic](http://wordpress.org/tags/background-manager#postform) that describes the problem(s) you are experiencing. 

It will be very useful to include information about the environment in which the problem occured. If you can still activate and access the __Settings__ page for the plugin, look at the bottom of the page for a __Debug__ link. Clicking it will expand a box with often requested details, such as the WordPress version and what operating system the web server is using. You can copy and paste these details when reporting a problem, which will help speed up finding a solution.

= How do my make my backgrounds click-able? =

You can redirect your visitor to a specific URL if they click anywhere on the background by setting the __Background URL__ for an image. Simply edit one of your Image Sets (__Apperance__ -> __Background__ -> __Image Sets__ --> [desired image set]), select an image and click the __Edit__ icon displayed over the image. Provide the URL in the __Background URL__ field and click __Save All Changes__.

