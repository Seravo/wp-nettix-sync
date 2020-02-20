=== WP NettiX Sync ===
Contributors: Zuige, elguitar, ottok
Tags: nettix, sync, import
Donate link: https://seravo.com/
Requires at least: 4.0
Tested up to: 4.9.5
Requires PHP: 5.6.0
Stable tag: trunk
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

WP NettiX Sync automatically imports data from NettiX sites like nettiauto.fi and nettivene.fi.

== Description ==

**DEPRECATION NOTICE:** NettiX Oy has shut down their API which this plugin was using. Therefore this plugin no longer works. This plugin is currently not used by any customer's of Seravo and there are no plans to rewrite the whole plugin for any alternative APIs (which might not even exist).

**If you are using this plugin, please uninstall it.**

*Do not install this plugin on any new sites. It will soon be removed from WordPress.org as it has become obsolete.*

== Changelog ==

= 2.0 =
* Release plugin as production quality and submit to WordPress.org
* Tidy up the codebase
* Redefine the sync interval to one hour
* Add support for Nettivene
* Add settings page to wp-admin
* Change the name to not start with a registered trademark (WordPress)

= 1.0 =
* Initial release

== Upgrade Notice ==

= 2.0 =
Storing posts blindly with JSON can now be done more easily but it will not be tested in the future. Use at your own risk!
