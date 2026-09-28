# State Tracker - MainWP for Simple Performance for WordPress

A MainWP Dashboard extension for configuring the Simple Performance for WordPress
(SPFW) plugin on each connected child site. Installed on the MainWP Dashboard
site only. Companion plugin: `onedogsolutions/simple-performance-for-wordpress`
(its own STATE.md tracks the child-side work).

# Current Phase: Phase 8 (Full settings parity with SPFW v2.17.0, v1.5.0)

Aligned the extension's schema with child plugin v2.17.0. The child had shipped
new settings across v1.15.0–v2.13.0 (cron/speculation/search/image sizes/
permissions-policy, CSP tightening + strict-dynamic + collection controls, PHP
whitelist + file monitor, database cleanup) that the dashboard could not
configure, and the v1.4.0 schema carried six duplicate Core field definitions
plus two child-mismatching field definitions. Field count: 61 → 88.

### Phase 8 Modifications (v1.5.0)

- **`class-mwpspfw-settings-schema.php` — bug fixes:** removed the six duplicate
  Core definitions (`disable_google_maps`, `google_maps_exceptions`,
  `disable_password_meter`, `disable_comments`, `remove_comment_urls`,
  `blank_favicon` — each was defined twice; PHP keeps the last value at the
  first position, and the two `remove_comment_urls` definitions carried
  contradictory `requires`); dropped `remove_comment_urls`'s `requires`
  entirely (the child shows it unconditionally); `block_css_smart_mode` default
  '0' → '1' (child defaults true); `block_xmlrpc_file` gained
  `requires hardening.disable_xmlrpc = 1`; `post_revisions` expanded from a
  subset to the full default/disable/1–30 range; `autosave_interval` changed
  from int 0–10 to a select of the child's 0–5 minute options (the child clamps
  to 0–5).
- **Schema — new Core fields (7):** `disable_wp_cron` (warns: only with a real
  system cron) + `cron_lock_timeout` (select 0/60/120/300, conditional),
  `speculation_rules` (select default/conservative/disable), `disable_search` +
  `search_redirect_home` (conditional), `disable_scaled_images`, and
  `disabled_image_sizes` — first use of the new `multi_check` type
  (1536x1536 / 2048x2048 / medium_large, stored as an array).
- **Schema — new Hardening fields (11 + CSP editability):** `permissions_policy`
  (new composite: 6 features, stored as feature => tokens[] when blocked or
  false when unblocked), `csp_policy` (new `textarea` type: custom-mode raw
  policy string), `csp_directives` changed from `readonly` to an editable
  composite (the child's 13 whitelisted directives, each posted as a
  space-separated token string; defaults copied 1:1 from the child's own
  default map), `csp_tighten_script_src`, `csp_strict_dynamic`, `csp_rate_limit`
  (int 5–60), `csp_collect_admin`, `csp_collect_nocache`, `php_whitelist`
  (route_list: wp-content-relative PHP paths), `auto_allow_known_php`,
  `file_monitor_enabled`, and `file_monitor_email` (new `text` type, input
  type email, conditional on the monitor being on).
- **Schema — new `database` tab (9 fields, no WooCommerce gating):** eight
  cleanup-target toggles (Clean Post Revisions / Auto Drafts / Trashed Posts /
  Spam Comments / Trashed Comments / Expired Transients / All Transients /
  Optimize Tables) + `optimize_schedule` (select off/daily/weekly/monthly).
  `database.post_revisions` deletes revisions during a cleanup run and is
  distinct from `core.post_revisions`, which caps future revisions. One-shot
  scan/cleanup actions stay in the child admin (operations, not settings).
- **Schema — `flatten()`/`nest()` for the new types:** text/textarea are trim-
  only passthrough (the child's `sanitize()` is the authority); multi_check is
  whitelisted to declared options; the composites go through new private
  helpers `nest_permissions_policy()` (posted block+origins → tokens[]|false,
  empty allowlist = `[]`) and `nest_csp_directives()` (string → tokens[], empty
  string = `[]`, which the child preserves so clearing a directive sticks).
  Toggle coercion now uses `filter_var( FILTER_VALIDATE_BOOLEAN )` so a stale
  client posting the string 'false' cannot flip a toggle on.
- **`class-mwpspfw-individual.php`:** `render_field()` gained cases for text,
  textarea, multi_check (checkboxes named `fields[group][key][]`), and the two
  composites — permissions-policy renders per-feature a hidden-0+checkbox-1
  block toggle plus an origins text input
  (`fields[hardening][permissions_policy][feature][block|origins]`), and CSP
  renders 13 labeled token inputs
  (`fields[hardening][csp_directives][directive]`), replacing
  `render_readonly_csp()` (removed). Hardening status badges render beside the
  plugins/uploads .htaccess rows from the bridge's top-level
  `hardening_status` / `uploads_hardening_status` (get_settings only), mapping
  ok/missing/altered/unsupported/advisory → Active / File missing / File
  modified / Not available on this server / Needs server config. `ajax_save()`
  coerces `block_xmlrpc_file = 0` when `disable_xmlrpc` is off (mirrors the
  child UI's auto-uncheck; hidden conditional inputs still post values), and
  after a successful update re-reads settings once so the response carries the
  fresh status keys — the update envelope does not include them. A failed
  re-read falls back to the update envelope rather than failing the save.
- **`assets/js/mwpspfw-admin.js`:** `rehydrateForm()` extended to refresh
  multi_check boxes (name ends `[]`), both composites (permissions-policy from
  the tokens[]|false map; CSP directives as space-joined lists), and the status
  badges (tone map mirrors the server renderer, labels from the new localized
  `statusLabels`). Also fixed two latent round-trip bugs: conditional rows now
  resolve the toggle's checkbox instead of its hidden '0' twin (previously
  every toggle-dependent row stayed hidden), and rehydration skips hidden
  inputs (previously stored booleans overwrote the twins, so unchecked toggles
  posted the string 'false' on the next save).
- **`assets/css/mwpspfw-admin.css`:** layout-only additions — multi_check
  groups, permissions-policy feature rows, the CSP directive grid, and status
  badge placement (the Fomantic label classes carry the tones). The read-only
  CSP rules were removed. Still no color values.
- **`class-mwpspfw-connector.php`:** `MIN_SPFW_VERSION` 2.1.0 → 2.17.0. The
  newest fields need the child ≥ 2.13.0, and older children silently drop
  unknown keys — the higher floor keeps silent partial saves from looking
  successful. No transport changes.
- **`class-mwpspfw-admin.php`:** added `statusLabels` to the `mwpspfwAdmin`
  localized data (used by the badge refresh after save).
- Version bumped to 1.5.0 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog + upgrade notice; the plugin/readme descriptions now
  cover six tabs including Database).

### Schema field count

| Tab | Before (v1.4.0) | After (v1.5.0) |
|---|---|---|
| Core | 29 | 36 |
| REST API | 3 | 3 |
| Hardening | 20 | 31 |
| Fonts | 3 | 3 |
| WooCommerce | 6 | 6 |
| Database | — | 9 |
| **Total** | **61** | **88** |

(The v1.4.0 "before" column counts effective fields; its six duplicate Core
definitions produced no extra settings. `csp_directives` existed before as a
readonly display and was re-typed, not added.)

## Historical Phase: Phase 7 (Schema alignment with SPFW v2.1.0, v1.4.0)

Aligned the MainWP extension's settings schema with the child plugin (SPFW
v2.1.0). The child plugin had accumulated 17 new field definitions and
renamed/moved 3 existing fields across versions 1.13.0–2.1.0. The old schema
silently lost data for heartbeat settings (key mismatch) and could not
configure any of the new toggles remotely.

### Phase 7 Modifications (v1.4.0)

- **`class-mwpspfw-settings-schema.php` — Core tab renames:**
  `heartbeat_mode` → `heartbeat_control` (select: default/disable/allow_posts)
  and `heartbeat_interval` → `heartbeat_frequency` (select: 0/15/30/60/120,
  was `int`). The child plugin renamed these in Step 10 (v1.0.0 release, but
  the field keys changed post-1.0.0 when heartbeat was reworked). The old keys
  caused saves to silently lose heartbeat configuration because the child's
  `SPFW_Settings::update()` didn't recognize them.
- **`class-mwpspfw-settings-schema.php` — Core tab, removed field:**
  `disable_xmlrpc` removed from Core (moved to Hardening group in child v2.0.2,
  where it now sits alongside the server-level `block_xmlrpc_file` toggle).
- **`class-mwpspfw-settings-schema.php` — Core tab, 11 new fields added:**
  `disable_google_maps` (toggle) + `google_maps_exceptions` (route_list,
  conditional), `disable_password_meter` (toggle), `disable_comments` (toggle)
  + `remove_comment_urls` (toggle, conditional on comments enabled),
  `blank_favicon` (toggle), `disable_block_css` (toggle) +
  `block_css_smart_mode` (toggle, conditional), `streamline_dashboard`
  (toggle), `disable_wp_sitemaps` (toggle),
  `remove_robots_max_image_preview` (toggle). All default off (opt-in),
  matching the child plugin's safe defaults.
- **`class-mwpspfw-settings-schema.php` — Hardening tab, 6 new fields added:**
  `disable_xmlrpc` (toggle, default on — moved from Core, child defaults it
  ON), `block_xmlrpc_file` (toggle, server-level .htaccess block),
  `disable_app_passwords` (toggle), `generic_login_errors` (toggle),
  `protect_sensitive_files` (toggle, root .htaccess), `csp_collect_sample`
  (select: 1/5/10/25/50/100%, conditional on CSP enabled). `csp_collect_until`
  is a server-managed timestamp and intentionally not in the schema.
- **`class-mwpspfw-connector.php` — `MIN_SPFW_VERSION`:** bumped from `1.8.0`
  to `2.1.0`. The new settings require schema additions that shipped across
  child versions 1.13.0–2.1.0; sites running older SPFW would accept but ignore
  unknown keys, so the version gate ensures the child actually processes every
  field this extension sends.
- Version bumped to 1.4.0 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog + upgrade notice).
- **No connector changes:** the existing `get_settings`/`update_settings`
  actions are schema-agnostic — they serialize/deserialize whatever the schema
declares, so new fields are handled automatically.
- **No JS/CSS changes:** the existing `data-requires-field`/`data-requires-value`
  conditional logic in `mwpspfw-admin.js` handles all new conditional fields
  (google_maps_exceptions, remove_comment_urls, block_css_smart_mode,
  csp_collect_sample) without modification.

### Key field changes summary

| Change | Old key | New key | Reason |
|---|---|---|---|
| Renamed | `core.heartbeat_mode` | `core.heartbeat_control` | Child renamed in Step 10 |
| Renamed | `core.heartbeat_interval` | `core.heartbeat_frequency` | Child renamed + type changed to select |
| Moved | `core.disable_xmlrpc` | `hardening.disable_xmlrpc` | Child moved in v2.0.2 |

### Schema field count

| Tab | Before (v1.3.0) | After (v1.4.0) |
|---|---|---|
| Core | 28 | 29 |
| REST API | 3 | 3 |
| Hardening | 14 | 20 |
| Fonts | 3 | 3 |
| WooCommerce | 6 | 6 |
| **Total** | **54** | **61** |

## Historical Phase: Phase 6 (Native secure downloads, v1.3.0)

Replaced the publicly-accessible uploaded-file storage with MainWP's native
signed download URL mechanism. Uploaded ZIPs are now stored in MainWP's
per-user directory and served exclusively via time-limited, HMAC-signed URLs
generated at install time — eliminating the publicly-accessible
`wp-content/uploads/mwpspfw-packages/` directory entirely.

### Discovery: MainWP 6.1.4 Native File Uploader Evaluation

Evaluated whether to replace our custom drag-and-drop uploader with MainWP's
native file uploader (shipped in the core plugin). Findings from dissecting
`mainwp.6.1.4.zip`:

**Native uploader architecture** (files examined):
- `pages/page-mainwp-install-bulk.php` — `MainWP_Install_Bulk` class
- `class/class-mainwp-qq2-file-uploader.php` — `MainWP_QQ2_File_Uploader`
- `class/class-mainwp-qq2-uploaded-file-form.php` — form-based upload handler
- `class/class-mainwp-post-plugin-theme-handler.php` — AJAX action routing
- `assets/js/mainwp.js` (lines 2820–3048) — bulk upload JS flow
- `class/class-mainwp-system-utility.php` — `get_download_url()`,
  `get_download_sig()`, `get_mainwp_specific_dir()`

**Native flow**: Upload ZIP via Dropzone.js → stored in
`wp-content/uploads/mainwp/{uid}/bulk/` → `prepare_upload()` generates signed
download URLs (`admin_url('?sig=<hmac>&mwpdl=<path>')`, validated via 3-hour
site transient) → `perform_upload()` sends signed URL to child via
`installplugintheme` → child downloads from dashboard → `clean_upload()`
deletes temp files.

**Why NOT hooking into the native uploader UI**:
1. **Not componentized**: The native Dropzone is inline page-specific markup
   with hardcoded element IDs (`#mainwp-dropzone-upload`, `#mainwp-file-uploader`)
   and inline `<script>` blocks — not a reusable widget.
2. **Incompatible lifecycle**: Native files are temporary (deleted after install
   via `clean_upload()`). Our use case requires a persistent package that
   survives across multiple install operations.
3. **Coupled site selection**: The native flow is tightly coupled to the Install
   Plugins/Themes page's groups/clients/sites checkboxes.
4. **No external URL support**: The native uploader only handles file uploads,
   not self-hosted ZIP URLs (update servers).
5. **Extra dependencies**: Would require jQuery + Dropzone.js (our JS is
   deliberately dependency-free plain JS).

**What we DID adopt**: The native **secure download URL mechanism**
(`MainWP_System_Utility::get_download_url()` / `get_download_sig()`).
This generates time-limited, HMAC-signed URLs validated via site transients
(3-hour window). Files are served through WordPress, never directly accessible.
This is significantly more secure than our previous approach of storing files
in a publicly-accessible uploads directory with only `.htaccess` protection.

### Phase 6 Modifications (v1.3.0)

- **`class-mwpspfw-overview.php` — `ajax_upload_package()`**: Now stores
  uploaded ZIPs in MainWP's per-user directory via
  `MainWP_System_Utility::get_mainwp_specific_dir('spfw')` (resolves to
  `wp-content/uploads/mainwp/{uid}/spfw/`). Returns the filename (not a public
  URL). Persists `array('file' => $filename)` in the `mwpspfw_package` option.
  Removed manual `.htaccess`/`index.php` guard creation (MainWP's directory
  structure handles this). Added `class_exists` guard for `MainWP_System_Utility`.
- **`class-mwpspfw-overview.php` — `ajax_save_package()`**: Now supports
  dual-mode: accepts either a `file` parameter (uploaded ZIP) or a `url`
  parameter (external self-hosted ZIP). File mode takes priority.
- **`class-mwpspfw-overview.php` — `render_page()`**: Added hidden `file` input
  to the package form. Dropzone preview shows the stored filename on page load.
  URL input is disabled when a file is uploaded (mutual exclusivity). Added
  description: "Uploaded files are stored securely and served via time-limited
  signed URLs at install time."
- **`class-mwpspfw-overview.php` — `get_package()`**: Now returns both `url`
  and `file` keys from the `mwpspfw_package` option.
- **`class-mwpspfw-connector.php` — `install_package()`**: Priority logic:
  if `package['file']` exists and `MainWP_System_Utility` is available,
  generates a fresh signed download URL via `get_download_url('spfw', $file)`.
  Falls back to `package['url']` for external ZIPs. The signed URL is valid for
  3 hours (more than sufficient for the install operation).
- **`assets/js/mwpspfw-admin.js` — `initPackageForm()`**: Added `setFileMode()`
  toggle (disables/clears URL input when file is active). `resetDropzone()` now
  clears the hidden file field and re-enables URL mode. Upload success handler
  stores the filename in the hidden field (not a URL). Initializes file mode on
  page load if a file is already configured.
- **`assets/css/mwpspfw-admin.css`**: Added `#mwpspfw-package-url:disabled`
  style (opacity + not-allowed cursor).
- Version bumped to 1.3.0 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog).

### Security Improvement

| Before (v1.2.x) | After (v1.3.0) |
|---|---|
| Files in `wp-content/uploads/mwpspfw-packages/` | Files in `wp-content/uploads/mainwp/{uid}/spfw/` |
| Publicly accessible URL (`.htaccess` only guard) | Never publicly accessible |
| URL persisted indefinitely in option | Signed URL generated on-demand, expires in 3h |
| No HMAC validation | HMAC + site transient validation |
| Manual `.htaccess`/`index.php` guards | MainWP's own directory protection |

## Historical Phase: Phase 5 (JSON-encode url parameter, v1.2.3)

Fixed bulk install failure caused by sending a raw URL string to MainWP Child's
`installplugintheme` callable. The child handler calls `json_decode()` on the
received `url` POST parameter, so the value must be JSON-encoded. A raw URL
string is invalid JSON and decodes to `null`, causing `WP_Upgrader->run()` to
receive a null package.

### Phase 5 Modifications (v1.2.3)

- **Fixed `MWPSPFW_Connector::install_package()`**: Now wraps the package URL
  with `wp_json_encode()` before passing it as the `url` parameter. This matches
  the native MainWP Dashboard behavior found in
  `MainWP_Install_Bulk::perform_install()` and `perform_upload()`
  (`page-mainwp-install-bulk.php`, MainWP Dashboard 6.1.4).
- **Root cause**: MainWP Child's `install_plugin_theme()` handler
  (`class-mainwp-child-install.php` line 410, MainWP Child 6.1.4) executes
  `json_decode( stripslashes( wp_unslash( $_POST['url'] ) ) )`. A raw URL
  string like `https://...` is not valid JSON, so `json_decode()` returns
  `null`. The native Dashboard always sends `wp_json_encode( $url )` which
  produces a quoted JSON string that decodes correctly.
- Version bumped to 1.2.3 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog).

## Historical Phase: Phase 4 (Critical install fix, v1.2.1)

Fixed bulk install failure caused by incorrect parameter passed to MainWP Child's
`installplugintheme` callable. The callable requires a `url` parameter pointing
to a ZIP file; it does not support `slug`-based installs.

### Phase 4 Modifications (v1.2.1)

- **Fixed `MWPSPFW_Connector::install_package()`**: Now always provides a `url`
  parameter to the `installplugintheme` callable. When no custom package URL is
  configured, constructs the wordpress.org download URL directly:
  `https://downloads.wordpress.org/plugin/simple-performance-for-wordpress.latest-stable.zip`
- **Root cause**: MainWP Child's `installplugintheme` handler expects `url` for
  the ZIP download location. The previous `slug` parameter was ignored, causing
  the error "Plugin or theme not specified, or missing required data."
- Version bumped to 1.2.1 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog).

## Historical Phase: Phase 3 (Debug logging + improved error reporting, v1.2.0)

Added server-side debug logging for install operations and improved bulk install
error reporting to help diagnose installation failures across child sites.

### Phase 3 Modifications (v1.2.0)

- **Server-side debug logging** in `class-mwpspfw-connector.php`: new `log()`,
  `get_log_path()`, `get_log_contents()`, and `clear_log()` methods. All
  install attempts are logged to `wp-content/uploads/mwpspfw-logs/debug.log`
  with timestamps, event names, and JSON-encoded context (site ID, package
  URL, install args, raw response, error details). Log directory is protected
  with `.htaccess` (Deny from all) and `index.php` (silence) guards.
- **Debug Log UI section** in `class-mwpspfw-overview.php`: new segment on the
  Extensions page showing the log file path, a "View Log" button (loads last
  200 lines into a scrollable `<pre>` panel), and a "Clear Log" button.
- **New AJAX endpoints** `mwpspfw_get_log` and `mwpspfw_clear_log`
  (`MWPSPFW_Overview::ajax_get_log()` / `ajax_clear_log()`): gated behind
  `install_plugins` capability and `mwpspfw_debug_log` nonce.
- **Improved bulk install error reporting** in `mwpspfw-admin.js`: each row's
  Status cell now shows "Installing…" during the operation and "Installed." or
  "✗ [error message]" on completion. Unique errors are collected and the first
  is appended to the summary line. All errors are logged to the browser
  console via `console.error()` for debugging.
- **New CSS** in `mwpspfw-admin.css`: `.mwpspfw-log-output` styling (monospace
  font, scrollable, max-height 400px, inherits theme colors).
- **New localized string** in `MWPSPFW_Admin::enqueue_assets()`: `logNonce`.
- Version bumped to 1.2.0 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog).

## Historical Phase: Phase 2 (Drag-and-drop package upload, v1.1.0)

Added a drag-and-drop ZIP upload feature to the Extensions-page overview,
modeled on the sister project `mainwp-for-google-security-for-wordpress` Phase 8.
Allows uploading a custom SPFW build or linking to a self-hosted ZIP for
installation on child sites, with automatic fallback to wordpress.org when no
package URL is configured.

### Phase 2 Modifications (v1.1.0)

- **Package form with drag-and-drop upload zone** in
  `class-mwpspfw-overview.php`: a dashed-border container with an upload icon,
  "Drop your ZIP here" prompt, file-type/size hint ("ZIP only, max. 10 MB"),
  a "Select file" button, a file-preview row (filename + × remove button), and
  a `role="alert"` error line. The URL input remains below (auto-populated on
  successful upload, or manually entered for a self-hosted ZIP) with a helper
  description explaining the wordpress.org fallback.
- **New AJAX endpoint `mwpspfw_upload_package`**
  (`MWPSPFW_Overview::ajax_upload_package()`): validates the upload (ZIP
  extension, ≤ 10 MB, `UPLOAD_ERR_OK`), stores the file in
  `wp-content/uploads/mwpspfw-packages/` with a unique timestamped name,
  writes `.htaccess` (Deny from all) + `index.php` (silence) guards on first
  use, and returns the public URL. Gated behind `upload_files` +
  `install_plugins` capabilities and its own nonce.
- **New AJAX endpoint `mwpspfw_save_package`**
  (`MWPSPFW_Overview::ajax_save_package()`): saves the package URL to the
  `mwpspfw_package` option. Gated behind `install_plugins` capability.
- **`MWPSPFW_Connector::install_package()` updated**: now checks for a
  configured package URL (`mwpspfw_package` option) and uses it if present;
  otherwise falls back to the wordpress.org slug-based install. This allows
  installing custom builds or specific versions while maintaining the
  zero-config default behavior.
- **`initPackageForm()` in `mwpspfw-admin.js`**: client-side validation
  (extension + size), `FormData`/`fetch()` multipart upload, drag-enter/over/
  leave/drop handlers with a `--dragging` visual state, click-anywhere-to-
  browse (when no file is loaded), preview/remove toggle, inline error
  display, and package URL form submission.
- **New CSS** in `mwpspfw-admin.css`: drop-zone layout (flexbox centering,
  min-height, dashed border, rounded corners), visually-hidden file input,
  icon circle, prompt/preview states, remove button, error text. Layout-only,
  no color values — inherits MainWP's Fomantic theme (light/dark).
- **4 new localized strings** in `MWPSPFW_Admin::enqueue_assets()`:
  `uploadNonce`, `packageNonce`, `uploading`, `uploadError`, `invalidType`,
  `invalidSize`.
- Version bumped to 1.1.0 (plugin header, `MWPSPFW_VERSION`, `readme.txt`
  stable tag + changelog).

## Historical Phase: Phase 1 (Initial implementation, v1.0.0)

Built the full dashboard-side extension modeled on the sister project
`onedogsolutions/mainwp-for-google-security-for-wordpress`. Server-rendered PHP
+ one small JS/CSS pair; **no build step** (the MainWP admin brings its own
Fomantic UI chrome and the settings form is plain inputs). Namespace
`MainWP\Extensions\SPFW`, prefix `mwpspfw_`, autoloaded from
`class/class-mwpspfw-*.php`.

### Phase 1 Modifications (v1.0.0)

- **`mainwp-simple-performance-for-wordpress.php`** (`MWPSPFW_Activator`,
  modeled on the GSWP MainWP extension): registers via `mainwp_getextensions`
  (`apiManager => false` — free in-house extension); gates on
  `mainwp_activated_check`/`mainwp_activated` with the standard Plugins-screen
  notice when MainWP Dashboard is absent; and, once active, registers the
  per-site tab through `mainwp_getsubpages_sites` with `'slug' => 'SPFWConfig'`,
  `'sitetab' => true`, `'menu_hidden' => true` — a "Performance" tab on each
  individual child site's own screen
  (`admin.php?page=ManageSitesSPFWConfig&id={siteId}`).
- **`class-mwpspfw-settings-schema.php`**: single source of truth for all 53
  SPFW settings (matched 1:1 to SPFW v1.8.0's `spfw/v1/settings` REST
  contract), grouped into five task-oriented tabs mirroring SPFW's own screen —
  Core Optimizations (27 fields), REST API Controls (3 fields), Security
  Hardening (14 fields including CSP builder read-only display), Google Fonts
  Localizer (3 fields), and WooCommerce (6 fields). Each field declares type
  (`toggle`, `select`, `int`, `route_list`, `readonly`), enums, ranges,
  conditional visibility (`requires`), and WooCommerce gating. Render and save
  both iterate the schema, so a future SPFW option is a one-array-entry change
  here. Includes `flatten()` and `nest()` helpers to convert between SPFW's
  grouped settings structure (`array('core' => array('disable_emojis' => true))`)
  and the flat dot-notation keys used internally (`core.disable_emojis`).
- **`class-mwpspfw-connector.php`**: owns every dashboard→child call. Wraps
  `apply_filters( 'mainwp_fetchurlauthed', $extFile, $extKey, $siteId,
  'extra_execution', $postData )` and normalizes responses into **three typed
  outcomes** the UI messages distinctly: `transport` (site unreachable/
  suspended/not editable), `bridge_missing` (MainWP Child answered but no
  `spfw` envelope — SPFW absent or < 1.8.0), `bridge_error` (envelope present,
  `success => false`). The MainWP channel was chosen over SPFW's own REST route
  because that route authenticates with `manage_options` cookies/application
  passwords — the configurator must not depend on them. Includes
  `install_package()` using MainWP's standard `installplugintheme` callable
  with the wordpress.org slug (`simple-performance-for-wordpress`).
- **`class-mwpspfw-individual.php`**: the per-site tab. Renders between
  `mainwp_pageheader_sites`/`mainwp_pagefooter_sites` ('SPFWConfig'); site id
  from `$_GET['id']`. Fetches settings synchronously on render and **shows no
  form on any failure** (prevents blind overwrites of a site it can't read).
  One form, one Save button, all five tabs mounted (inactive panels `hidden`).
  CSP directives are displayed as a read-only summary table (the structured
  CSP builder is too complex for remote editing — only the enable/disable/mode
  toggles are managed remotely). Save posts to `wp_ajax_mwpspfw_save_settings`
  (nonce + `mainwp_current_user_can('dashboard', 'edit_sites')`), coerces each
  posted value per schema type (toggles to `'1'`/`'0'`, ints numeric, route
  lists sanitized), skips WooCommerce-gated fields that were never rendered,
  and re-hydrates the form from the child's **post-save** response.
- **`class-mwpspfw-overview.php`**: the Extensions-page landing — site table
  via the `mainwp_getsites` filter with per-site "Configure" links, an
  on-demand per-row "Check" button (`wp_ajax_mwpspfw_check_site`) reporting
  SPFW version/active toggle count/CSP status, and an "Install SPFW" button
  (`wp_ajax_mwpspfw_install_spfw`) using MainWP's standard plugin-install
  mechanism. Includes bulk-install: per-row checkboxes with select-all toggle
  and "Install SPFW on Selected" button that installs sequentially with
  real-time progress and a final success/failure summary.
- **`class-mwpspfw-admin.php`**: AJAX endpoint registration and the shared,
  idempotent `enqueue_assets()` (script/style + `mwpspfwAdmin` localized
  strings/nonces), called from each screen's render method.
- **`assets/js/mwpspfw-admin.js`**: plain JS (no jQuery dependency — MainWP's
  Fomantic UI JS requires jQuery which this project deliberately avoids).
  Tab switching with URL-hash persistence (`#tab=<id>`), schema-driven
  conditional field show/hide via `data-requires-field`/`data-requires-value`
  attributes, beforeunload dirty guard, AJAX save/check/install. Bulk install
  uses sequential AJAX loop (one request per site, avoiding PHP timeout on
  large site counts) with per-row status updates.
- **`assets/css/mwpspfw-admin.css`**: layout-only rules with **no color values
  at all**, so light/dark theme is inherited entirely from MainWP's Fomantic
  UI. Includes `.mwpspfw-tabpanel[hidden]{ display: none !important; }`
  defensive rule (Fomantic tab segment's display CSS could otherwise beat the
  browser's low-specificity `[hidden]` default), route-list textarea monospace
  styling, CSP preview table, and bulk-action bar layout.
- **`readme.txt`**: WordPress.org-style plugin readme with per-site scope
  rationale, SPFW ≥ 1.8.0 requirement, MainWP-connection-unaffected FAQ, and
  installation instructions.
- All PHP `php -l` clean; JS `node --check` clean.

### Key Differences from GSWP Extension

- **No package upload subsystem.** Unlike GSWP (not on wordpress.org), SPFW is
  available on wordpress.org, so the install mechanism uses MainWP's slug-based
  install instead of a custom ZIP URL. This eliminated the entire package
  upload/URL setting UI.
  *(Superseded in Phase 2/6: SPFW is NOT on wordpress.org; a custom package
  upload + signed URL system was added.)*
- **Grouped settings structure.** SPFW uses nested groups
  (`array('core' => array(...), 'restapi' => array(...))`) vs GSWP's flat
  keys. Solved with `flatten()`/`nest()` helpers and dot-notation keys
  internally (`core.disable_emojis`).
- **CSP builder read-only.** The structured CSP directive builder is too
  complex for remote editing. Only the enable/disable/mode toggles are managed
  from the dashboard; the directives themselves display as a read-only summary.
  *(Superseded in Phase 8: all 13 builder directives and the custom raw policy
  are now fully editable from the dashboard.)*
- **Field naming convention.** Form fields use bracket notation
  (`fields[core][disable_emojis]`) to preserve the grouped structure through
  the save cycle.

## Child-Side Bridge (separate repository)

The child-side bridge (`includes/class-spfw-mainwp-child.php`) ships inside the
`onedogsolutions/simple-performance-for-wordpress` plugin. It hooks
`mainwp_child_extra_execution` to handle `get_settings` and `update_settings`
actions from the dashboard, routing both through `SPFW_Settings::get()/update()`
so all validation stays single-source. It is a schema-agnostic passthrough —
this extension's schema is the sole authority on which fields exist, and no
child-side changes were needed for Phase 8 (verified against child v2.17.0
source).

**Wire contract:**
- Request field: `spfw_action` (`get_settings` | `update_settings`)
- For updates: JSON-encoded `settings` field (`wp_unslash()` required child-side
  since `$_POST` arrives slashed)
- Response envelope key: `spfw` with `success`, `version`, `woocommerce_active`,
  `settings` fields
- `get_settings` additionally injects top-level `hardening_status` and
  `uploads_hardening_status` strings (ok/missing/altered/unsupported/advisory)
  beside the settings array — `update_settings` does not, which is why
  `ajax_save()` re-reads settings after a successful save (Phase 8).

## Next Steps

- **Manual QA against a child site running SPFW 2.17.0** (the extension floor
  is now 2.17.0, so older children show the bridge-missing notice):
  - All six tabs render; Database tab appears after the WooCommerce gating
    logic.
  - Conditional show/hide works for: cron lock timeout, search redirect, all
    CSP sub-fields, file monitor email, Permissions-Policy under security
    headers, Block xmlrpc.php under Disable XML-RPC, custom policy under
    custom mode.
  - Save round-trip per new field; post-save rehydration updates multi_check
    and both composites correctly.
  - Permissions-Policy: unchecking a feature sends `false`; an empty allowlist
    sends `[]`.
  - CSP builder directives: clearing a directive's tokens clears it on the
    child.
  - Hardening status badges render on load and refresh after save.
  - Regression: the original 61 fields still save and round-trip.
- Rebuild the distribution ZIP after any further change (build command in the
  project's build-configuration notes; excludes `.git`, `.DS_Store`, existing
  ZIPs, and STATE.md).

## Historical Phases

### Phase 7 (Schema alignment with SPFW v2.1.0, v1.4.0)

Aligned the settings schema with child v2.1.0: heartbeat key renames,
disable_xmlrpc moved to Hardening, 17 new field definitions. See the Phase 7
Modifications section above for full details.

### Phase 6 (Native secure downloads, v1.3.0)

Replaced publicly-accessible uploaded-file storage with MainWP's native signed
download URL mechanism. See the Phase 6 Modifications section above for full
details.

### Phase 5 (JSON-encode url parameter, v1.2.3)

Fixed bulk install failure caused by sending a raw URL string to MainWP Child's
`installplugintheme` callable. See the Phase 5 Modifications section above for
full details.

### Phase 4 (Critical install fix, v1.2.1)

Fixed bulk install failure caused by incorrect parameter passed to MainWP
Child's `installplugintheme` callable. See the Phase 4 Modifications section
above for full details.

### Phase 3 (Debug logging + improved error reporting, v1.2.0)

Added server-side debug logging for install operations and improved bulk install
error reporting. See the Phase 3 Modifications section above for full details.

### Phase 2 (Drag-and-drop package upload, v1.1.0)

Added drag-and-drop ZIP upload for custom SPFW builds and self-hosted package
URL support. See the Phase 2 Modifications section above for full details.

### Phase 1 (Initial implementation, v1.0.0)

Built the full dashboard-side extension modeled on the sister project
`onedogsolutions/mainwp-for-google-security-for-wordpress`. See the Phase 1
Modifications section above for full details.
