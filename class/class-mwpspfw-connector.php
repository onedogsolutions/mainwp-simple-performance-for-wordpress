<?php
/**
 * Dashboard -> child connector.
 *
 * Wraps the 'mainwp_fetchurlauthed' filter (MainWP's signed dashboard-to-child
 * channel) to talk to the SPFW bridge that ships inside the Simple Performance
 * for WordPress plugin itself (class-spfw-mainwp-child.php, hooked to MainWP
 * Child's 'mainwp_child_extra_execution' filter). This class owns every
 * dashboard->child call this extension makes.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Connector
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Connector {

	/**
	 * Minimum SPFW plugin version known to carry the MainWP bridge.
	 *
	 * 2.17.0 is the release this extension's schema is verified against. The
	 * newest fields (strict-dynamic, CSP collection toggles) need the child
	 * >= 2.13.0; older children silently drop unknown settings keys, so a
	 * higher floor keeps silent partial saves from looking successful.
	 *
	 * @var string
	 */
	const MIN_SPFW_VERSION = '2.17.0';

	/**
	 * Minimum SPFW version whose bridge carries the file-monitor actions
	 * (scan, Mark as verified, plugin fingerprints, consensus verification).
	 * Older children answer those actions with 'unknown action'.
	 *
	 * @var string
	 */
	const INTEGRITY_MIN_VERSION = '2.21.0';

	/**
	 * Minimum SPFW version that scans when MainWP syncs it and returns the
	 * review state and plugin fingerprints in the sync response (see
	 * MWPSPFW_Sync). Older children ignore the sync flag; every on-demand
	 * action keeps working from INTEGRITY_MIN_VERSION.
	 *
	 * @var string
	 */
	const SYNC_SCAN_MIN_VERSION = '2.21.1';

	/**
	 * Whether a child's reported SPFW version scans at sync time.
	 *
	 * @param string $version Version from the bridge envelope.
	 * @return bool
	 */
	public static function supports_sync_scan( $version ) {
		return is_string( $version ) && '' !== $version && version_compare( $version, self::SYNC_SCAN_MIN_VERSION, '>=' );
	}

	/**
	 * Whether a child's reported SPFW version carries the file-monitor actions.
	 *
	 * @param string $version Version from the bridge envelope.
	 * @return bool
	 */
	public static function supports_integrity( $version ) {
		return is_string( $version ) && '' !== $version && version_compare( $version, self::INTEGRITY_MIN_VERSION, '>=' );
	}

	/**
	 * Run a file-integrity scan on the child.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} On
	 *         success `data['data']` holds `scan_result` and `file_monitor`.
	 */
	public static function scan_files( $site_id ) {
		return self::file_call( $site_id, 'scan_files' );
	}

	/**
	 * Mark as verified on the child for one scope.
	 *
	 * @param int    $site_id   MainWP internal website ID.
	 * @param string $signature The scope's signature from the reviewed state.
	 * @param string $scope     'all', 'uploads', or a plugin slug.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	public static function verify_files( $site_id, $signature, $scope ) {
		return self::file_call(
			$site_id,
			'verify_files',
			array(
				'signature' => (string) $signature,
				'scope'     => (string) $scope,
			)
		);
	}

	/**
	 * Fingerprint every plugin installed on the child.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} On
	 *         success `data['data']['plugins']` is slug => {version,
	 *         fingerprint, count, pending}.
	 */
	public static function plugin_fingerprints( $site_id ) {
		return self::file_call( $site_id, 'plugin_fingerprints' );
	}

	/**
	 * One plugin's file hashes on the child, relative to the plugin directory.
	 *
	 * @param int    $site_id MainWP internal website ID.
	 * @param string $slug    Plugin slug.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	public static function plugin_files( $site_id, $slug ) {
		return self::file_call( $site_id, 'plugin_files', array( 'slug' => (string) $slug ) );
	}

	/**
	 * Ask the child to verify its copy of a plugin against the majority
	 * fingerprint. The child recomputes its own fingerprint and refuses on
	 * any mismatch.
	 *
	 * @param int    $site_id     MainWP internal website ID.
	 * @param string $slug        Plugin slug.
	 * @param string $version     Version the majority runs.
	 * @param string $fingerprint Majority fingerprint.
	 * @param int    $agreeing    Number of other sites with that fingerprint.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	public static function verify_consensus( $site_id, $slug, $version, $fingerprint, $agreeing ) {
		return self::file_call(
			$site_id,
			'verify_consensus',
			array(
				'slug'        => (string) $slug,
				'version'     => (string) $version,
				'fingerprint' => (string) $fingerprint,
				'agreeing'    => (int) $agreeing,
			)
		);
	}

	/**
	 * A logged file-monitor call. An older child that does not know the
	 * action is reported as 'unsupported' rather than a generic refusal.
	 *
	 * @param int                 $site_id MainWP internal website ID.
	 * @param string              $action  Bridge action.
	 * @param array<string,mixed> $extra   Extra POST fields.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	private static function file_call( $site_id, $action, array $extra = array() ) {
		$result = self::call( $site_id, $action, $extra );

		if ( ! $result['ok'] && 'bridge_error' === $result['error_type'] && 'unknown action' === $result['message'] ) {
			$result['error_type'] = 'unsupported';
			$result['message']    = sprintf(
				/* translators: %s: minimum SPFW version for file integrity features. */
				__( 'File integrity review needs Simple Performance for WordPress %s or later on this child site.', 'mainwp-simple-performance-for-wordpress' ),
				self::INTEGRITY_MIN_VERSION
			);
		}

		self::log(
			'file_' . $action,
			array(
				'site_id'    => (int) $site_id,
				'args'       => $extra,
				'ok'         => $result['ok'],
				'error_type' => $result['error_type'],
				'code'       => isset( $result['code'] ) ? $result['code'] : '',
				'message'    => $result['message'],
			)
		);

		return $result;
	}

	/**
	 * Fetch the current SPFW settings from a child site.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} Normalized result.
	 */
	public static function get_settings( $site_id ) {
		return self::call( $site_id, 'get_settings' );
	}

	/**
	 * Push updated SPFW settings to a child site.
	 *
	 * @param int                  $site_id  MainWP internal website ID.
	 * @param array<string,mixed>  $settings Settings in the nested group structure
	 *                                       SPFW_Settings::update() expects.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array} Normalized
	 *         result; on success `data['settings']` holds what the child
	 *         actually persisted after its own validation/clamping.
	 */
	public static function update_settings( $site_id, array $settings ) {
		return self::call( $site_id, 'update_settings', array( 'settings' => wp_json_encode( $settings ) ) );
	}

	/**
	 * Install (or upgrade-in-place) Simple Performance for WordPress on a
	 * child site using MainWP's standard 'installplugintheme' child callable.
	 * Installs from the configured package URL (set on the Extensions page).
	 *
	 * Note: MainWP Child's installplugintheme callable requires a 'url'
	 * parameter containing a JSON-encoded value (string or array of URLs).
	 * The child handler calls json_decode() on the received value, so we
	 * must wp_json_encode() the URL before sending. This matches the native
	 * MainWP Dashboard behavior (see MainWP_Install_Bulk::perform_install()
	 * and perform_upload() in page-mainwp-install-bulk.php).
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	public static function install_package( $site_id ) {
		global $mwpspfw_activator;

		// Log extension key status for debugging.
		$child_key = $mwpspfw_activator->get_child_key();
		self::log( 'install_package_start', array(
			'site_id'    => $site_id,
			'child_key'  => $child_key ? '(present)' : '(missing/false)',
			'child_file' => $mwpspfw_activator->get_child_file(),
		) );

		$package     = get_option( 'mwpspfw_package', array() );
		$package_url = '';

		// Priority: uploaded file (signed URL) > external URL.
		if ( is_array( $package ) && ! empty( $package['file'] ) && class_exists( '\MainWP\Dashboard\MainWP_System_Utility' ) ) {
			// Generate a time-limited signed download URL via MainWP's native mechanism.
			$package_url = \MainWP\Dashboard\MainWP_System_Utility::get_download_url( 'spfw', $package['file'] );
		} elseif ( is_array( $package ) && ! empty( $package['url'] ) ) {
			$package_url = $package['url'];
		}

		if ( '' === $package_url ) {
			return array(
				'ok'         => false,
				'error_type' => 'no_package',
				'message'    => __( 'No SPFW package URL is configured. Set one on the Extensions page first.', 'mainwp-simple-performance-for-wordpress' ),
				'data'       => null,
			);
		}

		$install_args = array(
			'type'           => 'plugin',
			'url'            => wp_json_encode( $package_url ), // Child json_decode()s this value.
			'activatePlugin' => 'yes',
			'overwrite'      => true,
		);

		self::log( 'install_package', array(
			'site_id'      => $site_id,
			'package_url'  => $package_url,
			'install_args' => $install_args,
		) );

		$response = apply_filters(
			'mainwp_fetchurlauthed',
			$mwpspfw_activator->get_child_file(),
			$mwpspfw_activator->get_child_key(),
			(int) $site_id,
			'installplugintheme',
			$install_args
		);

		self::log( 'install_response', array(
			'site_id'  => $site_id,
			'response' => $response,
		) );

		if ( self::is_transport_failure( $response ) ) {
			$result = array(
				'ok'         => false,
				'error_type' => 'transport',
				'message'    => self::transport_error_message( $response ),
				'data'       => null,
			);
			self::log( 'install_failed', array( 'site_id' => $site_id, 'result' => $result ) );
			return $result;
		}

		if ( empty( $response['installation'] ) || 'SUCCESS' !== $response['installation'] ) {
			$result = array(
				'ok'         => false,
				'error_type' => 'install_error',
				'message'    => __( 'The child site reported an installation error.', 'mainwp-simple-performance-for-wordpress' ),
				'data'       => $response,
			);
			self::log( 'install_failed', array( 'site_id' => $site_id, 'result' => $result ) );
			return $result;
		}

		self::log( 'install_success', array( 'site_id' => $site_id ) );

		return array(
			'ok'         => true,
			'error_type' => null,
			'message'    => '',
			'data'       => $response,
		);
	}

	/**
	 * Whether a 'mainwp_fetchurlauthed' response is a transport/site-level
	 * failure (unreachable site, suspended site, or a dashboard user without
	 * edit rights on this site) rather than a real payload.
	 *
	 * @param mixed $response Raw filter result.
	 * @return bool
	 */
	private static function is_transport_failure( $response ) {
		return ! is_array( $response ) || isset( $response['error'] );
	}

	/**
	 * Extract a human-readable message from a transport-failure response.
	 *
	 * @param mixed $response Raw filter result.
	 * @return string
	 */
	private static function transport_error_message( $response ) {
		return ( is_array( $response ) && ! empty( $response['error'] ) )
			? $response['error']
			: __( 'The site did not respond.', 'mainwp-simple-performance-for-wordpress' );
	}

	/**
	 * Make one 'extra_execution' round trip and normalize the response into
	 * one of three typed outcomes: a transport/site-level failure (site
	 * unreachable, suspended, not editable by this dashboard user), a missing
	 * bridge (SPFW absent, deactivated, or older than MIN_SPFW_VERSION), or a
	 * bridge-reported error (e.g. an internal callback failure).
	 *
	 * @param int                  $site_id MainWP internal website ID.
	 * @param string               $action  Bridge action ('get_settings', 'update_settings', or a file-monitor action).
	 * @param array<string,mixed>  $extra   Extra POST fields for the action.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	private static function call( $site_id, $action, array $extra = array() ) {
		global $mwpspfw_activator;

		$post_data                   = $extra;
		$post_data['spfw_action']    = $action;

		$response = apply_filters(
			'mainwp_fetchurlauthed',
			$mwpspfw_activator->get_child_file(),
			$mwpspfw_activator->get_child_key(),
			(int) $site_id,
			'extra_execution',
			$post_data
		);

		if ( self::is_transport_failure( $response ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'transport',
				'message'    => self::transport_error_message( $response ),
				'data'       => null,
			);
		}

		// Bridge missing: MainWP Child answered, but nothing on the child
		// hooked our action, so the envelope key never appears. This is the
		// expected state until the child runs SPFW >= MIN_SPFW_VERSION.
		if ( empty( $response['spfw'] ) || ! is_array( $response['spfw'] ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'bridge_missing',
				'message'    => sprintf(
					/* translators: %s: minimum required SPFW version. */
					__( 'Simple Performance for WordPress is not active on this child site, or is older than version %s.', 'mainwp-simple-performance-for-wordpress' ),
					self::MIN_SPFW_VERSION
				),
				'data'       => null,
			);
		}

		$envelope = $response['spfw'];

		// Bridge-reported error: the action reached SPFW but it refused it.
		if ( empty( $envelope['success'] ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'bridge_error',
				'message'    => ! empty( $envelope['error'] )
					? $envelope['error']
					: __( 'Simple Performance for WordPress refused the request.', 'mainwp-simple-performance-for-wordpress' ),
				'code'       => isset( $envelope['code'] ) && is_string( $envelope['code'] ) ? $envelope['code'] : '',
				'data'       => null,
			);
		}

		return array(
			'ok'         => true,
			'error_type' => null,
			'message'    => '',
			'data'       => $envelope,
		);
	}

	/**
	 * Write a debug log entry to wp-content/uploads/mwpspfw-logs/debug.log.
	 * Logging is always enabled for now; can be gated behind a constant or
	 * option later if needed.
	 *
	 * @param string               $event   Event name (e.g. 'install_package').
	 * @param array<string,mixed>  $context Contextual data to log.
	 */
	public static function log( $event, array $context = array() ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'mwpspfw-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			// Prevent direct access.
			file_put_contents( $log_dir . '/.htaccess', 'Deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$log_file = $log_dir . '/debug.log';

		$entry = sprintf(
			"[%s] %s: %s\n",
			gmdate( 'Y-m-d H:i:s' ),
			$event,
			wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Get the path to the debug log file.
	 *
	 * @return string
	 */
	public static function get_log_path() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'mwpspfw-logs/debug.log';
	}

	/**
	 * Get the contents of the debug log file.
	 *
	 * @param int $lines Number of recent lines to return (0 = all).
	 * @return string
	 */
	public static function get_log_contents( $lines = 0 ) {
		$log_file = self::get_log_path();

		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		$content = file_get_contents( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( $lines > 0 ) {
			$all_lines = explode( "\n", trim( $content ) );
			$all_lines = array_slice( $all_lines, -$lines );
			return implode( "\n", $all_lines );
		}

		return $content;
	}

	/**
	 * Clear the debug log file.
	 *
	 * @return bool
	 */
	public static function clear_log() {
		$log_file = self::get_log_path();

		if ( file_exists( $log_file ) ) {
			return file_put_contents( $log_file, '' ) !== false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return true;
	}
}
