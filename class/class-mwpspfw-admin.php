<?php
/**
 * Admin wiring.
 *
 * Registers the AJAX endpoints backing the per-site configuration tab and the
 * Extensions-page overview, and provides the shared asset-enqueue helper both
 * screens call from their own render methods.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Admin
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether enqueue_assets() has already run this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Wires AJAX endpoints to the screens that own their logic.
	 */
	public function __construct() {
		add_action( 'wp_ajax_mwpspfw_save_settings', array( MWPSPFW_Individual::get_instance(), 'ajax_save' ) );
		add_action( 'wp_ajax_mwpspfw_check_site', array( MWPSPFW_Overview::get_instance(), 'ajax_check' ) );
		add_action( 'wp_ajax_mwpspfw_save_package', array( MWPSPFW_Overview::get_instance(), 'ajax_save_package' ) );
		add_action( 'wp_ajax_mwpspfw_upload_package', array( MWPSPFW_Overview::get_instance(), 'ajax_upload_package' ) );
		add_action( 'wp_ajax_mwpspfw_install_spfw', array( MWPSPFW_Overview::get_instance(), 'ajax_install' ) );
		add_action( 'wp_ajax_mwpspfw_get_log', array( MWPSPFW_Overview::get_instance(), 'ajax_get_log' ) );
		add_action( 'wp_ajax_mwpspfw_clear_log', array( MWPSPFW_Overview::get_instance(), 'ajax_clear_log' ) );
	}

	/**
	 * Enqueue this extension's admin script/style. Safe to call from every
	 * screen we render — WordPress only enqueues once per request, so a
	 * second call from the same page load is a no-op.
	 */
	public static function enqueue_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}
		self::$assets_enqueued = true;

		wp_enqueue_style(
			'mwpspfw-admin',
			MWPSPFW_PLUGIN_URL . 'assets/css/mwpspfw-admin.css',
			array(),
			MWPSPFW_VERSION
		);

		wp_enqueue_script(
			'mwpspfw-admin',
			MWPSPFW_PLUGIN_URL . 'assets/js/mwpspfw-admin.js',
			array(),
			MWPSPFW_VERSION,
			true
		);

		wp_localize_script(
			'mwpspfw-admin',
			'mwpspfwAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'saveNonce'      => wp_create_nonce( 'mwpspfw_save_settings' ),
				'checkNonce'     => wp_create_nonce( 'mwpspfw_check_site' ),
				'packageNonce'   => wp_create_nonce( 'mwpspfw_save_package' ),
				'uploadNonce'    => wp_create_nonce( 'mwpspfw_upload_package' ),
				'installNonce'   => wp_create_nonce( 'mwpspfw_install_spfw' ),
				'logNonce'       => wp_create_nonce( 'mwpspfw_debug_log' ),
				'confirmUnsaved' => __( 'You have unsaved changes. Leave this page anyway?', 'mainwp-simple-performance-for-wordpress' ),
				'confirmInstall' => __( 'Install (or reinstall/upgrade) Simple Performance for WordPress on this site now?', 'mainwp-simple-performance-for-wordpress' ),
				'saving'         => __( 'Saving…', 'mainwp-simple-performance-for-wordpress' ),
				'saved'          => __( 'Saved.', 'mainwp-simple-performance-for-wordpress' ),
				'saveError'      => __( 'Something went wrong.', 'mainwp-simple-performance-for-wordpress' ),
				'checking'       => __( 'Checking…', 'mainwp-simple-performance-for-wordpress' ),
				'installing'     => __( 'Installing…', 'mainwp-simple-performance-for-wordpress' ),
				'installed'      => __( 'Installed.', 'mainwp-simple-performance-for-wordpress' ),
				'uploading'      => __( 'Uploading…', 'mainwp-simple-performance-for-wordpress' ),
				'uploadError'    => __( 'Upload failed.', 'mainwp-simple-performance-for-wordpress' ),
				'invalidType'    => __( 'Only ZIP files are accepted.', 'mainwp-simple-performance-for-wordpress' ),
				'invalidSize'    => __( 'File exceeds the maximum size of 10 MB.', 'mainwp-simple-performance-for-wordpress' ),
				'bulkConfirm'    => __( 'Install (or reinstall/upgrade) Simple Performance for WordPress on the selected sites now?', 'mainwp-simple-performance-for-wordpress' ),
				'bulkInstalling' => __( 'Installing %1$d of %2$d…', 'mainwp-simple-performance-for-wordpress' ),
				'bulkDone'       => __( 'Bulk install complete: %1$d succeeded, %2$d failed.', 'mainwp-simple-performance-for-wordpress' ),
				'bulkNone'       => __( 'No sites selected.', 'mainwp-simple-performance-for-wordpress' ),
				'bulkSelected'   => __( '%d selected', 'mainwp-simple-performance-for-wordpress' ),
				'statusLabels'   => array(
					'ok'          => __( 'Active', 'mainwp-simple-performance-for-wordpress' ),
					'missing'     => __( 'File missing', 'mainwp-simple-performance-for-wordpress' ),
					'altered'     => __( 'File modified', 'mainwp-simple-performance-for-wordpress' ),
					'unsupported' => __( 'Not available on this server', 'mainwp-simple-performance-for-wordpress' ),
					'advisory'    => __( 'Needs server config', 'mainwp-simple-performance-for-wordpress' ),
				),
			)
		);
	}
}
