=== Lapsha WP Tools ===
Contributors: qstanay
Tags: database, cleanup, revisions, transients, tools, administration
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free toolkit for WordPress administrators: safe database cleanup and a foundation for more tools.

== Description ==

Lapsha WP Tools is a free and open-source plugin for WordPress administrators and developers. There is no Premium edition and no locked features.

Version 0.1.1 includes a **Database Cleaner** that can scan and remove:

* Post revisions
* Auto-drafts
* Trashed posts
* Spam comments
* Trashed comments
* Expired transients

Scanning never deletes data. Cleanup requires an explicit confirmation. The plugin loads its assets only on its own admin screens.

The admin interface is translated into Russian (`ru_RU`). The site language in Settings controls which strings you see.

== Installation ==

1. Upload the `lapsha-wp-tools` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open **Lapsha WP Tools** in the admin menu.

== Frequently Asked Questions ==

= Does the plugin delete data automatically? =

No. Nothing is removed on install, activation, or uninstall. Cleanup runs only after you scan, select categories, and confirm.

= Will this remove my published posts? =

No. Revision cleanup removes only `revision` posts. Auto-draft cleanup does not touch normal drafts. Trashed-post cleanup only permanently deletes items already in the trash.

= How do I switch the plugin to Russian? =

Set **Settings → General → Site Language** (or the user’s profile language) to Русский. WordPress will load `languages/lapsha-wp-tools-ru_RU.mo`.

== Changelog ==

= 0.1.1 =
* Russian translation of the admin UI (`ru_RU`).

= 0.1.0 =
* Database Cleaner: scan and remove revisions, auto-drafts, trash, spam, and expired transients.
* Plugin core and admin dashboard.
