=== MainWP for Simple Performance for WordPress ===
Contributors: onedogsolutions
Tags: mainwp, performance, optimization, child sites, management
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure the Simple Performance for WordPress plugin on each connected child site directly from the MainWP Dashboard.

== Description ==

A MainWP Dashboard extension for configuring the [Simple Performance for WordPress](https://wordpress.org/plugins/simple-performance-for-wordpress/) plugin on each connected child site, without logging in to that site individually.

Installing this plugin on your MainWP Dashboard adds a "Performance" tab to each individual child site's own screen (Sites → site name → Performance), covering the same five tabs as SPFW's own settings screen:

* **Core Optimizations** — Disable emojis, embeds, dashicons, XML-RPC, Google Maps, comments, block editor CSS, dashboard widgets, and more. Control heartbeat, post revisions, autosave intervals.
* **REST API** — Require authentication, disable namespaces, whitelist routes.
* **Hardening** — Directory hardening (plugins + uploads), XML-RPC (PHP + server-level), file editing, author enumeration, application passwords, login errors, sensitive file protection, security headers, CSP (with violation sampling), HSTS.
* **Fonts** — Localize Google Fonts for GDPR compliance and faster loading.
* **WooCommerce** — Disable cart fragments, scripts/styles on non-shop pages, widgets, and more.

= Requirements =

* MainWP Dashboard plugin (latest version)
* Simple Performance for WordPress 2.1.0 or later on child sites (carries the bridge this extension talks to)

= Features =

* Per-site configuration — each child site has its own settings tab
* Read current settings from child sites in real-time
* Push setting changes to child sites via MainWP's secure signed channel
* One-click install of SPFW on any child site
* Upload a custom SPFW ZIP or link to a self-hosted build for installation
* Uploaded files stored securely and served via time-limited signed URLs
* Bulk install SPFW on multiple sites at once
* Status check — quickly see which optimizations are active on each site

== Installation ==

1. Install and activate the MainWP Dashboard plugin on your management site.
2. Upload the `mainwp-simple-performance-for-wordpress` folder to `/wp-content/plugins/` or install via the Plugins screen.
3. Activate the plugin through the Plugins screen.
4. Navigate to MainWP → Extensions → Simple Performance for WordPress to see your connected sites.
5. Click "Configure" on any site to manage its performance settings.

== Frequently Asked Questions ==

= Does this plugin work without MainWP? =

No. This is a MainWP Dashboard extension and requires the MainWP Dashboard plugin to be installed and activated.

= What version of Simple Performance for WordPress is required on child sites? =

Version 2.1.0 or later, which includes the MainWP child bridge and all settings this extension can manage. If a child site runs an older version, you'll see a notice with an option to install/upgrade.

= Can I manage CSP directives from MainWP? =

The CSP directive builder is complex and site-specific, so it remains read-only from the MainWP Dashboard. You can enable/disable CSP and set the mode, but fine-tuning directives should be done in the child site's own admin panel.

= Can I trigger a font scan from MainWP? =

No. Font scanning touches the child site's filesystem and should be triggered from the child's own admin panel. You can, however, manage the localize toggle and manual font families from MainWP.

== Changelog ==

= 1.4.0 =
* Schema aligned with SPFW v2.1.0: 17 new field definitions added across Core and Hardening tabs.
* New Core toggles: Google Maps (+ page exceptions), comments, password meter, blank favicon, block editor CSS (+ smart mode), streamline dashboard, WP sitemaps, robots max-image-preview.
* New Hardening toggles: XML-RPC (moved from Core), server-level xmlrpc.php block, application passwords, generic login errors, sensitive file protection, CSP violation sample rate.
* Fixed: heartbeat settings renamed to match child plugin (heartbeat_control / heartbeat_frequency).
* Minimum SPFW child version bumped from 1.8.0 to 2.1.0.

= 1.3.0 =
* Security: uploaded ZIPs now stored in MainWP's per-user directory (never publicly accessible).
* Security: install uses time-limited, HMAC-signed download URLs (MainWP native mechanism).
* Removed publicly-accessible wp-content/uploads/mwpspfw-packages/ directory.
* Package form now supports dual-mode: uploaded file (priority) or external URL.
* URL input disabled when a file is uploaded (mutual exclusivity).

= 1.2.3 =
* Fixed bulk install failure: MainWP Child expects a JSON-encoded 'url' parameter.
* Added wp_json_encode() around the package URL to match native MainWP Dashboard behavior.

= 1.2.2 =
* Added extension key status logging to diagnose free extension authentication.

= 1.2.1 =
* Fixed bulk install failure: MainWP Child requires 'url' parameter, not 'slug'.
* Now uses wordpress.org download URL directly when no custom package is configured.

= 1.2.0 =
* Added server-side debug logging for install operations.
* Added Debug Log section with View/Clear buttons on Extensions page.
* Improved bulk install error reporting (per-row status, console logging).

= 1.1.0 =
* Added drag-and-drop ZIP upload for custom SPFW builds.
* Added package URL setting for self-hosted plugin ZIPs.
* Install button now uses configured package URL, falls back to wordpress.org.

= 1.0.0 =
* Initial release.
* Per-site configuration tab with five settings groups.
* Real-time settings read/write via MainWP's signed channel.
* One-click and bulk install of SPFW from wordpress.org.
* Status check for quick overview of active optimizations.

== Upgrade Notice ==

= 1.4.0 =
Schema alignment: adds 17 new field definitions matching SPFW v2.1.0. Requires SPFW 2.1.0+ on child sites.

= 1.3.0 =
Security improvement: uploaded packages now served via signed URLs, no longer publicly accessible.

= 1.2.3 =
Critical fix: bulk install now correctly JSON-encodes the package URL for MainWP Child.

= 1.2.1 =
Critical fix: bulk install now works correctly with wordpress.org downloads.

= 1.2.0 =
Adds debug logging and improved error reporting for bulk installs.

= 1.1.0 =
Adds drag-and-drop ZIP upload for custom SPFW builds and self-hosted package URL support.

= 1.0.0 =
Initial release.
