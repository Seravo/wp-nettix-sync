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

In Finland most car and boat sellers have their vehicles listed in the nettiauto.fi, nettivene.fi and similar services. Such sellers often also want to list those items on their own website as well. With this plugin one can import automatically all listings from the NettiX database.

This plugin is fully open source and can be used for free anywhere.

== Installation ==

1. Download and activate the plugin.
2. Define NETTIX_DEALERLIST or NETTIX_ADLIST in wp-admin
3. Request NettiX to whitelist your server IP address and allow the import to happen.
4. Installation done!

== Frequently Asked Questions ==

No FAQ section yet.

== Screenshots ==

1. Examples of settings screens

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
