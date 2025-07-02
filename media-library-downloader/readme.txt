=== Media Library Downloader ===
Contributors: devloper00
Donate link: https://ko-fi.com/devloper
Tags: library, media, files, download, downloader, WordPress
Requires at least: 5.0 or higher
Tested up to: 6.6.1
Requires PHP: 5.6
Stable tag: 1.3.3
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Download multiples files / media in one click, from the WordPress media library

== Description ==

Natively WordPress doesn't offer the possibility to download files directly from the WordPress media library. With that simple extension you can download any files you need!

= Main features: =

* Download single / multiple files
* Compatible with List / Grid view
* AJAX Method (No reload)

== Installation ==

1. Upload the `media-library-downloader` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 1.3.3 =
* Fix bug where download button where disabled

= 1.3.2 =
* Fix vulnerabilities regarding access control

= 1.3.1 =
* Fix bug where download button where disabled

= 1.3 =
* Fix bug where file are empty
* Refacto code with vanilla javascript

= 1.2.2 =
* Add dismissible notice on dashboard
* Remove init hook to check_requirements

= 1.2.1 =
* Code optimization
* Add fallback to cURL method if allow_url_fopen value is not defined

= 1.2 =
* Code optimization
* Compatible with grid/list library view

= 1.1.1 =
* Add counter

= 1.1 =
* Check PHP requirements on plugin activation
* Reorder code

= 1.0 =
* Initial release