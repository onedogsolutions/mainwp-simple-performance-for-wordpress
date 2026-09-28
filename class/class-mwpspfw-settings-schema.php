<?php
/**
 * Settings schema.
 *
 * Single source of truth for every Simple Performance for WordPress (SPFW)
 * setting this extension can read/write, grouped into the same six tabs as
 * SPFW's own settings screen. Render and save both iterate this schema, so a
 * future SPFW option only needs one entry here.
 *
 * Settings are grouped (nested one level). Field keys use dot-notation
 * (e.g. 'core.disable_emojis'); the connector assembles/disassembles the
 * nested structure before pushing to the child.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Settings_Schema
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Settings_Schema {

	/**
	 * Field types this schema declares:
	 *
	 * - toggle             On/off checkbox. Stored/sent as '1'/'0'.
	 * - select             Enum dropdown, `options` => array( value => label ).
	 * - int                Integer input with `min`/`max`.
	 * - route_list         Newline-separated text area (routes, namespaces,
	 *                      PHP whitelist paths).
	 * - text               Single-line text input (`input_type` optionally
	 *                      'email'). Sent as a trimmed string.
	 * - textarea           Multi-line raw string (e.g. the custom CSP policy).
	 * - multi_check        Checkbox group; values whitelisted to `options`
	 *                      and stored/sent as an array.
	 * - permissions_policy Composite: per-feature "block" toggle plus a
	 *                      space-separated origins input. Sent as
	 *                      feature => tokens[] (blocked) or false (unblocked).
	 * - csp_directives     Composite: one space-separated token string per
	 *                      whitelisted directive; sent as
	 *                      directive => tokens[] (empty string clears it).
	 *
	 * @return array<string,array<string,mixed>> Ordered tab => tab definition.
	 */
	public static function get_tabs() {

		// Post-revision limits, matching the child UI: default / disable / 1..30.
		$revision_options = array(
			'default' => __( 'Default (unlimited)', 'mainwp-simple-performance-for-wordpress' ),
			'disable' => __( 'Disable revisions', 'mainwp-simple-performance-for-wordpress' ),
		);
		for ( $i = 1; $i <= 30; $i++ ) {
			$revision_options[ (string) $i ] = sprintf(
				/* translators: %d: number of revisions to keep. */
				__( 'Keep %d', 'mainwp-simple-performance-for-wordpress' ),
				$i
			);
		}

		return array(
			'core'        => array(
				'label'  => __( 'Core Optimizations', 'mainwp-simple-performance-for-wordpress' ),
				'group'  => 'core',
				'fields' => array(
					'disable_emojis'                  => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Emojis', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'disable_embeds'                  => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Embeds', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'disable_dashicons'               => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Dashicons for Logged-Out Users', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
	
					'remove_rsd'                      => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove RSD Link', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'remove_wlwmanifest'              => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove WLW Manifest Link', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'hide_wp_version'                 => array(
						'type'    => 'toggle',
						'label'   => __( 'Hide WordPress Version', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'remove_shortlink'                => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove Shortlink', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'remove_rest_api_links'           => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove REST API Links from Head', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'disable_feeds'                   => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable RSS Feeds', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'feed_redirect_home'              => array(
						'type'     => 'toggle',
						'label'    => __( 'Redirect Feeds to Homepage', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'core.disable_feeds' => '1' ),
					),
					'remove_feed_links'               => array(
						'type'     => 'toggle',
						'label'    => __( 'Remove Feed Links from Head', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'core.disable_feeds' => '1' ),
					),
					'disable_self_pingbacks'          => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Self-Pingbacks', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'remove_query_strings'            => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove Query Strings from Static Resources', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'disable_google_maps'             => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Google Maps', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'google_maps_exceptions'          => array(
						'type'        => 'route_list',
						'label'       => __( 'Google Maps Exceptions (one page slug per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Pages where Google Maps should still load, even when disabled above.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
						'requires'    => array( 'core.disable_google_maps' => '1' ),
					),
					'disable_password_meter'          => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Password Strength Meter', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'disable_comments'                => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Comments', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'remove_comment_urls'             => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove Comment Author URLs', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'blank_favicon'                   => array(
						'type'    => 'toggle',
						'label'   => __( 'Serve Blank Favicon', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'heartbeat_control'               => array(
						'type'    => 'select',
						'label'   => __( 'Heartbeat Control', 'mainwp-simple-performance-for-wordpress' ),
						'options' => array(
							'default'     => __( 'Default (WordPress controls)', 'mainwp-simple-performance-for-wordpress' ),
							'disable'     => __( 'Disable everywhere', 'mainwp-simple-performance-for-wordpress' ),
							'allow_posts' => __( 'Allow only on post edit screens', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default' => 'default',
					),
					'heartbeat_frequency'             => array(
						'type'        => 'select',
						'label'       => __( 'Heartbeat Frequency', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Only applies when Heartbeat is not fully disabled.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'0'   => __( 'Default', 'mainwp-simple-performance-for-wordpress' ),
							'15'  => __( '15 seconds', 'mainwp-simple-performance-for-wordpress' ),
							'30'  => __( '30 seconds', 'mainwp-simple-performance-for-wordpress' ),
							'60'  => __( '60 seconds', 'mainwp-simple-performance-for-wordpress' ),
							'120' => __( '120 seconds', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => '0',
					),
					'post_revisions'                  => array(
						'type'    => 'select',
						'label'   => __( 'Post Revisions', 'mainwp-simple-performance-for-wordpress' ),
						'options' => $revision_options,
						'default' => 'default',
					),
					'autosave_interval'               => array(
						'type'        => 'select',
						'label'       => __( 'Autosave Interval', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'How often the editor autosaves a draft. The child clamps this to 0–5 minutes.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'0' => __( 'Default (1 minute)', 'mainwp-simple-performance-for-wordpress' ),
							'1' => __( '1 minute', 'mainwp-simple-performance-for-wordpress' ),
							'2' => __( '2 minutes', 'mainwp-simple-performance-for-wordpress' ),
							'3' => __( '3 minutes', 'mainwp-simple-performance-for-wordpress' ),
							'4' => __( '4 minutes', 'mainwp-simple-performance-for-wordpress' ),
							'5' => __( '5 minutes', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => '0',
					),
					'disable_jquery_migrate'          => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable jQuery Migrate', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '1',
					),
					'disable_wp_sitemaps'             => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable WordPress Sitemaps', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'remove_robots_max_image_preview' => array(
						'type'    => 'toggle',
						'label'   => __( 'Remove max-image-preview from Robots Meta', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'disable_block_css'               => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Block Editor Frontend CSS', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'block_css_smart_mode'            => array(
						'type'        => 'toggle',
						'label'       => __( 'Smart Mode (preserve first-contentful-paint CSS)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Keeps the minimal CSS needed for the first paint; only non-critical block styles are removed.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
						'requires'    => array( 'core.disable_block_css' => '1' ),
					),
					'streamline_dashboard'            => array(
						'type'        => 'toggle',
						'label'       => __( 'Streamline WordPress Dashboard', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Removes non-essential dashboard widgets, including those that make outbound HTTP requests.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'disable_wp_cron'                 => array(
						'type'        => 'toggle',
						'label'       => __( 'Disable WP-Cron', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Stops WordPress from spawning virtual cron jobs on every page load. Only enable with a real system cron configured on the server.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'cron_lock_timeout'               => array(
						'type'        => 'select',
						'label'       => __( 'Cron Lock Timeout', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Prevents overlapping cron runs when a real system cron triggers events. Only applies when WP-Cron is disabled above.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'0'   => __( 'Default (60 seconds)', 'mainwp-simple-performance-for-wordpress' ),
							'60'  => __( '60 seconds', 'mainwp-simple-performance-for-wordpress' ),
							'120' => __( '120 seconds', 'mainwp-simple-performance-for-wordpress' ),
							'300' => __( '300 seconds (5 minutes)', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => '0',
						'requires'    => array( 'core.disable_wp_cron' => '1' ),
					),
					'speculation_rules'               => array(
						'type'        => 'select',
						'label'       => __( 'Speculation Rules', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'WordPress 6.5+ prefetches and prerenders likely next pages. Conservative keeps prefetch only; Disable removes the script entirely.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'default'      => __( 'Default (WordPress behavior)', 'mainwp-simple-performance-for-wordpress' ),
							'conservative' => __( 'Conservative (prefetch only, no prerender)', 'mainwp-simple-performance-for-wordpress' ),
							'disable'      => __( 'Disable entirely', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => 'default',
					),
					'disable_search'                  => array(
						'type'        => 'toggle',
						'label'       => __( 'Disable Site Search', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Blocks public ?s= search queries on the frontend — an abuse vector on sites where search is unused.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'search_redirect_home'            => array(
						'type'        => 'toggle',
						'label'       => __( 'Redirect Search Requests to Homepage', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Otherwise blocked search requests return a 404.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
						'requires'    => array( 'core.disable_search' => '1' ),
					),
					'disable_scaled_images'           => array(
						'type'        => 'toggle',
						'label'       => __( 'Disable Scaled Image Generation', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Prevents the -scaled copy of images larger than 2560px. Saves significant disk space on image-heavy sites.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'disabled_image_sizes'            => array(
						'type'        => 'multi_check',
						'label'       => __( 'Disable Intermediate Image Sizes', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Skip generating these sizes on upload. Applies going forward only; existing files are not deleted.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'1536x1536'    => __( '1536×1536 (scaled)', 'mainwp-simple-performance-for-wordpress' ),
							'2048x2048'    => __( '2048×2048 (scaled)', 'mainwp-simple-performance-for-wordpress' ),
							'medium_large' => __( 'medium_large (768px)', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => array(),
					),
				),
			),
			'restapi'     => array(
				'label'  => __( 'REST API', 'mainwp-simple-performance-for-wordpress' ),
				'group'  => 'restapi',
				'fields' => array(
					'require_auth'        => array(
						'type'        => 'toggle',
						'label'       => __( 'Require Authentication for All REST Requests', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Blocks unauthenticated access to the REST API entirely.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'disabled_namespaces' => array(
						'type'        => 'route_list',
						'label'       => __( 'Disabled Namespaces (one per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'REST API namespace prefixes to block for unauthenticated users.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => "wp/v2/users\nwp/v2/themes\nwp/v2/comments\nwp/v2/settings\nwp/v2/taxonomies",
					),
					'whitelist_routes'    => array(
						'type'        => 'route_list',
						'label'       => __( 'Whitelisted Routes (one per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Routes always allowed, even when require_auth is on.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => "contact-form-7/v1\nwc/v3\nwc/store",
					),
				),
			),
			'hardening'   => array(
				'label'  => __( 'Hardening', 'mainwp-simple-performance-for-wordpress' ),
				'group'  => 'hardening',
				'fields' => array(
					'plugins_htaccess'      => array(
						'type'        => 'toggle',
						'label'       => __( 'Plugins Directory Hardening (.htaccess)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Blocks direct PHP execution in wp-content/plugins.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'uploads_htaccess'      => array(
						'type'        => 'toggle',
						'label'       => __( 'Uploads Directory Hardening (.htaccess)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Blocks direct PHP execution in wp-content/uploads.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'disable_file_editing'  => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Plugin/Theme File Editing', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'block_author_enum'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Block Author Enumeration', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'security_headers'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Security Headers', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'permissions_policy'    => array(
						'type'        => 'permissions_policy',
						'label'       => __( 'Permissions-Policy Features', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Browser features to restrict via the Permissions-Policy header. Checked features are blocked with an empty allowlist; enter space-separated origins (e.g. \'self\' https://example.com) to allow them instead. Applies while Security Headers is on.', 'mainwp-simple-performance-for-wordpress' ),
						'features'    => array( 'geolocation', 'microphone', 'camera', 'payment', 'usb', 'interest-cohort' ),
						'default'     => array(
							'geolocation'     => array(),
							'microphone'      => array(),
							'camera'          => array(),
							'payment'         => array(),
							'usb'             => array(),
							'interest-cohort' => array(),
						),
						'requires'    => array( 'hardening.security_headers' => '1' ),
					),
					'csp_enabled'           => array(
						'type'    => 'toggle',
						'label'   => __( 'Content Security Policy', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'csp_report_only'       => array(
						'type'     => 'toggle',
						'label'    => __( 'CSP Report-Only Mode', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_exclude_logged_in' => array(
						'type'     => 'toggle',
						'label'    => __( 'Exclude Logged-In Users from CSP', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '1',
						'requires' => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_mode'              => array(
						'type'     => 'select',
						'label'    => __( 'CSP Mode', 'mainwp-simple-performance-for-wordpress' ),
						'options'  => array(
							'builder' => __( 'Builder (structured directives)', 'mainwp-simple-performance-for-wordpress' ),
							'custom'  => __( 'Custom (raw policy string)', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'  => 'builder',
						'requires' => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_policy'            => array(
						'type'        => 'textarea',
						'label'       => __( 'Custom CSP Policy', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'The raw policy string used while CSP Mode is Custom. Leave empty to use the child\'s recommended default. Line breaks are flattened by the child on save.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
						'requires'    => array( 'hardening.csp_mode' => 'custom' ),
					),
					'csp_directives'        => array(
						'type'        => 'csp_directives',
						'label'       => __( 'CSP Directives', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'One space-separated source list per directive (e.g. \'self\' data: https:). Clearing a field empties that directive. The child\'s own admin additionally offers a visual builder with preset chips.', 'mainwp-simple-performance-for-wordpress' ),
						'directives'  => array( 'default-src', 'script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'media-src', 'worker-src', 'object-src', 'frame-src', 'frame-ancestors', 'base-uri', 'form-action' ),
						'default'     => array(
							'default-src'     => array( "'self'" ),
							'img-src'         => array( "'self'", 'data:', 'https:' ),
							'style-src'       => array( "'self'", "'unsafe-inline'", 'https:' ),
							'script-src'      => array( "'self'", "'unsafe-inline'", 'https:', 'data:', 'blob:' ),
							'font-src'        => array( "'self'", 'data:', 'https:' ),
							'connect-src'     => array( "'self'", 'https:' ),
							'media-src'       => array( "'self'" ),
							'worker-src'      => array( "'self'", 'blob:' ),
							'frame-src'       => array( "'self'", 'https:' ),
							'object-src'      => array( "'none'" ),
							'base-uri'        => array( "'self'" ),
							'frame-ancestors' => array( "'self'" ),
						),
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_tighten_script_src' => array(
						'type'        => 'toggle',
						'label'       => __( 'CSP: Tighten script-src with Hashes (Advanced)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Replaces \'unsafe-inline\' in script-src with sha256 hashes of the site\'s inline scripts. Real XSS protection, but it requires re-scanning from the child admin after every plugin or theme change — keep CSP in Report-Only mode until the violation log is clean.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_strict_dynamic'    => array(
						'type'        => 'toggle',
						'label'       => __( 'CSP: \'strict-dynamic\'', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Browsers that support it ignore every host and scheme source in script-src — only hashed scripts, and what those scripts load themselves, may run. A much bigger change than hashing; test in Report-Only mode first.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_rate_limit'        => array(
						'type'        => 'int',
						'label'       => __( 'CSP: Max New Violations per Minute', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Per-minute ceiling on new (directive, origin) pairs entering the violation log. Raise on tracker-heavy sites if reports are being dropped. Clamped to 5–60 by the child.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '10',
						'min'         => 5,
						'max'         => 60,
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'hsts_enabled'          => array(
						'type'    => 'toggle',
						'label'   => __( 'HSTS (HTTP Strict Transport Security)', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'hsts_max_age'          => array(
						'type'     => 'select',
						'label'    => __( 'HSTS Max-Age', 'mainwp-simple-performance-for-wordpress' ),
						'options'  => array(
							'86400'    => __( '1 day', 'mainwp-simple-performance-for-wordpress' ),
							'604800'   => __( '1 week', 'mainwp-simple-performance-for-wordpress' ),
							'2592000'  => __( '30 days', 'mainwp-simple-performance-for-wordpress' ),
							'15768000' => __( '6 months', 'mainwp-simple-performance-for-wordpress' ),
							'31536000' => __( '1 year', 'mainwp-simple-performance-for-wordpress' ),
							'63072000' => __( '2 years', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'  => '31536000',
						'requires' => array( 'hardening.hsts_enabled' => '1' ),
					),
					'hsts_include_subdomains' => array(
						'type'     => 'toggle',
						'label'    => __( 'HSTS Include Subdomains', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'hardening.hsts_enabled' => '1' ),
					),
					'hsts_preload'          => array(
						'type'     => 'toggle',
						'label'    => __( 'HSTS Preload', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'hardening.hsts_enabled' => '1' ),
					),
					'disable_xmlrpc'        => array(
						'type'        => 'toggle',
						'label'       => __( 'Disable XML-RPC', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Disables the XML-RPC endpoint via PHP filter.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
					),
					'block_xmlrpc_file'     => array(
						'type'        => 'toggle',
						'label'       => __( 'Block xmlrpc.php at Server Level (.htaccess)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Adds a root .htaccess rule to block direct access to xmlrpc.php before WordPress loads. Layers on top of the PHP disable above. MainWP is unaffected — it uses its own signed channel.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
						'requires'    => array( 'hardening.disable_xmlrpc' => '1' ),
					),
					'disable_app_passwords' => array(
						'type'    => 'toggle',
						'label'   => __( 'Disable Application Passwords', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'generic_login_errors'  => array(
						'type'        => 'toggle',
						'label'       => __( 'Generic Login Error Messages', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Replaces specific "invalid username" / "incorrect password" messages with a generic error to prevent username enumeration.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'protect_sensitive_files' => array(
						'type'        => 'toggle',
						'label'       => __( 'Protect Sensitive Files (root .htaccess)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Adds root-level .htaccess rules to block direct access to wp-config.php, .htaccess, and other sensitive files.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'php_whitelist'         => array(
						'type'        => 'route_list',
						'label'       => __( 'PHP Execution Whitelist (one wp-content-relative path per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Paths under plugins/ or uploads/ allowed to execute PHP even when directory hardening is on, e.g. plugins/litespeed-cache/guest.vary.php. Must end in a PHP extension the deny rule blocks.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
					),
					'auto_allow_known_php'  => array(
						'type'        => 'toggle',
						'label'       => __( 'Auto-allow Known Plugin Endpoints', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Automatically allows PHP files that well-known plugins serve directly over HTTP (e.g. LiteSpeed Cache guest.vary.php) when the file is actually installed, so enabling hardening never breaks them. Turn off for a total deny.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
					),
					'file_monitor_enabled'  => array(
						'type'        => 'toggle',
						'label'       => __( 'File Integrity Monitoring', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Periodically scans wp-content/plugins and wp-content/uploads for new, modified, or removed PHP files and emails an alert when changes are detected outside the whitelist.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'file_monitor_email'    => array(
						'type'        => 'text',
						'input_type'  => 'email',
						'label'       => __( 'File Monitor Alert Email', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Email address for file-change alerts. Leave empty to use the child site\'s admin email.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
						'requires'    => array( 'hardening.file_monitor_enabled' => '1' ),
					),
					'csp_collect_sample'    => array(
						'type'        => 'select',
						'label'       => __( 'CSP Violation Sample Rate (%)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Percentage of responses that include the report-uri directive when a collection window is open.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'1'   => __( '1%', 'mainwp-simple-performance-for-wordpress' ),
							'5'   => __( '5%', 'mainwp-simple-performance-for-wordpress' ),
							'10'  => __( '10%', 'mainwp-simple-performance-for-wordpress' ),
							'25'  => __( '25%', 'mainwp-simple-performance-for-wordpress' ),
							'50'  => __( '50%', 'mainwp-simple-performance-for-wordpress' ),
							'100' => __( '100%', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => '100',
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_collect_admin'     => array(
						'type'        => 'toggle',
						'label'       => __( 'CSP: Include Admin Browsing While Collecting', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'While a violation-collection window is open, send the policy to administrators too, overriding the logged-in exclusion — otherwise the one person deliberately testing the site is the one visitor the window cannot see.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
					'csp_collect_nocache'   => array(
						'type'        => 'toggle',
						'label'       => __( 'CSP: Bypass Page Cache While Collecting', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Recommended. The reporting header is set by PHP, which does not run for a cache hit, so leaving this off limits a collection window to whatever the cache regenerated while it was open.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '1',
						'requires'    => array( 'hardening.csp_enabled' => '1' ),
					),
				),
			),
			'fonts'       => array(
				'label'  => __( 'Fonts', 'mainwp-simple-performance-for-wordpress' ),
				'group'  => 'fonts',
				'fields' => array(
					'localize_google' => array(
						'type'        => 'toggle',
						'label'       => __( 'Localize Google Fonts', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Downloads Google Fonts and serves them locally for GDPR compliance and faster loading.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'manual_families' => array(
						'type'        => 'route_list',
						'label'       => __( 'Manual Font Families (one per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Format: Family:weights (e.g. "Roboto Condensed:400,700"). Use when the automatic scan misses fonts loaded via proxy/CDN.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
						'requires'    => array( 'fonts.localize_google' => '1' ),
					),
					'extra_scan_urls' => array(
						'type'        => 'route_list',
						'label'       => __( 'Extra Scan URLs (one per line)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Additional pages to scan for fonts beyond the homepage. Root-relative paths (/shop/) or same-origin URLs.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '',
						'requires'    => array( 'fonts.localize_google' => '1' ),
					),
				),
			),
			'woocommerce' => array(
				'label'              => __( 'WooCommerce', 'mainwp-simple-performance-for-wordpress' ),
				'group'              => 'woocommerce',
				'requires_woocommerce' => true,
				'fields'             => array(
					'disable_cart_fragments' => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable Cart Fragments', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'disable_scripts_styles' => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable WooCommerce Scripts & Styles on Non-Shop Pages', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'disable_status_widget'  => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable WooCommerce Status Widget', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'disable_widgets'        => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable WooCommerce Widgets', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'disable_password_meter' => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable WooCommerce Password Strength Meter', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
					'disable_marketing_hub'  => array(
						'type'                 => 'toggle',
						'label'                => __( 'Disable WooCommerce Marketing Hub', 'mainwp-simple-performance-for-wordpress' ),
						'default'              => '0',
						'requires_woocommerce' => true,
					),
				),
			),
			'database'    => array(
				'label'  => __( 'Database', 'mainwp-simple-performance-for-wordpress' ),
				'group'  => 'database',
				'fields' => array(
					'post_revisions'     => array(
						'type'        => 'toggle',
						'label'       => __( 'Clean Post Revisions', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Deletes stored revisions during a cleanup run. This is a cleanup target — separate from the Core tab\'s revision-limit setting, which only caps future revisions.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'post_auto_drafts'   => array(
						'type'    => 'toggle',
						'label'   => __( 'Clean Auto Drafts', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'trashed_posts'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Clean Trashed Posts', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'spam_comments'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Clean Spam Comments', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'trashed_comments'   => array(
						'type'    => 'toggle',
						'label'   => __( 'Clean Trashed Comments', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'expired_transients' => array(
						'type'    => 'toggle',
						'label'   => __( 'Clean Expired Transients', 'mainwp-simple-performance-for-wordpress' ),
						'default' => '0',
					),
					'all_transients'     => array(
						'type'        => 'toggle',
						'label'       => __( 'Clean All Transients', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Includes transients that have not expired yet — some plugins may need to rebuild their caches afterward.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'tables'             => array(
						'type'        => 'toggle',
						'label'       => __( 'Optimize Tables', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Repairs fragmented tables during a cleanup run.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
					),
					'optimize_schedule'  => array(
						'type'        => 'select',
						'label'       => __( 'Automatic Optimization Schedule', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Runs table optimization via WP-Cron at the chosen interval. One-off cleanups and the database scan live in the child site\'s own admin.', 'mainwp-simple-performance-for-wordpress' ),
						'options'     => array(
							'off'     => __( 'Off', 'mainwp-simple-performance-for-wordpress' ),
							'daily'   => __( 'Daily', 'mainwp-simple-performance-for-wordpress' ),
							'weekly'  => __( 'Weekly', 'mainwp-simple-performance-for-wordpress' ),
							'monthly' => __( 'Monthly', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default'     => 'off',
					),
				),
			),
		);
	}

	/**
	 * Flatten the tabs into a single dot-notation key => field-definition map,
	 * each field annotated with the tab and group it belongs to.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields() {
		$fields = array();
		foreach ( self::get_tabs() as $tab_id => $tab ) {
			$group = isset( $tab['group'] ) ? $tab['group'] : $tab_id;
			foreach ( $tab['fields'] as $key => $field ) {
				$field['tab']              = $tab_id;
				$field['group']            = $group;
				$fields[ $group . '.' . $key ] = $field;
			}
		}
		return $fields;
	}

	/**
	 * The default value for every field, keyed by dot-notation settings key.
	 *
	 * Used to fill in a row before a site has been contacted, and as the
	 * fallback when the child's response is missing a key.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$defaults = array();
		foreach ( self::get_fields() as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}
		return $defaults;
	}

	/**
	 * Convert a nested SPFW settings array into flat dot-notation values
	 * suitable for form rendering.
	 *
	 * @param array $settings Nested settings from the child (group => fields).
	 * @return array<string,mixed> Flat dot-notation => value.
	 */
	public static function flatten( array $settings ) {
		$flat = array();
		foreach ( self::get_fields() as $dot_key => $field ) {
			$group = $field['group'];
			$key   = substr( $dot_key, strlen( $group ) + 1 );

			if ( isset( $settings[ $group ] ) && is_array( $settings[ $group ] ) && array_key_exists( $key, $settings[ $group ] ) ) {
				$value = $settings[ $group ][ $key ];

				// Normalize booleans to '1'/'0' for toggle fields.
				if ( 'toggle' === $field['type'] ) {
					$value = ! empty( $value ) ? '1' : '0';
				}

				// Normalize arrays (route_list) to newline-separated strings.
				if ( 'route_list' === $field['type'] && is_array( $value ) ) {
					$value = implode( "\n", $value );
				}

				$flat[ $dot_key ] = $value;
			} else {
				$flat[ $dot_key ] = $field['default'];
			}
		}
		return $flat;
	}

	/**
	 * Convert flat dot-notation form values back into the nested structure
	 * SPFW_Settings::update() expects.
	 *
	 * The child merges the payload over its stored settings, so keys this
	 * form does not manage (hashes, timestamps, scan caches) are preserved
	 * untouched; arrays in the payload replace their stored counterparts
	 * outright.
	 *
	 * @param array $flat Flat dot-notation => value from the form.
	 * @return array Nested group => fields array.
	 */
	public static function nest( array $flat ) {
		$nested = array();
		foreach ( self::get_fields() as $dot_key => $field ) {
			if ( ! array_key_exists( $dot_key, $flat ) ) {
				continue;
			}

			$group = $field['group'];
			$key   = substr( $dot_key, strlen( $group ) + 1 );
			$value = $flat[ $dot_key ];

			if ( ! isset( $nested[ $group ] ) ) {
				$nested[ $group ] = array();
			}

			// Convert toggle '1'/'0' to boolean. filter_var() also maps the
			// strings 'true'/'false' — which a stale client could post for a
			// re-hydrated toggle — onto the correct booleans.
			if ( 'toggle' === $field['type'] ) {
				$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}

			// Convert route_list newline-separated string to array.
			if ( 'route_list' === $field['type'] && is_string( $value ) ) {
				$value = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $value ) ) ) );
			}

			// Integer fields.
			if ( 'int' === $field['type'] ) {
				$value = intval( $value );
			}

			// Single-line text and raw textarea strings: light trim only — the
			// child's own sanitize() remains the authority.
			if ( 'text' === $field['type'] || 'textarea' === $field['type'] ) {
				$value = trim( (string) $value );
			}

			// multi_check: keep only declared options, in declared order.
			if ( 'multi_check' === $field['type'] ) {
				$value = is_array( $value )
					? array_values( array_intersect( array_keys( $field['options'] ), array_map( 'strval', $value ) ) )
					: array();
			}

			// Composites: posted sub-arrays → the child's stored shapes.
			if ( 'permissions_policy' === $field['type'] && is_array( $value ) ) {
				$value = self::nest_permissions_policy( $value, $field );
			}

			if ( 'csp_directives' === $field['type'] && is_array( $value ) ) {
				$value = self::nest_csp_directives( $value, $field );
			}

			$nested[ $group ][ $key ] = $value;
		}
		return $nested;
	}

	/**
	 * Convert a posted permissions-policy composite into the child's stored
	 * shape: feature => tokens[] when blocked (an empty allowlist is an empty
	 * array), or false when the feature is unblocked (the child drops it).
	 *
	 * @param array $posted Per-feature sub-arrays: block ('1'/'0') + origins string.
	 * @param array $field  Field definition (provides the feature whitelist).
	 * @return array<string,string[]|false>
	 */
	private static function nest_permissions_policy( array $posted, array $field ) {
		$features = isset( $field['features'] ) && is_array( $field['features'] ) ? $field['features'] : array();
		$nested   = array();

		foreach ( $features as $feature ) {
			if ( ! isset( $posted[ $feature ] ) || ! is_array( $posted[ $feature ] ) ) {
				continue;
			}

			$blocked = ! empty( $posted[ $feature ]['block'] ) && '0' !== $posted[ $feature ]['block'];
			if ( ! $blocked ) {
				$nested[ $feature ] = false;
				continue;
			}

			$origins            = isset( $posted[ $feature ]['origins'] ) ? (string) $posted[ $feature ]['origins'] : '';
			$nested[ $feature ] = array_values( array_filter( array_map( 'trim', preg_split( '/\s+/', $origins ) ) ) );
		}

		return $nested;
	}

	/**
	 * Convert a posted CSP-directive composite into the child's stored shape:
	 * directive => tokens[]. An empty string becomes an empty array — the
	 * child preserves empty token lists precisely so clearing a directive
	 * sticks instead of the default value resurrecting on the next merge.
	 *
	 * @param array $posted Directive => space-separated token string.
	 * @param array $field  Field definition (provides the directive whitelist).
	 * @return array<string,string[]>
	 */
	private static function nest_csp_directives( array $posted, array $field ) {
		$directives = isset( $field['directives'] ) && is_array( $field['directives'] ) ? $field['directives'] : array();
		$nested     = array();

		foreach ( $directives as $directive ) {
			if ( ! array_key_exists( $directive, $posted ) ) {
				continue;
			}

			$raw                 = is_string( $posted[ $directive ] ) ? $posted[ $directive ] : '';
			$nested[ $directive ] = array_values( array_filter( array_map( 'trim', preg_split( '/\s+/', $raw ) ) ) );
		}

		return $nested;
	}
}
