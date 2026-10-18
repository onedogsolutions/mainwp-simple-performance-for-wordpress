<?php
/**
 * Fleet file integrity: compare each plugin version's files across every
 * managed site and flag the site whose copy differs.
 *
 * Each child (SPFW >= 2.21.0) reports one fingerprint per installed plugin,
 * a sha256 over its monitored files' relative paths and hashes, so identical
 * copies agree on every site. Grouping by slug@version and then by
 * fingerprint isolates the outlier, which is usually the infected site. A
 * drill-down fetches the full relative hashes from the outlier and one
 * majority site and lists exactly which files differ.
 *
 * Consensus verification lets an administrator accept the majority's copy
 * on sites where that plugin is still pending review (for example a premium
 * plugin copied in by SFTP). Each child recomputes its own fingerprint and
 * refuses unless it still matches, so the dashboard never vouches for files
 * a site no longer holds. A majority proves the copies match, not that they
 * are clean, which is why a person confirms it.
 *
 * Collection runs in the browser, one site per request (like bulk install),
 * so a large fleet never hits a PHP timeout.
 *
 * A site whose last MainWP sync fingerprinted it within the hour (SPFW
 * 2.21.1+, see MWPSPFW_Sync) is compared from that sync instead of being
 * asked again; every other site is fingerprinted live.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Integrity
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Integrity {

	/**
	 * Per-site fingerprints for the comparison in progress (non-autoloaded).
	 */
	const RUN_OPTION = 'mwpspfw_integrity_run';

	/**
	 * The last finished report (non-autoloaded).
	 */
	const REPORT_OPTION = 'mwpspfw_integrity_report';

	/**
	 * Fewest sites a group needs before a majority means anything.
	 */
	const MIN_SITES = 3;

	/**
	 * Verdict sort order: what needs attention first.
	 */
	const VERDICT_ORDER = array(
		'outlier'     => 0,
		'no_majority' => 1,
		'consistent'  => 2,
		'single'      => 3,
	);

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Group per-site fingerprints into a report.
	 *
	 * Pure: no I/O, so it can be checked against fixtures.
	 *
	 * Verdicts per slug@version:
	 * - consistent:  every site has the same fingerprint.
	 * - outlier:     one fingerprint is held by a strict majority of a group
	 *                of at least MIN_SITES; every other site is an outlier.
	 * - no_majority: copies differ and no fingerprint holds a strict majority
	 *                of at least MIN_SITES sites (e.g. 2 vs 2, or 1 vs 1).
	 * - single:      only one site runs this version.
	 *
	 * @param array<int|string,array> $site_results Site ID => either
	 *        {plugins: slug => {version, fingerprint, count, pending},
	 *        uploads_pending: int, from_sync?: fingerprint time when the
	 *        entry came from the last sync} or {skipped: reason}.
	 * @return array{groups:array,skipped:array<string,string>,uploads_pending:array<string,int>,from_sync:array<string,int>,sites_compared:int}
	 */
	public static function analyze( array $site_results ) {
		$buckets         = array();
		$skipped         = array();
		$uploads_pending = array();
		$from_sync       = array();
		$compared        = 0;

		foreach ( $site_results as $site_id => $result ) {
			$site_id = (string) $site_id;

			if ( ! is_array( $result ) || isset( $result['skipped'] ) || ! isset( $result['plugins'] ) || ! is_array( $result['plugins'] ) ) {
				$skipped[ $site_id ] = is_array( $result ) && isset( $result['skipped'] ) ? (string) $result['skipped'] : 'no data';
				continue;
			}

			++$compared;

			if ( ! empty( $result['uploads_pending'] ) ) {
				$uploads_pending[ $site_id ] = (int) $result['uploads_pending'];
			}

			if ( ! empty( $result['from_sync'] ) ) {
				$from_sync[ $site_id ] = (int) $result['from_sync'];
			}

			foreach ( $result['plugins'] as $slug => $plugin ) {
				if ( ! is_array( $plugin ) || empty( $plugin['fingerprint'] ) || ! isset( $plugin['version'] ) ) {
					continue;
				}

				$key = $slug . '@' . $plugin['version'];

				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array(
						'slug'         => (string) $slug,
						'version'      => (string) $plugin['version'],
						'fingerprints' => array(),
						'pending'      => array(),
						'counts'       => array(),
					);
				}

				$fp = (string) $plugin['fingerprint'];

				$buckets[ $key ]['fingerprints'][ $fp ][] = $site_id;
				$buckets[ $key ]['counts'][ $fp ]         = isset( $plugin['count'] ) ? (int) $plugin['count'] : 0;

				if ( ! empty( $plugin['pending'] ) ) {
					$buckets[ $key ]['pending'][] = $site_id;
				}
			}
		}

		$groups = array();

		foreach ( $buckets as $bucket ) {
			$groups[] = self::judge( $bucket );
		}

		usort(
			$groups,
			static function ( $a, $b ) {
				$order = self::VERDICT_ORDER[ $a['verdict'] ] - self::VERDICT_ORDER[ $b['verdict'] ];
				if ( 0 !== $order ) {
					return $order;
				}
				// Within a verdict, groups with something pending come first.
				$pending = count( $b['pending'] ) - count( $a['pending'] );
				if ( 0 !== $pending ) {
					return $pending;
				}
				return strcmp( $a['slug'] . '@' . $a['version'], $b['slug'] . '@' . $b['version'] );
			}
		);

		return array(
			'groups'          => $groups,
			'skipped'         => $skipped,
			'uploads_pending' => $uploads_pending,
			'from_sync'       => $from_sync,
			'sites_compared'  => $compared,
		);
	}

	/**
	 * Decide one slug@version group.
	 *
	 * @param array $bucket {slug, version, fingerprints: fp => site IDs, pending: site IDs, counts}.
	 * @return array
	 */
	private static function judge( array $bucket ) {
		$fingerprints = $bucket['fingerprints'];

		uasort(
			$fingerprints,
			static function ( $a, $b ) {
				return count( $b ) - count( $a );
			}
		);

		$total     = 0;
		$top_fp    = '';
		$top_sites = array();

		foreach ( $fingerprints as $fp => $sites ) {
			$total += count( $sites );

			if ( '' === $top_fp ) {
				$top_fp    = (string) $fp;
				$top_sites = $sites;
			}
		}

		$distinct = count( $fingerprints );

		if ( 1 === $total ) {
			$verdict = 'single';
		} elseif ( 1 === $distinct ) {
			$verdict = 'consistent';
		} elseif ( $total >= self::MIN_SITES && count( $top_sites ) * 2 > $total ) {
			$verdict = 'outlier';
		} else {
			$verdict = 'no_majority';
		}

		$has_majority = in_array( $verdict, array( 'consistent', 'outlier' ), true );
		$outliers     = array();

		if ( 'outlier' === $verdict ) {
			foreach ( $fingerprints as $fp => $sites ) {
				if ( (string) $fp !== $top_fp ) {
					$outliers = array_merge( $outliers, $sites );
				}
			}
		}

		// Sites the majority could verify: in the majority, and pending.
		$verifiable = $has_majority && count( $top_sites ) >= self::MIN_SITES
			? array_values( array_intersect( $top_sites, $bucket['pending'] ) )
			: array();

		return array(
			'slug'                 => $bucket['slug'],
			'version'              => $bucket['version'],
			'sites'                => $total,
			'verdict'              => $verdict,
			'majority_fingerprint' => $has_majority ? $top_fp : '',
			'majority_sites'       => $has_majority ? array_values( $top_sites ) : array(),
			'majority_count'       => $has_majority && isset( $bucket['counts'][ $top_fp ] ) ? $bucket['counts'][ $top_fp ] : 0,
			'outliers'             => array_values( $outliers ),
			'variants'             => array_map( 'array_values', $fingerprints ),
			'pending'              => array_values( $bucket['pending'] ),
			'verifiable'           => $verifiable,
		);
	}

	/**
	 * Added/modified/removed relative paths of one copy against another.
	 *
	 * @param array<string,string> $subject   Relative path => sha256 (the outlier).
	 * @param array<string,string> $reference Relative path => sha256 (a majority site).
	 * @return array{added:string[],modified:string[],removed:string[]}
	 */
	public static function diff( array $subject, array $reference ) {
		$out = array(
			'added'    => array_values( array_map( 'strval', array_keys( array_diff_key( $subject, $reference ) ) ) ),
			'modified' => array(),
			'removed'  => array_values( array_map( 'strval', array_keys( array_diff_key( $reference, $subject ) ) ) ),
		);

		foreach ( $subject as $path => $hash ) {
			if ( isset( $reference[ $path ] ) && $reference[ $path ] !== $hash ) {
				$out['modified'][] = (string) $path;
			}
		}

		foreach ( $out as $type => $paths ) {
			sort( $paths );
			$out[ $type ] = $paths;
		}

		return $out;
	}

	/**
	 * The last finished report, or null.
	 *
	 * @return array|null
	 */
	public static function get_report() {
		$report = get_option( self::REPORT_OPTION, null );
		return is_array( $report ) && isset( $report['groups'] ) ? $report : null;
	}

	/**
	 * Find one group in the stored report.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $version Version.
	 * @return array|null
	 */
	private static function find_group( $slug, $version ) {
		$report = self::get_report();

		if ( ! $report ) {
			return null;
		}

		foreach ( $report['groups'] as $group ) {
			if ( $group['slug'] === $slug && $group['version'] === $version ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Shared gate for the fleet integrity AJAX handlers.
	 *
	 * @param bool $vouches Whether the action vouches for files (consensus).
	 */
	private function gate( $vouches = false ) {
		check_ajax_referer( 'mwpspfw_integrity', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit these sites.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		if ( $vouches && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only a dashboard administrator can verify files across sites.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}
	}

	/**
	 * A posted scalar, unslashed and sanitized.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function posted( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in gate().
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * AJAX: fingerprint one site and record it in the run in progress.
	 * `run_id` names the run; a new run_id starts over. A site whose last
	 * sync fingerprinted it within MWPSPFW_Sync::FINGERPRINT_MAX_AGE is not
	 * asked again; any other site is fingerprinted live, as before.
	 */
	public function ajax_collect() {
		$this->gate();

		$site_id = (int) $this->posted( 'site_id' );
		$run_id  = $this->posted( 'run_id' );

		if ( ! $site_id || ! preg_match( '/^[A-Za-z0-9]{6,32}$/', $run_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$run = get_option( self::RUN_OPTION, array() );

		if ( ! is_array( $run ) || ! isset( $run['run_id'] ) || $run['run_id'] !== $run_id ) {
			$run = array(
				'run_id'  => $run_id,
				'started' => time(),
				'sites'   => array(),
			);
		}

		// Fingerprints from a recent sync stand in for asking the site again.
		// Consensus verification still has the site recheck its own disk.
		$stored = MWPSPFW_Sync::fresh_fingerprints( MWPSPFW_Sync::get( $site_id ), time() );

		if ( null !== $stored ) {
			$entry = array(
				'plugins'         => $stored['plugins'],
				'uploads_pending' => $stored['uploads_pending'],
				'from_sync'       => $stored['fingerprinted'],
			);
		} else {
			$result = MWPSPFW_Connector::plugin_fingerprints( $site_id );

			if ( $result['ok'] && isset( $result['data']['data']['plugins'] ) && is_array( $result['data']['data']['plugins'] ) ) {
				$entry = array(
					'plugins'         => $result['data']['data']['plugins'],
					'uploads_pending' => isset( $result['data']['data']['uploads_pending'] ) ? (int) $result['data']['data']['uploads_pending'] : 0,
				);
			} else {
				$entry = array( 'skipped' => $result['ok'] ? __( 'Unexpected response.', 'mainwp-simple-performance-for-wordpress' ) : $result['message'] );
			}
		}

		$run['sites'][ $site_id ] = $entry;
		update_option( self::RUN_OPTION, $run, false );

		wp_send_json_success(
			array(
				'skipped'   => isset( $entry['skipped'] ) ? $entry['skipped'] : '',
				'plugins'   => isset( $entry['plugins'] ) ? count( $entry['plugins'] ) : 0,
				'from_sync' => ! empty( $entry['from_sync'] ),
			)
		);
	}

	/**
	 * AJAX: analyze the run in progress, store it as the report, return it.
	 */
	public function ajax_analyze() {
		$this->gate();

		$run_id = $this->posted( 'run_id' );
		$run    = get_option( self::RUN_OPTION, array() );

		if ( ! is_array( $run ) || ! isset( $run['run_id'] ) || $run['run_id'] !== $run_id ) {
			wp_send_json_error( array( 'message' => __( 'That comparison is no longer in progress. Run it again.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$report         = self::analyze( $run['sites'] );
		$report['time'] = time();
		$report['user'] = wp_get_current_user()->display_name;

		update_option( self::REPORT_OPTION, $report, false );
		delete_option( self::RUN_OPTION );

		wp_send_json_success( array( 'report' => $report ) );
	}

	/**
	 * AJAX: list which files one outlier's copy changes against a majority
	 * site's copy.
	 */
	public function ajax_diff() {
		$this->gate();

		$slug    = $this->posted( 'slug' );
		$version = $this->posted( 'version' );
		$site_id = $this->posted( 'site_id' );
		$group   = self::find_group( $slug, $version );

		if ( ! $group || empty( $group['majority_sites'] ) || ! in_array( $site_id, $group['outliers'], true ) ) {
			wp_send_json_error( array( 'message' => __( 'That site is not an outlier in the last comparison. Run it again.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$reference = $group['majority_sites'][0];
		$theirs    = MWPSPFW_Connector::plugin_files( (int) $site_id, $slug );
		$ours      = MWPSPFW_Connector::plugin_files( (int) $reference, $slug );

		foreach ( array( $theirs, $ours ) as $result ) {
			if ( ! $result['ok'] ) {
				wp_send_json_error( array( 'message' => $result['message'] ) );
			}
		}

		$subject_files   = isset( $theirs['data']['data']['files'] ) && is_array( $theirs['data']['data']['files'] ) ? $theirs['data']['data']['files'] : array();
		$reference_files = isset( $ours['data']['data']['files'] ) && is_array( $ours['data']['data']['files'] ) ? $ours['data']['data']['files'] : array();

		wp_send_json_success(
			array(
				'reference' => $reference,
				'diff'      => self::diff( $subject_files, $reference_files ),
			)
		);
	}

	/**
	 * AJAX: consensus-verify one majority site's copy of a plugin. The
	 * fingerprint and quorum come from the stored report, never from the
	 * browser, and the child rechecks its own disk before accepting.
	 */
	public function ajax_consensus() {
		$this->gate( true );

		$slug    = $this->posted( 'slug' );
		$version = $this->posted( 'version' );
		$site_id = $this->posted( 'site_id' );
		$group   = self::find_group( $slug, $version );

		if ( ! $group || ! in_array( $site_id, $group['verifiable'], true ) ) {
			wp_send_json_error( array( 'message' => __( 'That site cannot be verified from the last comparison. Run it again.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$result = MWPSPFW_Connector::verify_consensus(
			(int) $site_id,
			$slug,
			$version,
			$group['majority_fingerprint'],
			count( $group['majority_sites'] ) - 1
		);

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message' => $result['message'],
					'code'    => isset( $result['code'] ) ? $result['code'] : '',
				)
			);
		}

		self::mark_verified_in_report( $slug, $version, $site_id );
		MWPSPFW_Sync::forget_fingerprints( (int) $site_id );

		wp_send_json_success( array( 'site_id' => $site_id ) );
	}

	/**
	 * Drop a verified site from a group's pending lists in the stored
	 * report, so the page reflects it after a reload.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $version Version.
	 * @param string $site_id Site ID.
	 */
	private static function mark_verified_in_report( $slug, $version, $site_id ) {
		$report = self::get_report();

		if ( ! $report ) {
			return;
		}

		foreach ( $report['groups'] as &$group ) {
			if ( $group['slug'] === $slug && $group['version'] === $version ) {
				$group['pending']    = array_values( array_diff( $group['pending'], array( $site_id ) ) );
				$group['verifiable'] = array_values( array_diff( $group['verifiable'], array( $site_id ) ) );
			}
		}
		unset( $group );

		update_option( self::REPORT_OPTION, $report, false );
	}

	/**
	 * Render the Fleet File Integrity segment on the overview. The report is
	 * drawn by initFleetIntegrity() in mwpspfw-admin.js from the JSON below,
	 * so the stored report and a fresh run share one renderer.
	 *
	 * @param array $sites Site rows from mainwp_getsites.
	 */
	public function render_segment( array $sites ) {
		$directory = array();

		foreach ( $sites as $site ) {
			if ( ! is_array( $site ) || empty( $site['id'] ) ) {
				continue;
			}
			$directory[ (string) (int) $site['id'] ] = array(
				'name' => isset( $site['name'] ) ? (string) $site['name'] : '',
				'url'  => isset( $site['url'] ) ? (string) $site['url'] : '',
				'link' => add_query_arg(
					array(
						'page' => 'ManageSitesSPFWConfig',
						'id'   => (int) $site['id'],
					),
					admin_url( 'admin.php' )
				),
			);
		}

		$report = self::get_report();
		?>
		<div class="ui segment mwpspfw-fleet" id="mwpspfw-fleet"
			data-sites="<?php echo esc_attr( wp_json_encode( (object) $directory ) ); ?>"
			data-report="<?php echo esc_attr( wp_json_encode( $report ? $report : null ) ); ?>"
			data-can-verify="<?php echo current_user_can( 'manage_options' ) ? '1' : '0'; ?>">
			<h3 class="ui header"><?php esc_html_e( 'Fleet File Integrity', 'mainwp-simple-performance-for-wordpress' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: minimum SPFW version. */
					esc_html__( 'Compares each plugin version\'s files across your sites. A site whose copy differs from the others stands out, which is how an infected site is usually found. Needs Simple Performance for WordPress %s or later on each site; older sites are skipped.', 'mainwp-simple-performance-for-wordpress' ),
					esc_html( MWPSPFW_Connector::INTEGRITY_MIN_VERSION )
				);
				?>
			</p>
			<p>
				<button type="button" class="ui primary button" id="mwpspfw-fleet-run-btn"><?php esc_html_e( 'Compare plugins across sites', 'mainwp-simple-performance-for-wordpress' ); ?></button>
				<span class="mwpspfw-save-status" id="mwpspfw-fleet-status" role="status"></span>
			</p>
			<p class="mwpspfw-field-description"><?php esc_html_e( 'Compares the sites selected in the table below, or every site when none are selected.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
			<div class="mwpspfw-fleet-report" id="mwpspfw-fleet-report"></div>
		</div>
		<?php
	}
}
