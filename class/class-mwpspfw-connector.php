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
	 * Uses the configured package URL if set, otherwise falls back to the
	 * wordpress.org slug-based install.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array{ok:bool,error_type:?string,message:string,data:?array}
	 */
	public static function install_package( $site_id ) {
		global $mwpspfw_activator;

		// Check for a configured package URL; fall back to wordpress.org slug.
		$package     = get_option( 'mwpspfw_package', array() );
		$package_url = is_array( $package ) && ! empty( $package['url'] ) ? $package['url'] : '';

		$install_args = array(
			'type'           => 'plugin',
			'activatePlugin' => 'yes',
			'overwrite'      => true,
		);

		if ( ! empty( $package_url ) ) {
			// Use the custom package URL (uploaded ZIP or self-hosted).
			$install_args['url'] = $package_url;
		} else {
			// Fall back to wordpress.org slug-based install.
			$install_args['slug'] = 'simple-performance-for-wordpress';
		}

		$response = apply_filters(
			'mainwp_fetchurlauthed',
			$mwpspfw_activator->get_child_file(),
			$mwpspfw_activator->get_child_key(),
			(int) $site_id,
			'installplugintheme',
			$install_args
		);

		if ( self::is_transport_failure( $response ) ) {
			return array(
				'ok'         => false,
				'error_type' => 'transport',
				'message'    => self::transport_error_message( $response ),
				'data'       => null,
			);
		}

		if ( empty( $response['installation'] ) || 'SUCCESS' !== $response['installation'] ) {
			return array(
				'ok'         => false,
				'error_type' => 'install_error',
				'message'    => __( 'The child site reported an installation error.', 'mainwp-simple-performance-for-wordpress' ),
				'data'       => $response,
			);
		}

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
}
