=== WooCommerce Bookings Dropdown Dates ===
Contributors: Kodr
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Display WooCommerce Bookings dates as a dropdown menu with remaining places, instead of a calendar picker.

== Description ==

This plugin enhances the WooCommerce Bookings plugin by replacing the standard calendar date picker with a user-friendly dropdown menu. Each available date shows the number of remaining places, making it easier for customers to see availability at a glance.

**Features:**

* Dropdown date selection instead of calendar picker
* Shows remaining places for each date
* Updates dynamically when resources are selected
* Mobile-friendly interface
* Better user experience for course bookings
* Compatible with latest WooCommerce Bookings

**Requirements:**

* WordPress 5.8 or higher
* WooCommerce 6.0 or higher
* WooCommerce Bookings plugin

== Installation ==

1. Ensure WooCommerce and WooCommerce Bookings are installed and activated
2. Upload the plugin folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The dropdown will automatically replace the calendar on all bookable products

== Frequently Asked Questions ==

= Does this work with all booking types? =

Yes, it works with day, night, hour, minute, and month-based bookings.

= Can I customise the date format? =

Yes, you can use WordPress filters to customise the date format and text.

= What happens if there are no available dates? =

A custom message is displayed informing users to contact you to arrange dates.

== Changelog ==

= 2.0.0 - 2025-11-05 =
* Complete rewrite with modern PHP practices
* Improved compatibility with latest WooCommerce Bookings
* Better error handling and security
* Separated concerns into multiple classes
* Added proper nonce verification
* Improved AJAX handling
* Better code documentation
* Mobile-responsive improvements

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
Major update with improved compatibility and security. Tested with latest WooCommerce Bookings.

== Support ==

For support, please contact SES Training Solutions.

== Credits ==

Created by Kodr.