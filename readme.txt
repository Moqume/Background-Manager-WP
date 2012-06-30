=== Background Manager ===
Contributors: Myatu
Donate link: http://pledgie.com/campaigns/16906
Tags: background, theme, photo, image, rotate, slideshow, random, flickr
Requires at least: 3.2.1
Tested up to: 3.4.1
Stable tag: 1.1.6.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Display a random image as the website background at each visit or as a timed slideshow, without the need to edit the theme.

== Description ==

_Background Manager_ is a powerful replacement for the default WordPress background manager.

It allows you to create multiple image sets, from which an image is selected and displayed as the website background. The images can be uploaded from your local computer, selected from the images available in your Media Library, or import them from other plugins and third-party sources.

With an easy to use menu, you can also define how the background image is displayed, such as full-screen (with ratio correction), tiled, fixed or scrolling, define where the image is positioned and how a background image is selected (random or in sequential order).

You also have the ability to add an overlay to the background images with a choice of pre-defined patterns. And of course it is also possible to add a background color.

Where supported, in full-screen mode the the background image is optionally "eased in" when it is ready to be displayed. No longer will visitors with slower Internet connections have to endure watching a background image load from top down.

You can also limit the background images to certain parts of WordPress, for example only on the front page or a full-page post. And for each individual page, post, category or tag, you can also override the images, overlay or color used as the background, which allows you to have pages or posts with a different background theme.

Each individual background image can also be linked to a specific URL, which allows a visitor to click anywhere on the background and be redirected to another page or website. Impressions and clickthroughs of clickable backgrounds can optionally be be tracked via Google Analytics.

All this is done without the need to edit the theme or any other files!

= Demo =

Visit the [Background Manager Demo Site](http://j.mp/bgmdemo) for a live demonstration of the plugin.

= Features =

* Integration with WordPress Media Library
* Full support for the WordPress Theme Customizer
* Full-screen background images
* Full control over position for images in normal display mode (tiling, positioning, scrolling and stretching)
* Optional "Ease in" of a full-screen background image
* Multiple _Image Sets_ to select a random or sequential image from
* Override the _Image Set_ for each Post, Page, Custom post type or by Tag(s) or Category
* User defined display interval between the background images (timed slideshow)
* Optional transition effects between the different background images displayed:
    * Fade-in/Fade-out (Crossfade)
    * Slide (Top, bottom, left or right)
    * Cover (Top, bottom, left or right)
    * Fade-in and Zoom
    * CSS3 transitions: Bars, Zip, Blinds, Swipe, Random Blocks, Sequential Blocks, Concentric and Warp
* 18 pre-defined background overlays
* Enable or disable the background images on the Front page, Error pages, Custom post types, etc.
* Optional thumbnail/information tab for the visitor to learn more about the background
* Import from various sources, such as:
    * [Flickr](http://www.flickr.com) (including license and ownership)
    * [NextGEN Gallery](http://wordpress.org/extend/plugins/nextgen-gallery/) Plugin
    * [GRAND FlAGallery](http://wordpress.org/extend/plugins/flash-album-gallery/) Plugin
    * [WP Flickr Background](http://wordpress.org/extend/plugins/wp-flickr-background/) Plugin
    * A directory (and optionally its sub-directories) on the web server
* Define the background opacity (available in Full Screen only)
* Background image links (click-able backgrounds)
* Track background clicks and impressions via Google Analytics
* Uses AJAX to load background images, keeping the website's footprint small and improves caching
* Graceful degradation for visitors without JavaScript and older browsers
* Option to add a "Pin It" [Pinterest](http://www.pinterest.com) button

_This product uses the Flickr API but is not endorsed or certified by Flickr._

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

1. A full-screen background behind the TwentyEleven theme
2. The main settings of Background Manager
3. Editing an Image Set within Background Manager

== Changelog ==

= 1.1.6.1 (June 30, 2012) =
* Fixed: For some themes, the background image group would override screen elements (menus, links) due to missing z-index

= 1.1.6 (June 30, 2012) =
* __Added__: Option to remember last displayed image for subsequent page views
* Fixed: Background links were not opened in a new window, as defined by the user
* Fixed: Non-fatal error when adding image to Image Set, related to a missing URL field
* Fixed: Pinterest button updated caused the browser history to be filled
* Fixed: Image group had incorrect positioning, which caused the background image from appearing

= 1.1.1 (June 14, 2012) =
* Fixed: A bug managed its way past testing, causing background overrides to stop working.

= 1.1 (June 14, 2012) =
* __Added:__ Support for WordPress 3.4 Theme Customizer
* __Added:__ Automatically detects 3rd party categories for _Category Override_ meta option, ie. [WP e-Commerce](http://wordpress.org/extend/plugins/wp-e-commerce/)
* __Added:__ Ability to re-adjust and optionally center large images to fit the browser window, whilst maintaining ratio
* __Added:__ Meta option to overide the background image link (with shortcode support)
* __Added:__ Importer for [GRAND FlAGallery](http://wordpress.org/extend/plugins/flash-album-gallery/).
* __Added:__ Option to overide the color in Posts, Pages, Categories and Tags
* __Added:__ Ability to remove (detach) images from an Image Set, keeping the image in the Media Library
* __Added:__ Ability to change the order of images in an _Image Set_
* __Added:__ In addition to selecting an image from an _Image Set_ at random, sequential (ascending/descending) selection is now possible too
* __Added:__ Ability to select which roles are able to override the background Image Set, Overlay and Color for individual Posts and Pages
* __Added:__ Option to allow the user to enable/disable the initial image ease-in
* __Added:__ 9 new transitions, 8 of which are adaptations of [Flux Slider](http://www.joelambert.co.uk/flux/)
* __Added:__ Support for tracking background clicks and impressions via Google Analytics
* Changed: Vendor libraries for Pf4wp and Twig updated to latest versions (1.0.10 and 1.7 respectively), minor change in public-side JS
* Changed: Increased maximum image transition speed limit from 7500ms to 15000ms
* Changed: Decreased minimum permitted change frequency from 10 seconds to 1 second
* Changed: Background image details are now loaded asynchronous, to prevent browser "blocking"
* Changed: Background image is now rendered by JS directly, unless JS is disabled, to avoid "flicker"
* Changed: Background image is no longer printed
* Fixed: Minimum background image change interval was not added to Javascript
* Fixed: Individual page settings were ignored if the page was used as a Posts Page (in Reading)
* Fixed: Background images were not click-able if no info tab was present or not in full screen mode
* Fixed: For Image Sets with a single image, the transition effect would still be applied
* Fixed: Issue where embeded overlay image had missing mime types, or the mime type detection caused a fatal error

= 1.0.25 (March 18, 2012) =
* Fixed: Resolved the "flickering" before each transition

= 1.0.24 (March 4, 2012) =
* Changed: Image ratios are now retained, regardless of their width
* Fixed: When a static page for the front page using "Posts as page" was set, it would not display the background on either that page nor the front page.

= 1.0.22.1 (February 20, 2012) =
* Fixed: MSIE encountered Javascript runtime errors due to non-closure of object/array elements

= 1.0.22 (February 18, 2012) =
* __Added:__ Support for the [Pinterest](http://www.pinterest.com) "Pin It" button
* Changed: Replaced bt (jQuery BalloonTip) in favor of qTip2
* Changed: If no image caption is specified, the title will be used instead
* Changed: Updated Pf4wp and Twig vendor libraries

= 1.0.18 (February 12, 2012) =
* Changed: Added Categories and Tags columns to Image Set/Trash listings
* Changed: Tag and Category overrides now also apply to their respective archive pages
* Changed: Individual Post overrides now take priority over Tag or Category overrides
* Fixed: Minor error where there was no test for btOff() in public script before using

= 1.0.14 (February 6, 2012) =
* __Added:__ Allow overriding the background _Image Set_ and/or _Overlay_ by the post's _Tag(s)_ or _Category_
* __Added:__ Ability to download an image directly from an external source (URL) to the Image Set/Media Library, with support for Flickr.
* __Added:__ Ability to copy images from the Media Library already attached to other posts, pages or image sets.
* __Added:__ Importer for (sub)directories on the web server
* Changed: Extra user capability checks for Importers
* Fixed: Not all Image Sets were shown in the Settings (system dependent)
* Fixed: Flickr Importer authorization and logout URLs
* Fixed: 'Add to Image Set' button was missing from Media Library when adding images to an Image Set

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

= 1.1.6.1 =
An upgrade to this version is only required if certain screen elements, such as links or menus, are "missing"

= 1.1 =
Version 1.1 introduces many new features and changes. Before upgrading, it is highly recommended to visit the official website and read about the changes and how they may impact your website.

= 1.0.14 =
New: Override Image Sets by Post Tags or Categories; Download Images by URL (with Flickr support); Copy existing Media Library images; Import a local (server) directory

= 1.0.6 =

New: Background image links, support for overlay opacity and more overlays. Improved transition effects.

== Frequently Asked Questions ==

= Help, it's broken! What do I do now? =

If something does not appear to be working as it should, [search the WordPress Support Forum](http://wordpress.org/support/plugin/background-manager) if there may be a solution, or write a new topic that describes the problem(s) you are experiencing.

It will be very useful to include information about the environment in which the problem occured. If you can still activate and access the __Settings__ page for the plugin, look at the bottom of the page for a __Debug__ link. Clicking it will expand a box with often requested details, such as the WordPress version and what operating system the web server is using. You can copy and paste these details when reporting a problem, which will help speed up finding a solution.

= How do my make my backgrounds click-able? =

You can redirect your visitor to a specific URL if they click anywhere on the background by setting the __Background URL__ for an image. Simply edit one of your Image Sets (__Apperance__ -> __Background__ -> __Image Sets__ --> [desired image set]), select an image and click the __Edit__ icon displayed over the image. Provide the URL in the __Background URL__ field and click __Save All Changes__.

= Can I track background impressions and clicks? =

Yes, starting with version 1.1, any click-able background image that is shown or clicked on can be tracked using Google Analytics. They will appear as Google Analytics Events, which can also be used for Goals. The _Help_ tab on the main _Settins_ page will describe this in more detail.

= How do I change the background, overlay or color for individual posts or pages? =

To override the default Image Set or overlay used as the background, edit the desired page or post and look for the __Background__ box, which is usually located under the large text editor.

If this box is not visible, ensure that it is enabled by clicking the __Screen Options__ tab in the upper right corner, and under the __Show on screen__ heading select/tick the __Background__ option.

You will be able to select any of your existing Image Sets, as well as a different overlay, or disable either entirely.

= How do I override by Category or Tag? =

Edit the desired Image Set (__Apperance__ -> __Background__ -> __Image Sets__ --> [desired image set]) and select a Category or Tag in the corresponding __Override by Category__ or __Override by Tag__ boxes.

If the box is not visible, ensure that it is enabled by clicking the __Screen Options__ tab in the upper right corner, and under the __Show on screen__ heading select/tick the __Override by Category__ and/or __Override by Tag__ options.

Once the Image Set has been saved, it will override the selected Category/Tag.

= How do I display an Image Set in order instead of random? =

This option is only available if a new background image is select every few seconds (see __Settings__ -> _Select an Image_ option). This will give you the added option of displaying an Image Set in ascending or descending order.

= How can I change the order images are displayed? =

First ensure that a background is selected every few seconds (see above). Edit the desired Image Set and highlight an image using the mouse or by using the cursor keys on your keyboard. Buttons will overlay the highlighted image, allowing you to edit, delete/remove and move the image one position left or right.

To move more than one image at a time, double click or press SPACE on your keyboard to select the images. A set of buttons will appear at the top of window containing the images, which give you the option to move the selected images left or right.

Note: These changes take effect immediately!

= I have a PHP version older than 5.3, can I make it work? =

This plugin makes use of many features introduced in PHP version 5.3, and an attempt to make it work with older versions of PHP is equivalent to a complete rewrtie of the plugin.

Many hosting providers are already providing PHP 5.3+ to their customers, and others allow for an easy upgrade. Also consider that PHP 5.3 was first released in 2009 and fixes many bugs and security issues, and support for PHP 5.2 was [stopped in 2010](http://www.php.net/archive/2010.php#id2010-12-09-1).

= How can I upgrade to PHP version 5.3? =

This depends. If you have your very own server, then this is Operating System specific and you will need to consult its documentation on how to upgrade. Most commonly in Linux environments this consists of running `apt-get`, `yum` or `pacman` from the CLI.

If you are using a web hosting provider, then you need to contact the provider regarding this. Some can move your website to a different server with a newer version of PHP 5.3, while others make it as simple as adding/changing a line in the `.htaccess` file or a setting in the control panel. For example:

* 1&1 Webhosting: Add `AddType x-mapp-php6 .php` to the `.htaccess` file
* OVH: Add `SetEnv PHP_VER 5_3` or `SetEnv PHP_VER 5_TEST` to the `.htaccess` file
* GoDaddy Linux Shared Hosting: Add `AddHandler x-httpd-php5-3 .php` to the `.htaccess` file
* GoDaddy 4GH Hosting: Visit GoDaddy's __Hosting Control Center__ -> __Content__ -> __Programming Languages__
* HostGator: Add `Action application/x-hg-php53 /cgi-sys/php53` and `AddHandler application/x-hg-php53 .php` to the `.htaccess` file
* Bluehost: Add `AddHandler application/x-httpd-php53 .php` to the `.htaccess` file (Note: may require a support request/ticket to enable PHP 5.3)
