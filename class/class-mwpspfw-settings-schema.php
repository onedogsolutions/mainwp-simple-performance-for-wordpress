<?php
/**
 * Settings schema.
 *
 * Single source of truth for every Simple Performance for WordPress (SPFW)
 * setting this extension can read/write, grouped into the same five tabs as
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
	 * - toggle      On/off checkbox. Stored/sent as '1'/'0'.
	 * - select      Enum dropdown, `options` => array( value => label ).
	 * - int         Integer input with `min`/`max`.
	 * - route_list  Newline-separated text area (routes, namespaces, families).
	 * - readonly    Display-only (e.g. CSP directives summary).
	 *
	 * @return array<string,array<string,mixed>> Ordered tab => tab definition.
	 */
	public static function get_tabs() {
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
						'type'     => 'toggle',
						'label'    => __( 'Remove Comment Author URLs', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'core.disable_comments' => '0' ),
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
						'options' => array(
							'default' => __( 'Default (unlimited)', 'mainwp-simple-performance-for-wordpress' ),
							'disable' => __( 'Disable revisions', 'mainwp-simple-performance-for-wordpress' ),
							'1'       => __( 'Keep 1', 'mainwp-simple-performance-for-wordpress' ),
							'3'       => __( 'Keep 3', 'mainwp-simple-performance-for-wordpress' ),
							'5'       => __( 'Keep 5', 'mainwp-simple-performance-for-wordpress' ),
							'10'      => __( 'Keep 10', 'mainwp-simple-performance-for-wordpress' ),
							'15'      => __( 'Keep 15', 'mainwp-simple-performance-for-wordpress' ),
							'20'      => __( 'Keep 20', 'mainwp-simple-performance-for-wordpress' ),
							'30'      => __( 'Keep 30', 'mainwp-simple-performance-for-wordpress' ),
						),
						'default' => 'default',
					),
					'autosave_interval'               => array(
						'type'        => 'int',
						'label'       => __( 'Autosave Interval (minutes)', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( '0 = WordPress default (60s). Range: 1–10 minutes.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
						'min'         => 0,
						'max'         => 10,
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
						'type'     => 'toggle',
						'label'    => __( 'Remove Comment Author URLs', 'mainwp-simple-performance-for-wordpress' ),
						'default'  => '0',
						'requires' => array( 'core.disable_comments' => '1' ),
					),
					'blank_favicon'                   => array(
						'type'    => 'toggle',
						'label'   => __( 'Serve Blank Favicon', 'mainwp-simple-performance-for-wordpress' ),
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
						'default'     => '0',
						'requires'    => array( 'core.disable_block_css' => '1' ),
					),
					'streamline_dashboard'            => array(
						'type'        => 'toggle',
						'label'       => __( 'Streamline WordPress Dashboard', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'Removes non-essential dashboard widgets, including those that make outbound HTTP requests.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
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
					'csp_directives'        => array(
						'type'        => 'readonly',
						'label'       => __( 'CSP Directives', 'mainwp-simple-performance-for-wordpress' ),
						'description' => __( 'The structured CSP directive builder is only available in the child site\'s own admin. This read-only summary shows the current policy.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => array(),
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
						'description' => __( 'Adds a root .htaccess rule to block direct access to xmlrpc.php before WordPress loads.', 'mainwp-simple-performance-for-wordpress' ),
						'default'     => '0',
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
	 * @param array $flat Flat dot-notation => value from the form.
	 * @return array Nested group => fields array.
	 */
	public static function nest( array $flat ) {
		$nested = array();
		foreach ( self::get_fields() as $dot_key => $field ) {
			if ( 'readonly' === $field['type'] ) {
				continue;
			}

			if ( ! array_key_exists( $dot_key, $flat ) ) {
				continue;
			}

			$group = $field['group'];
			$key   = substr( $dot_key, strlen( $group ) + 1 );
			$value = $flat[ $dot_key ];

			if ( ! isset( $nested[ $group ] ) ) {
				$nested[ $group ] = array();
			}

			// Convert toggle '1'/'0' to boolean.
			if ( 'toggle' === $field['type'] ) {
				$value = ! empty( $value ) && '0' !== $value;
			}

			// Convert route_list newline-separated string to array.
			if ( 'route_list' === $field['type'] && is_string( $value ) ) {
				$value = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $value ) ) ) );
			}

			// Integer fields.
			if ( 'int' === $field['type'] ) {
				$value = intval( $value );
			}

			$nested[ $group ][ $key ] = $value;
		}
		return $nested;
	}
}
