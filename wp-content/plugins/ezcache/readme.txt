=== ezCache ===
Contributors: upress, ilanf
Tags: upress,hosting,cache,speed,boost
Requires PHP: 5.6
Requires at least: 4.6
Tested up to: 6.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

EzCache is an easy and innovative cache plugin that will help you significantly improve your site speed.

== Description ==

EzCache is an easy and innovative cache plugin that will help you significantly improve your site speed.
The plugin comes in a simple and easy installation, without the need for advanced technical knowledge, offers you the opportunity to make your site much faster in a few simple steps, cache pages on your site, automatically optimize images using WebP format to reduce the size of your site's images by tens of percent and save You need the extra image minimization plugin.

In addition, the plugin allows you to minimize advanced HTML files, JAVA SCRIPT files
And CSS files
In the advanced settings of the extension, you can easily save advanced settings, such as:
Configure caching by page type, set cached links,
Exclude certain user types.
And of course, you can always view statistics that will always keep you updated on your site's caching performance.

We created ezCash to take the new decade's speed experience and bring it to your WordPress sites easily and quickly

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ezcache` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress

== Screenshots ==

1. Plugin main screen

== Changelog ==
= 1.6.6 =
- Update tested up to
- Added option to purge WebP images
- Update frontend dependencies
- Optimize WebP convertion process

= 1.6.5 =
- Added purge of Varnish cache

= 1.6.4 =
- Update tested up to
- Disable WebP by default

= 1.6.3 =
- Fix display of statistics

= 1.6.2 =
- Fix external JS files not being ignored while combining
- Added an exclusion for Elementor per-page JS/CSS files so that the manual exclusion is no longer required

= 1.6.1 =
- Fix problem setting up scheduled task for old cache cleanup

= 1.6.0 =
- Log only when debug is enabled
- Fixed problem with caching of campaign URLs
- Clearing homepage cache on post save will clear posts page as well
- Fixed problem with duplicate IDs when saving WebP images
- Added option to skip combining inline scripts
