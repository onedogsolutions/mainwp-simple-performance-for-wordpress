<?php
/**
 * File-integrity data carried by MainWP's own site sync.
 *
 * Every sync asks the child for its file monitor ('mainwp_sync_others_data'
 * sets a flag in the request's othersData). SPFW 2.21.1+ answers from MainWP
 * Child's 'mainwp_site_sync_others_data' filter: it runs the same scan its
 * twice-daily cron runs (reusing one stored in the last five minutes), and
 * returns the review state plus one fingerprint per plugin, all from a single
 * pass over the disk. 'mainwp_site_synced' stores that answer per site in
 * MainWP's site options, so Fleet File Integrity can compare without
 * re-fingerprinting sites that synced recently.
 *
 * Older children ignore the flag, and nothing is stored for them.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Sync
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Sync {

	/**
	 * Request flag and response key; must match SPFW_MainWP_Child::SYNC_KEY.
	 */
	const SYNC_KEY = 'spfwFileMonitor';

	/**
	 * MainWP site option holding the last sync's answer (JSON).
	 */
	const SITE_OPTION = 'mwpspfw_file_monitor';

	/**
	 * Oldest stored fingerprints Fleet File Integrity uses instead of asking
	 * the site again (seconds).
	 */
	const FINGERPRINT_MAX_AGE = HOUR_IN_SECONDS;

	/**
	 * Hook MainWP's sync. Registered outside the per-user capability check:
	 * scheduled syncs run from cron with no user, and the hooks only add a
	 * flag to a request MainWP signs and store what the child sent back.
	 */
	public static function init() {
		add_filter( 'mainwp_sync_others_data', array( __CLASS__, 'request' ), 10, 2 );
		add_action( 'mainwp_site_synced', array( __CLASS__, 'store' ), 10, 2 );
	}

	/**
	 * Ask the child for its file monitor in this sync.
	 *
	 * @param array  $others_data Data MainWP sends as othersData.
	 * @param object $website     Site being synced.
	 * @return array
	 */
	public static function request( $others_data, $website = null ) {
		unset( $website );

		$others_data                   = is_array( $others_data ) ? $others_data : array();
		$others_data[ self::SYNC_KEY ] = 1;

		return $others_data;
	}

	/**
	 * Store the child's answer after a successful sync.
	 *
	 * A sync that reused a recent scan carries no fingerprints; the previous
	 * ones are kept with their own time, so their age stays honest.
	 *
	 * @param object $website     Site that synced.
	 * @param array  $information The child's sync response.
	 */
	public static function store( $website, $information ) {
		if ( ! is_object( $website ) || empty( $website->id ) || ! is_array( $information ) || empty( $information[ self::SYNC_KEY ] ) || ! is_array( $information[ self::SYNC_KEY ] ) ) {
			return;
		}

		$site_id = (int) $website->id;
		$answer  = $information[ self::SYNC_KEY ];
		$now     = time();
		$record  = self::build_record( $answer, self::get( $site_id ), $now );

		apply_filters( 'mainwp_updatewebsiteoptions', false, $site_id, self::SITE_OPTION, wp_json_encode( $record ) );
	}

	/**
	 * Merge one sync answer over the stored record.
	 *
	 * Pure: no I/O.
	 *
	 * @param array      $answer   The child's SYNC_KEY answer.
	 * @param array|null $previous Stored record, or null.
	 * @param int        $now      Current time.
	 * @return array{synced:int,version:string,enabled:bool,error:string,file_monitor:?array,plugins:?array,uploads_pending:int,fingerprinted:int}
	 */
	public static function build_record( array $answer, $previous, $now ) {
		$previous = is_array( $previous ) ? $previous : array();
		$enabled  = ! empty( $answer['enabled'] );
		$scanned  = $enabled && ! empty( $answer['scanned'] ) && isset( $answer['plugins'] ) && is_array( $answer['plugins'] );

		$record = array(
			'synced'          => (int) $now,
			'version'         => isset( $answer['version'] ) ? (string) $answer['version'] : '',
			'enabled'         => $enabled,
			'error'           => isset( $answer['error'] ) ? (string) $answer['error'] : '',
			'file_monitor'    => isset( $answer['file_monitor'] ) && is_array( $answer['file_monitor'] ) ? $answer['file_monitor'] : null,
			'plugins'         => null,
			'uploads_pending' => 0,
			'fingerprinted'   => 0,
		);

		if ( $scanned ) {
			$record['plugins']         = $answer['plugins'];
			$record['uploads_pending'] = isset( $answer['uploads_pending'] ) ? (int) $answer['uploads_pending'] : 0;
			$record['fingerprinted']   = (int) $now;
		} elseif ( $enabled && '' === $record['error'] && isset( $previous['plugins'] ) && is_array( $previous['plugins'] ) ) {
			$record['plugins']         = $previous['plugins'];
			$record['uploads_pending'] = isset( $previous['uploads_pending'] ) ? (int) $previous['uploads_pending'] : 0;
			$record['fingerprinted']   = isset( $previous['fingerprinted'] ) ? (int) $previous['fingerprinted'] : 0;
		}

		return $record;
	}

	/**
	 * The stored record for one site, or null.
	 *
	 * @param int $site_id MainWP internal website ID.
	 * @return array|null
	 */
	public static function get( $site_id ) {
		$raw = apply_filters( 'mainwp_getwebsiteoptions', false, (int) $site_id, self::SITE_OPTION );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$record = json_decode( $raw, true );

		return is_array( $record ) && isset( $record['synced'] ) ? $record : null;
	}

	/**
	 * Stored fingerprints for one site when they are recent enough to compare
	 * with, else null.
	 *
	 * Fresh means fingerprinted by SPFW >= INTEGRITY_MIN_VERSION within
	 * $max_age seconds. The fallback is to ask the site directly.
	 *
	 * @param array|null $record  Stored record.
	 * @param int        $now     Current time.
	 * @param int        $max_age Seconds.
	 * @return array{plugins:array,uploads_pending:int,fingerprinted:int}|null
	 */
	public static function fresh_fingerprints( $record, $now, $max_age = self::FINGERPRINT_MAX_AGE ) {
		if ( ! is_array( $record ) || empty( $record['plugins'] ) || ! is_array( $record['plugins'] ) || empty( $record['fingerprinted'] ) ) {
			return null;
		}

		if ( ! MWPSPFW_Connector::supports_integrity( isset( $record['version'] ) ? $record['version'] : '' ) ) {
			return null;
		}

		$age = (int) $now - (int) $record['fingerprinted'];

		if ( $age < 0 || $age > (int) $max_age ) {
			return null;
		}

		return array(
			'plugins'         => $record['plugins'],
			'uploads_pending' => isset( $record['uploads_pending'] ) ? (int) $record['uploads_pending'] : 0,
			'fingerprinted'   => (int) $record['fingerprinted'],
		);
	}

	/**
	 * Stop using a site's stored fingerprints. Called after anything on the
	 * dashboard moves that site's baseline, since the stored `pending` flags
	 * then no longer hold; the next comparison asks the site.
	 *
	 * @param int $site_id MainWP internal website ID.
	 */
	public static function forget_fingerprints( $site_id ) {
		$record = self::get( $site_id );

		if ( ! $record || empty( $record['fingerprinted'] ) ) {
			return;
		}

		$record['plugins']         = null;
		$record['uploads_pending'] = 0;
		$record['fingerprinted']   = 0;

		apply_filters( 'mainwp_updatewebsiteoptions', false, (int) $site_id, self::SITE_OPTION, wp_json_encode( $record ) );
	}
}
