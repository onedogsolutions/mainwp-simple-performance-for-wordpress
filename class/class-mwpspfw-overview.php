<?php
/**
 * Extensions-page landing screen.
 *
 * A thin index, not a second configurator: lists connected child sites with a
 * link to each one's own "Performance" tab (the actual configuration screen),
 * an optional per-row status check, and a one-click "Install SPFW" using
 * MainWP's standard wordpress.org plugin-install mechanism.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Overview
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Overview {

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
	 * Render the overview screen: the site table with per-site actions.
	 *
	 * Markup uses MainWP's own Fomantic UI classes (ui segment/table/form)
	 * rather than wp-admin's, so the screen inherits MainWP's light/dark theme
	 * and fills its content column instead of rendering as a mismatched
	 * wp-admin block inside it.
	 */
	public function render_page() {
		MWPSPFW_Admin::enqueue_assets();

		$sites = $this->get_sites();
		?>
		<div class="ui segment">
			<h2 class="ui header"><?php esc_html_e( 'Simple Performance for WordPress', 'mainwp-simple-performance-for-wordpress' ); ?></h2>
			<div class="ui info message">
				<?php esc_html_e( 'Performance settings are configured per site. Use "Configure" to open a site\'s settings tab.', 'mainwp-simple-performance-for-wordpress' ); ?>
			</div>

			<?php if ( empty( $sites ) ) : ?>
				<div class="ui message"><?php esc_html_e( 'No connected child sites were found.', 'mainwp-simple-performance-for-wordpress' ); ?></div>
			<?php else : ?>
				<div class="mwpspfw-bulk-bar" id="mwpspfw-bulk-bar">
					<span id="mwpspfw-bulk-count"></span>
					<button type="button" class="ui green button" id="mwpspfw-bulk-install-btn" disabled>
						<?php esc_html_e( 'Install SPFW on Selected', 'mainwp-simple-performance-for-wordpress' ); ?>
					</button>
					<span id="mwpspfw-bulk-status" class="mwpspfw-save-status"></span>
				</div>
				<table class="ui unstackable table mwpspfw-overview-table">
					<thead>
						<tr>
							<th class="collapsing">
								<input type="checkbox" id="mwpspfw-select-all" class="mwpspfw-checkbox" aria-label="<?php esc_attr_e( 'Select all sites', 'mainwp-simple-performance-for-wordpress' ); ?>" />
							</th>
							<th><?php esc_html_e( 'Site', 'mainwp-simple-performance-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Status', 'mainwp-simple-performance-for-wordpress' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'mainwp-simple-performance-for-wordpress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sites as $site ) : ?>
							<?php
							if ( ! is_array( $site ) || empty( $site['id'] ) ) {
								continue;
							}
							$site_id       = (int) $site['id'];
							$configure_url = add_query_arg(
								array(
									'page' => 'ManageSitesSPFWConfig',
									'id'   => $site_id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr data-site-id="<?php echo esc_attr( $site_id ); ?>">
								<td class="collapsing">
									<input type="checkbox" class="mwpspfw-site-checkbox mwpspfw-checkbox" value="<?php echo esc_attr( $site_id ); ?>" aria-label="<?php esc_attr_e( 'Select site', 'mainwp-simple-performance-for-wordpress' ); ?>" />
								</td>
								<td>
									<strong><?php echo esc_html( isset( $site['name'] ) ? $site['name'] : '' ); ?></strong><br />
									<span class="mwpspfw-site-url"><?php echo esc_html( isset( $site['url'] ) ? $site['url'] : '' ); ?></span>
								</td>
								<td class="mwpspfw-status-cell">&#8212;</td>
								<td>
									<a class="ui mini button" href="<?php echo esc_url( $configure_url ); ?>">
										<?php esc_html_e( 'Configure', 'mainwp-simple-performance-for-wordpress' ); ?>
									</a>
									<button type="button" class="ui mini button mwpspfw-check-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
										<?php esc_html_e( 'Check', 'mainwp-simple-performance-for-wordpress' ); ?>
									</button>
									<button type="button" class="ui mini green button mwpspfw-install-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
										<?php esc_html_e( 'Install SPFW', 'mainwp-simple-performance-for-wordpress' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetch every connected child site via MainWP's 'mainwp_getsites' filter.
	 *
	 * @return array<int,array<string,mixed>> Site rows, empty on failure.
	 */
	private function get_sites() {
		global $mwpspfw_activator;

		$sites = apply_filters(
			'mainwp_getsites',
			$mwpspfw_activator->get_child_file(),
			$mwpspfw_activator->get_child_key(),
			null,
			false
		);

		return is_array( $sites ) ? $sites : array();
	}

	/**
	 * AJAX handler backing the per-row "Check" button: fetches the child's
	 * current settings and returns a short status summary for that row.
	 */
	public function ajax_check() {
		check_ajax_referer( 'mwpspfw_check_site', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view this site.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$result = MWPSPFW_Connector::get_settings( $site_id );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		$settings  = isset( $result['data']['settings'] ) && is_array( $result['data']['settings'] ) ? $result['data']['settings'] : array();
		$core      = isset( $settings['core'] ) && is_array( $settings['core'] ) ? $settings['core'] : array();
		$hardening = isset( $settings['hardening'] ) && is_array( $settings['hardening'] ) ? $settings['hardening'] : array();

		// Count active optimizations for a quick summary.
		$active_core = 0;
		foreach ( $core as $value ) {
			if ( true === $value || '1' === $value ) {
				$active_core++;
			}
		}

		wp_send_json_success(
			array(
				'version'         => isset( $result['data']['version'] ) ? $result['data']['version'] : '',
				'active_toggles'  => $active_core,
				'csp_enabled'     => ! empty( $hardening['csp_enabled'] ),
				'fonts_localized' => ! empty( $settings['fonts']['localize_google'] ),
			)
		);
	}

	/**
	 * AJAX handler backing the "Install SPFW" button: installs/upgrades SPFW
	 * on the child site from wordpress.org via MainWP's standard plugin-install
	 * mechanism.
	 */
	public function ajax_install() {
		check_ajax_referer( 'mwpspfw_install_spfw', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this site.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to install plugins.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$result = MWPSPFW_Connector::install_package( $site_id );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Simple Performance for WordPress was installed and activated.', 'mainwp-simple-performance-for-wordpress' ),
			)
		);
	}
}
