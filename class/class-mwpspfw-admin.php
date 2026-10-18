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
		add_action( 'wp_ajax_mwpspfw_scan_files', array( MWPSPFW_Individual::get_instance(), 'ajax_scan_files' ) );
		add_action( 'wp_ajax_mwpspfw_verify_files', array( MWPSPFW_Individual::get_instance(), 'ajax_verify_files' ) );
		add_action( 'wp_ajax_mwpspfw_integrity_collect', array( MWPSPFW_Integrity::get_instance(), 'ajax_collect' ) );
		add_action( 'wp_ajax_mwpspfw_integrity_analyze', array( MWPSPFW_Integrity::get_instance(), 'ajax_analyze' ) );
		add_action( 'wp_ajax_mwpspfw_integrity_diff', array( MWPSPFW_Integrity::get_instance(), 'ajax_diff' ) );
		add_action( 'wp_ajax_mwpspfw_integrity_consensus', array( MWPSPFW_Integrity::get_instance(), 'ajax_consensus' ) );
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
				'fileReviewNonce' => wp_create_nonce( 'mwpspfw_file_review' ),
				'integrityNonce' => wp_create_nonce( 'mwpspfw_integrity' ),
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
				'fileReview'     => array(
					// Count-bearing strings are {one, other} pairs; plural() in the JS picks one.
					'lastScan'           => array(
						/* translators: 1: last scan time, 2: number of files in the verified baseline. */
						'one'   => __( 'Last scan: %1$s · %2$d file in the verified baseline', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: 1: last scan time, 2: number of files in the verified baseline. */
						'other' => __( 'Last scan: %1$s · %2$d files in the verified baseline', 'mainwp-simple-performance-for-wordpress' ),
					),
					/* translators: 1: time, 2: how it was verified. */
					'lastVerified'       => __( 'Last verified %1$s (%2$s)', 'mainwp-simple-performance-for-wordpress' ),
					/* translators: %s: user display name. */
					'byUser'             => __( ' by %s', 'mainwp-simple-performance-for-wordpress' ),
					'never'              => __( 'never', 'mainwp-simple-performance-for-wordpress' ),
					'nothingPending'     => __( 'Nothing is waiting for review.', 'mainwp-simple-performance-for-wordpress' ),
					'uploadsScope'       => __( 'Uploads', 'mainwp-simple-performance-for-wordpress' ),
					'allScope'           => __( 'everything listed', 'mainwp-simple-performance-for-wordpress' ),
					/* translators: 1: previous version, 2: new version. */
					'unverifiedUpdate'   => __( 'Update %1$s → %2$s could not be verified', 'mainwp-simple-performance-for-wordpress' ),
					'markVerified'       => __( 'Mark as verified', 'mainwp-simple-performance-for-wordpress' ),
					'markAllVerified'    => __( 'Mark all as verified', 'mainwp-simple-performance-for-wordpress' ),
					/* translators: %s: plugin slug, "Uploads", or "everything listed". */
					'confirmVerify'      => __( 'Accept the current files for %s into this site\'s verified baseline? Only do this once you know the changes are legitimate.', 'mainwp-simple-performance-for-wordpress' ),
					'scanning'           => __( 'Scanning…', 'mainwp-simple-performance-for-wordpress' ),
					'verifying'          => __( 'Verifying…', 'mainwp-simple-performance-for-wordpress' ),
					'done'               => __( 'Updated.', 'mainwp-simple-performance-for-wordpress' ),
					'changedSinceReview' => __( 'The files changed since this list was loaded. Scan again and review the new list.', 'mainwp-simple-performance-for-wordpress' ),
					'pendingCount'       => array(
						/* translators: %d: number of file changes. */
						'one'   => __( '%d file change to review', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: %d: number of file changes. */
						'other' => __( '%d file changes to review', 'mainwp-simple-performance-for-wordpress' ),
					),
					'types'              => array(
						'added'    => __( 'added', 'mainwp-simple-performance-for-wordpress' ),
						'modified' => __( 'modified', 'mainwp-simple-performance-for-wordpress' ),
						'removed'  => __( 'removed', 'mainwp-simple-performance-for-wordpress' ),
					),
					'sources'            => array(
						'manual'    => __( 'marked as verified on the site', 'mainwp-simple-performance-for-wordpress' ),
						'mainwp'    => __( 'marked as verified from this dashboard', 'mainwp-simple-performance-for-wordpress' ),
						'consensus' => __( 'matched the same version on other managed sites', 'mainwp-simple-performance-for-wordpress' ),
						'package'   => __( 'matched the update package', 'mainwp-simple-performance-for-wordpress' ),
						'wporg'     => __( 'matched wordpress.org checksums', 'mainwp-simple-performance-for-wordpress' ),
						'initial'   => __( 'first scan', 'mainwp-simple-performance-for-wordpress' ),
					),
				),
				'fleet'          => array(
					/* translators: 1: current site number, 2: total sites. */
					'collecting'      => __( 'Fingerprinting site %1$d of %2$d…', 'mainwp-simple-performance-for-wordpress' ),
					'analyzing'       => __( 'Comparing…', 'mainwp-simple-performance-for-wordpress' ),
					'noSites'         => __( 'No sites to compare.', 'mainwp-simple-performance-for-wordpress' ),
					'noReport'        => __( 'No comparison has been run yet.', 'mainwp-simple-performance-for-wordpress' ),
					'reportMeta'      => array(
						/* translators: 1: time, 2: user, 3: number of sites compared. */
						'one'   => __( 'Last compared %1$s by %2$s · %3$d site', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: 1: time, 2: user, 3: number of sites compared. */
						'other' => __( 'Last compared %1$s by %2$s · %3$d sites', 'mainwp-simple-performance-for-wordpress' ),
					),
					'allConsistent'   => __( 'Every plugin version that runs on more than one site has identical files everywhere.', 'mainwp-simple-performance-for-wordpress' ),
					'colPlugin'       => __( 'Plugin', 'mainwp-simple-performance-for-wordpress' ),
					'colSites'        => __( 'Sites', 'mainwp-simple-performance-for-wordpress' ),
					'colVerdict'      => __( 'Result', 'mainwp-simple-performance-for-wordpress' ),
					'colDetail'       => __( 'Details', 'mainwp-simple-performance-for-wordpress' ),
					'verdicts'        => array(
						'outlier'     => __( 'Differs on some sites', 'mainwp-simple-performance-for-wordpress' ),
						'no_majority' => __( 'Copies differ, no majority', 'mainwp-simple-performance-for-wordpress' ),
						'consistent'  => __( 'Identical', 'mainwp-simple-performance-for-wordpress' ),
						'single'      => __( 'Only one site', 'mainwp-simple-performance-for-wordpress' ),
					),
					'noMajorityHelp'  => __( 'Too few sites agree to say which copy is expected. Review each site by hand.', 'mainwp-simple-performance-for-wordpress' ),
					'outlierLabel'    => __( 'Differs:', 'mainwp-simple-performance-for-wordpress' ),
					/* translators: %d: number of sites. */
					'pendingLabel'    => __( '%d with changes waiting for review', 'mainwp-simple-performance-for-wordpress' ),
					'showDiff'        => __( 'Show differences', 'mainwp-simple-performance-for-wordpress' ),
					'loadingDiff'     => __( 'Loading…', 'mainwp-simple-performance-for-wordpress' ),
					/* translators: %s: reference site name. */
					'diffAgainst'     => __( 'Compared with %s:', 'mainwp-simple-performance-for-wordpress' ),
					'diffNone'        => __( 'No file differences found now. The site may have changed since the comparison; run it again.', 'mainwp-simple-performance-for-wordpress' ),
					'verifyButton'    => array(
						/* translators: %d: number of sites. */
						'one'   => __( 'Mark verified on %d matching site', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: %d: number of sites. */
						'other' => __( 'Mark verified on %d matching sites', 'mainwp-simple-performance-for-wordpress' ),
					),
					/* translators: 1: plugin slug, 2: version, 3: number of agreeing sites, 4: number of sites to verify, 5: outlier list or "none". */
					'confirmConsensus' => __( "Verify %1\$s %2\$s on the matching sites still waiting for review?\n\nSites with identical files: %3\$d\nSites to verify: %4\$d\nExcluded (differ): %5\$s\n\nMatching copies are not proof the files are clean: if every site was changed the same way, the majority is the change. Only continue if you trust this version.", 'mainwp-simple-performance-for-wordpress' ),
					'none'            => __( 'none', 'mainwp-simple-performance-for-wordpress' ),
					'verifyingSite'   => __( 'Verifying…', 'mainwp-simple-performance-for-wordpress' ),
					'verifiedSite'    => __( 'verified', 'mainwp-simple-performance-for-wordpress' ),
					'changedSince'    => __( 'changed since the comparison; run it again', 'mainwp-simple-performance-for-wordpress' ),
					'skippedLabel'    => array(
						/* translators: %d: number of sites. */
						'one'   => __( '%d site skipped:', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: %d: number of sites. */
						'other' => __( '%d sites skipped:', 'mainwp-simple-performance-for-wordpress' ),
					),
					'uploadsPending'  => array(
						/* translators: %d: number of PHP file changes. */
						'one'   => __( '%d PHP change in uploads waiting for review', 'mainwp-simple-performance-for-wordpress' ),
						/* translators: %d: number of PHP file changes. */
						'other' => __( '%d PHP changes in uploads waiting for review', 'mainwp-simple-performance-for-wordpress' ),
					),
					'uploadsHeading'  => __( 'Uploads (not comparable across sites)', 'mainwp-simple-performance-for-wordpress' ),
					'openSite'        => __( 'Open site', 'mainwp-simple-performance-for-wordpress' ),
				),
			)
		);
	}
}
