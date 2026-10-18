=== MainWP for Simple Performance for WordPress ===
Contributors: onedogsolutions
Tags: mainwp, performance, optimization, child sites, management
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure the Simple Performance for WordPress plugin on each connected child site directly from the MainWP Dashboard.

== Description ==

A MainWP Dashboard extension for configuring the [Simple Performance for WordPress](https://wordpress.org/plugins/simple-performance-for-wordpress/) plugin on each connected child site, without logging in to that site individually.

Installing this plugin on your MainWP Dashboard adds a "Performance" tab to each individual child site's own screen (Sites → site name → Performance), covering the same six tabs as SPFW's own settings screen:

* **Core Optimizations** — Disable emojis, embeds, dashicons, XML-RPC, Google Maps, comments, block editor CSS, dashboard widgets, WP-Cron, site search, scaled images, intermediate image sizes, and more. Control heartbeat, post revisions, autosave intervals, speculation rules.
* **REST API** — Require authentication, disable namespaces, whitelist routes.
* **Hardening** — Directory hardening (plugins + uploads, with live .htaccess status badges), XML-RPC (PHP + server-level), file editing, author enumeration, application passwords, login errors, sensitive file protection, PHP execution whitelist, file integrity monitoring, security headers, Permissions-Policy, CSP (fully editable directives, custom policy mode, violation sampling and collection controls), HSTS.
* **Fonts** — Localize Google Fonts for GDPR compliance and faster loading.
* **WooCommerce** — Disable cart fragments, scripts/styles on non-shop pages, widgets, and more.
* **Database** — Choose cleanup targets (revisions, auto drafts, trashed posts, spam/trashed comments, transients, table optimization) and an automatic optimization schedule.

= Requirements =

* MainWP Dashboard plugin (latest version)
* Simple Performance for WordPress 2.17.0 or later on child sites (carries the bridge this extension talks to)
* Simple Performance for WordPress 2.21.0 or later for file integrity review and fleet comparison (older sites keep full settings support and are skipped by the comparison)

= Features =

* Per-site configuration — each child site has its own settings tab
* Read current settings from child sites in real-time
* Push setting changes to child sites via MainWP's secure signed channel
* One-click install of SPFW on any child site
* Upload a custom SPFW ZIP or link to a self-hosted build for installation
* Uploaded files stored securely and served via time-limited signed URLs
* Bulk install SPFW on multiple sites at once
* Status check — quickly see which optimizations are active on each site, and how many file changes wait for review
* File integrity review — see each site's unverified file changes, scan, and Mark as verified from the dashboard
* Fleet file integrity — compare each plugin version's files across all sites, see exactly which files differ on the odd site out, and verify matching copies in one confirmed step

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

Version 2.17.0 or later, which includes the MainWP child bridge and all settings this extension can manage. If a child site runs an older version, you'll see a notice with an option to install/upgrade.

= Can I manage CSP directives from MainWP? =

Yes. The CSP builder's 13 whitelisted directives can each be edited directly from the MainWP Dashboard as space-separated source lists, and a custom raw policy can be entered when CSP Mode is set to Custom. The child's own admin additionally offers a visual builder with preset chips and the violation log.

= Can I trigger a database cleanup or font scan from MainWP? =

No. One-shot operations (database scan/cleanup, font scan, htaccess verification) touch the child site's filesystem and remain in the child's own admin. From MainWP you manage the cleanup targets, the automatic optimization schedule, and the font settings.

== Changelog ==

= 1.6.0 =
* New: Fleet File Integrity on the Extensions page. Compares each plugin version's files across your sites (one fingerprint per plugin per site) and flags the site whose copy differs from the majority, which is usually how an infected site is found. "Show differences" lists exactly which files were added, modified, or removed against a majority site.
* New: consensus verification. Where at least three sites run identical copies of a plugin version and some of them still have that plugin waiting for review (for example a premium plugin copied in by SFTP), an administrator can verify those sites in one confirmed step. Each site rechecks its own files and refuses if they no longer match.
* New: File integrity review on each site's Hardening tab: last scan, last verification and how it happened, unexplained changes and unverified updates grouped by plugin and uploads, Scan now, and Mark as verified per plugin, for uploads, or for everything.
* New: the root .htaccess status badge beside "Protect sensitive files".
* Improved: the overview Check button reports how many file changes are waiting for review.
* File integrity features need SPFW 2.21.0 on the child; settings management still works from 2.17.0.

= 1.5.0 =
* Full settings parity with SPFW child v2.17.0: 27 new field definitions across Core, Hardening, and a new Database tab (88 total, up from 61).
* New Core fields: disable WP-Cron (+ cron lock timeout), speculation rules, disable site search (+ redirect to homepage), disable scaled images, disable intermediate image sizes.
* CSP is now fully editable: all 13 builder directives as space-separated source lists, custom raw policy mode, tighten script-src, 'strict-dynamic', violation rate limit, and collection controls (admin inclusion, cache bypass).
* New Hardening fields: Permissions-Policy feature controls (block or allowlist per feature), PHP execution whitelist, auto-allow known plugin endpoints, file integrity monitoring (+ alert email).
* New Database tab: eight cleanup targets plus an automatic table-optimization schedule.
* Hardening status badges (Active / File missing / File modified / …) beside the plugins and uploads .htaccess rows, refreshed after every save.
* Fixed: six duplicate Core field definitions removed; post-revision options now cover the full 1–30 range; autosave interval matches the child's 0–5 minute options; block CSS smart mode default corrected to on.
* Fixed: conditional rows depending on a toggle now evaluate the checkbox state (previously the hidden '0' twin could keep dependent rows hidden), and post-save re-hydration no longer writes the string 'false' into unchecked toggles.
* Minimum SPFW child version bumped from 2.1.0 to 2.17.0.

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

= 1.6.0 =
Fleet file integrity comparison and per-site file review. These need SPFW 2.21.0+ on child sites; settings management still works from 2.17.0.

= 1.5.0 =
Full settings parity with SPFW v2.17.0: editable CSP directives and Permissions-Policy, new Database tab, file monitoring, and hardening status badges. Requires SPFW 2.17.0+ on child sites.

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
