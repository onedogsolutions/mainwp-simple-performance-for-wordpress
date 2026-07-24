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
	 * @var string
	 */
	const MIN_SPFW_VERSION = '1.8.0';

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
	 * @param string               $action  'get_settings' or 'update_settings'.
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
