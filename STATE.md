# State Tracker - MainWP for Simple Performance for WordPress

A MainWP Dashboard extension for configuring the Simple Performance for WordPress
(SPFW) plugin on each connected child site. Installed on the MainWP Dashboard
site only. Companion plugin: `onedogsolutions/simple-performance-for-wordpress`
(its own STATE.md tracks the child-side work).

## Current Phase: Phase 1 (Initial implementation, v1.0.0)

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
- **Grouped settings structure.** SPFW uses nested groups
  (`array('core' => array(...), 'restapi' => array(...))`) vs GSWP's flat
  keys. Solved with `flatten()`/`nest()` helpers and dot-notation keys
  internally (`core.disable_emojis`).
- **CSP builder read-only.** The structured CSP directive builder is too
  complex for remote editing. Only the enable/disable/mode toggles are managed
  from the dashboard; the directives themselves display as a read-only summary.
- **Field naming convention.** Form fields use bracket notation
  (`fields[core][disable_emojis]`) to preserve the grouped structure through
  the save cycle.

## Child-Side Bridge (separate repository)

The child-side bridge (`includes/class-spfw-mainwp-child.php`) must be added to
the `onedogsolutions/simple-performance-for-wordpress` repository. It hooks
`mainwp_child_extra_execution` to handle `get_settings` and `update_settings`
actions from the dashboard, routing writes through the existing `SPFW_Rest_Settings`
callbacks so all validation stays single-source.

**Wire contract (frozen):**
- Request field: `spfw_action` (`get_settings` | `update_settings`)
- For updates: JSON-encoded `settings` field (`wp_unslash()` required child-side
  since `$_POST` arrives slashed)
- Response envelope key: `spfw` with `success`, `version`, `woocommerce_active`,
  `settings` fields

## Next Steps

- **End-to-end testing** on a live MainWP Dashboard with connected child sites
  running SPFW ≥ 1.8.0 (with the child bridge installed).
- **Child bridge implementation** in the SPFW repository (code provided, needs
  to be added and released in SPFW v1.9.0+).
- Verify: extension appears under Extensions, the per-site "Performance" tab
  renders inside MainWP chrome, settings save round-trips correctly, bulk
  install works on sites without SPFW.

## Historical Phases

(None — this is the initial release.)
