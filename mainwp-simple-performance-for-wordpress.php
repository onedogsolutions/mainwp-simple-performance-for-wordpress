<?php
/**
 * Plugin Name: MainWP for Simple Performance for WordPress
 * Plugin URI: https://onedog.solutions/
 * Description: Configure the Simple Performance for WordPress plugin on each connected child site directly from the MainWP Dashboard. Manage core optimizations, REST API restrictions, hardening, fonts, WooCommerce tweaks, and database cleanup per site.
 * Version: 1.6.0
 * Author: One Dog Solutions
 * Author URI: https://onedog.solutions/
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: mainwp-simple-performance-for-wordpress
 * Domain Path: /languages
 * Documentation URI: https://github.com/onedogsolutions/mainwp-simple-performance-for-wordpress
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'MWPSPFW_PLUGIN_FILE' ) ) {
	define( 'MWPSPFW_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MWPSPFW_PLUGIN_DIR' ) ) {
	define( 'MWPSPFW_PLUGIN_DIR', plugin_dir_path( MWPSPFW_PLUGIN_FILE ) );
}

if ( ! defined( 'MWPSPFW_PLUGIN_URL' ) ) {
	define( 'MWPSPFW_PLUGIN_URL', plugin_dir_url( MWPSPFW_PLUGIN_FILE ) );
}

if ( ! defined( 'MWPSPFW_VERSION' ) ) {
	define( 'MWPSPFW_VERSION', '1.6.0' );
}

/**
 * Class MWPSPFW_Activator
 *
 * Bootstraps the extension: registers it with the MainWP Dashboard, waits for
 * MainWP to confirm activation, then wires the per-site "Performance" tab
 * and the AJAX endpoints that back it. Mirrors the structure of the official
 * MainWP Development Extension starter.
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Activator {

	/**
	 * Whether the MainWP Dashboard plugin is active, or its extension-enabled
	 * payload (array with a 'key' member) once resolved.
	 *
	 * @var bool|array
	 */
	protected $mainwpMainActivated = false;

	/**
	 * The 'mainwp_extension_enabled_check' payload for this plugin.
	 *
	 * @var bool|array
	 */
	protected $childEnabled = false;

	/**
	 * This extension's signed child key, used to authorize every
	 * 'mainwp_fetchurlauthed' call this extension makes.
	 *
	 * @var bool|string
	 */
	protected $childKey = false;

	/**
	 * This plugin's main file path, passed as the 'plugin' identity to every
	 * MainWP extension filter.
	 *
	 * @var string
	 */
	protected $childFile;

	/**
	 * Extension handle used to register with MainWP (mainwp_current_user_can
	 * capability checks, activation/deactivation hooks).
	 *
	 * @var string
	 */
	protected $plugin_handle = 'mainwp-simple-performance-for-wordpress';

	/**
	 * Human-readable product name, reported to MainWP on activation.
	 *
	 * @var string
	 */
	protected $product_id = 'MainWP for Simple Performance for WordPress';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->childFile = __FILE__;

		spl_autoload_register( array( $this, 'autoload' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		/**
		 * Registers this extension with the MainWP Dashboard's Extensions
		 * page. MainWP calls the returned 'callback' to render our entry
		 * there; the real per-site configuration screen is registered
		 * separately, once MainWP confirms activation, via
		 * 'mainwp_getsubpages_sites'.
		 */
		add_filter( 'mainwp_getextensions', array( $this, 'get_this_extension' ) );

		/**
		 * Corrects the <h1>-style title MainWP prints atop our own Extensions
		 * page. That title is reconstructed from the page's URL slug, which
		 * MainWP derives from this plugin's folder name. The naive transform
		 * produces a mangled string; this filter is MainWP's own escape hatch.
		 */
		add_filter( 'mainwp_extensions_page_top_header', array( $this, 'fix_extensions_page_title' ), 10, 2 );

		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', false );
		if ( false !== $this->mainwpMainActivated ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp_activated', array( $this, 'activate_this_plugin' ) );
		}

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Autoloader for this extension's classes.
	 *
	 * Class MWPSPFW_Foo_Bar autoloads from class/class-mwpspfw-foo-bar.php.
	 *
	 * @param string $class_name Fully qualified class name being instantiated.
	 */
	public function autoload( $class_name ) {
		if ( 0 === strpos( $class_name, __NAMESPACE__ . '\\' ) ) {
			$class_name = str_replace( __NAMESPACE__ . '\\', '', $class_name );
		} else {
			return;
		}

		if ( 0 !== strpos( $class_name, 'MWPSPFW_' ) ) {
			return;
		}

		$class_file = MWPSPFW_PLUGIN_DIR . 'class' . DIRECTORY_SEPARATOR . 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}

	/**
	 * Add this extension to MainWP's Extensions page.
	 *
	 * @param array $extensions Extensions MainWP already knows about.
	 * @return array Extensions including this one.
	 */
	public function get_this_extension( $extensions ) {
		$extensions[] = array(
			'plugin'     => __FILE__,
			'api'        => $this->plugin_handle,
			'mainwp'     => true,
			'name'       => 'Simple Performance for WordPress',
			'callback'   => array( $this, 'render_extensions_page' ),
			'apiManager' => false,
		);

		return $extensions;
	}

	/**
	 * Filter callback for 'mainwp_extensions_page_top_header': replaces
	 * MainWP's naively slug-derived title with our real display name when
	 * the current page is ours.
	 *
	 * @param string $title    MainWP's computed title.
	 * @param string $raw_page The raw `$_GET['page']` slug this was derived from.
	 * @return string
	 */
	public function fix_extensions_page_title( $title, $raw_page ) {
		$our_page = 'Extensions-' . str_replace( ' ', '-', ucwords( str_replace( '-', ' ', dirname( plugin_basename( __FILE__ ) ) ) ) );

		if ( $raw_page === $our_page ) {
			return __( 'Simple Performance for WordPress', 'mainwp-simple-performance-for-wordpress' );
		}

		return $title;
	}

	/**
	 * Render the Extensions-page landing screen: a list of connected sites
	 * with a link to each one's "Performance" configuration tab.
	 */
	public function render_extensions_page() {
		do_action( 'mainwp_pageheader_extensions', __FILE__ );
		MWPSPFW_Overview::get_instance()->render_page();
		do_action( 'mainwp_pagefooter_extensions', __FILE__ );
	}

	/**
	 * Runs once MainWP confirms it is active. Resolves this extension's
	 * signed child key, registers the per-site tab, and boots the admin
	 * (assets + AJAX) class.
	 */
	public function activate_this_plugin() {
		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', $this->mainwpMainActivated );
		$this->childEnabled        = apply_filters( 'mainwp_extension_enabled_check', __FILE__ );
		$this->childKey            = is_array( $this->childEnabled ) && isset( $this->childEnabled['key'] ) ? $this->childEnabled['key'] : false;

		if ( function_exists( 'mainwp_current_user_can' ) && ! mainwp_current_user_can( 'extension', $this->plugin_handle ) ) {
			return;
		}

		add_filter( 'mainwp_getsubpages_sites', array( $this, 'hook_managesites_subpage' ) );

		MWPSPFW_Admin::get_instance();
	}

	/**
	 * Register the per-site "Performance" tab via 'mainwp_getsubpages_sites'.
	 *
	 * `sitetab => true` places it in the individual child site's own tab bar
	 * (Manage Sites -> site -> Performance) rather than the global Sites
	 * left-hand menu; `menu_hidden => true` keeps it out of that global menu
	 * entirely.
	 *
	 * @param array $sub_pages Sub pages MainWP already knows about.
	 * @return array Sub pages including ours.
	 */
	public function hook_managesites_subpage( $sub_pages ) {
		$sub_pages[] = array(
			'title'       => __( 'Performance', 'mainwp-simple-performance-for-wordpress' ),
			'slug'        => 'SPFWConfig',
			'sitetab'     => true,
			'menu_hidden' => true,
			'callback'    => array( MWPSPFW_Individual::get_instance(), 'render_page' ),
		);

		return $sub_pages;
	}

	/**
	 * Get this extension's signed child key.
	 *
	 * @return bool|string
	 */
	public function get_child_key() {
		return $this->childKey;
	}

	/**
	 * Get this plugin's main file path.
	 *
	 * @return string
	 */
	public function get_child_file() {
		return $this->childFile;
	}

	/**
	 * Show an admin notice on the Plugins screen when MainWP Dashboard is
	 * missing.
	 */
	public function admin_notices() {
		global $current_screen;
		if ( isset( $current_screen->parent_base ) && 'plugins' === $current_screen->parent_base && false === $this->mainwpMainActivated ) {
			echo '<div class="error"><p>' . sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				esc_html__( 'MainWP for Simple Performance for WordPress requires the %1$sMainWP Dashboard plugin%2$s to be installed and activated.', 'mainwp-simple-performance-for-wordpress' ),
				'<a href="https://mainwp.com/" target="_blank" rel="noopener noreferrer">',
				'</a>'
			) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup with escaped substitutions above.
		}
	}

	/**
	 * Report activation to MainWP.
	 */
	public function activate() {
		do_action(
			'mainwp_activate_extention',
			$this->plugin_handle,
			array(
				'product_id'       => $this->product_id,
				'software_version' => MWPSPFW_VERSION,
			)
		);
	}

	/**
	 * Report deactivation to MainWP.
	 */
	public function deactivate() {
		do_action( 'mainwp_deactivate_extention', $this->plugin_handle );
	}
}

global $mwpspfw_activator;
$mwpspfw_activator = new MWPSPFW_Activator();
