<?php
/**
 * Extensions-page landing screen.
 *
 * A thin index, not a second configurator: lists connected child sites with a
 * link to each one's own "Performance" tab (the actual configuration screen),
 * an optional per-row status check, a one-click "Install SPFW" using MainWP's
 * standard plugin-install mechanism, and the package URL setting that button
 * installs (with drag-and-drop ZIP upload).
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
	 * Render the overview screen: the package-URL setting, then the site table.
	 *
	 * Markup uses MainWP's own Fomantic UI classes (ui segment/table/form)
	 * rather than wp-admin's, so the screen inherits MainWP's light/dark theme
	 * and fills its content column instead of rendering as a mismatched
	 * wp-admin block inside it.
	 */
	public function render_page() {
		MWPSPFW_Admin::enqueue_assets();

		$sites   = $this->get_sites();
		$package = $this->get_package();
		?>
		<div class="ui segment">
			<h2 class="ui header"><?php esc_html_e( 'Simple Performance for WordPress', 'mainwp-simple-performance-for-wordpress' ); ?></h2>
			<div class="ui info message">
				<?php esc_html_e( 'Performance settings are configured per site. Use "Configure" to open a site\'s settings tab.', 'mainwp-simple-performance-for-wordpress' ); ?>
			</div>

			<div class="ui segment">
				<h3 class="ui header"><?php esc_html_e( 'SPFW Plugin Package', 'mainwp-simple-performance-for-wordpress' ); ?></h3>
				<p>
					<?php esc_html_e( 'Used by the "Install SPFW" button below to install or update Simple Performance for WordPress on a child site. By default, installs from wordpress.org. To use a custom build or a specific version, upload a ZIP here or link to one hosted on your own server.', 'mainwp-simple-performance-for-wordpress' ); ?>
				</p>
				<form id="mwpspfw-package-form" class="ui form">
					<input type="hidden" name="action" value="mwpspfw_save_package" />
					<?php wp_nonce_field( 'mwpspfw_save_package', 'nonce' ); ?>

					<!-- Drag-and-drop file upload zone -->
					<div class="field">
						<label><?php esc_html_e( 'Upload Plugin ZIP', 'mainwp-simple-performance-for-wordpress' ); ?></label>
						<div class="mwpspfw-dropzone" id="mwpspfw-dropzone">
							<input
								type="file"
								id="mwpspfw-file-input"
								accept=".zip,application/zip"
								class="mwpspfw-dropzone-input"
								aria-label="<?php esc_attr_e( 'Upload ZIP file', 'mainwp-simple-performance-for-wordpress' ); ?>"
							/>
							<!-- Empty state: prompt -->
							<div class="mwpspfw-dropzone-prompt" id="mwpspfw-dropzone-prompt">
								<span class="mwpspfw-dropzone-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
								</span>
								<p class="mwpspfw-dropzone-title"><?php esc_html_e( 'Drop your ZIP here', 'mainwp-simple-performance-for-wordpress' ); ?></p>
								<p class="mwpspfw-dropzone-hint"><?php esc_html_e( 'ZIP only (max. 10 MB)', 'mainwp-simple-performance-for-wordpress' ); ?></p>
								<button type="button" class="ui button" id="mwpspfw-select-file-btn">
									<?php esc_html_e( 'Select file', 'mainwp-simple-performance-for-wordpress' ); ?>
								</button>
							</div>
							<!-- Filled state: file preview -->
							<div class="mwpspfw-dropzone-preview" id="mwpspfw-dropzone-preview" hidden>
								<span class="mwpspfw-dropzone-filename" id="mwpspfw-dropzone-filename"></span>
								<button type="button" class="mwpspfw-dropzone-remove" id="mwpspfw-dropzone-remove" aria-label="<?php esc_attr_e( 'Remove file', 'mainwp-simple-performance-for-wordpress' ); ?>" title="<?php esc_attr_e( 'Remove file', 'mainwp-simple-performance-for-wordpress' ); ?>">&times;</button>
							</div>
						</div>
						<div class="mwpspfw-dropzone-error" id="mwpspfw-dropzone-error" role="alert" hidden></div>
					</div>

					<div class="field">
						<label for="mwpspfw-package-url"><?php esc_html_e( 'Package ZIP URL', 'mainwp-simple-performance-for-wordpress' ); ?></label>
						<div class="ui input">
							<input
								type="url"
								id="mwpspfw-package-url"
								name="url"
								value="<?php echo esc_attr( $package['url'] ); ?>"
								placeholder="https://example.com/simple-performance-for-wordpress.zip"
							/>
						</div>
						<p class="mwpspfw-field-description"><?php esc_html_e( 'Populated automatically when you upload a file above, or enter a URL to a ZIP hosted on your own server. Leave empty to install from wordpress.org.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
					</div>

					<button type="submit" class="ui primary button">
						<?php esc_html_e( 'Save Package', 'mainwp-simple-performance-for-wordpress' ); ?>
					</button>
					<span id="mwpspfw-package-status" class="mwpspfw-save-status"></span>
				</form>
			</div>
			
			<!-- Debug Log Section -->
			<div class="ui segment">
				<h3 class="ui header"><?php esc_html_e( 'Debug Log', 'mainwp-simple-performance-for-wordpress' ); ?></h3>
				<p>
					<?php esc_html_e( 'Installation attempts are logged for troubleshooting. Log file:', 'mainwp-simple-performance-for-wordpress' ); ?>
					<code><?php echo esc_html( str_replace( ABSPATH, '', MWPSPFW_Connector::get_log_path() ) ); ?></code>
				</p>
				<button type="button" class="ui button" id="mwpspfw-view-log-btn">
					<?php esc_html_e( 'View Log', 'mainwp-simple-performance-for-wordpress' ); ?>
				</button>
				<button type="button" class="ui button" id="mwpspfw-clear-log-btn">
					<?php esc_html_e( 'Clear Log', 'mainwp-simple-performance-for-wordpress' ); ?>
				</button>
				<span id="mwpspfw-log-status" class="mwpspfw-save-status"></span>
				<pre id="mwpspfw-log-output" class="mwpspfw-log-output" hidden></pre>
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
	 * Returns a list of associative arrays (id, url, name, ...) — MainWP_DB::
	 * get_sites() builds plain arrays, not objects, for every row.
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
	 * The configured SPFW package (currently just a ZIP URL).
	 *
	 * @return array{url:string}
	 */
	private function get_package() {
		$package = get_option( 'mwpspfw_package', array() );
		return array(
			'url' => is_array( $package ) && ! empty( $package['url'] ) ? $package['url'] : '',
		);
	}

	/**
	 * AJAX handler: save the SPFW package URL used by the Install button.
	 */
	public function ajax_save_package() {
		check_ajax_referer( 'mwpspfw_save_package', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage plugin packages.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		update_option( 'mwpspfw_package', array( 'url' => $url ) );

		wp_send_json_success( array( 'url' => $url ) );
	}

	/**
	 * AJAX handler: receive a ZIP file upload, store it in the WordPress
	 * uploads directory, and return its URL. Used by the drag-and-drop zone
	 * on the Extensions page.
	 */
	public function ajax_upload_package() {
		check_ajax_referer( 'mwpspfw_upload_package', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) || ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload plugin packages.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.

		// Validate extension.
		$filename = sanitize_file_name( $file['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'zip' !== $ext ) {
			wp_send_json_error( array( 'message' => __( 'Only ZIP files are accepted.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		// Validate size (10 MB max).
		$max_size = 10 * 1024 * 1024;
		if ( (int) $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'File exceeds the maximum size of 10 MB.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		// Validate upload error.
		if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		// Store in wp-content/uploads/mwpspfw-packages/.
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'mwpspfw-packages';

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Prevent direct PHP execution inside the package directory.
		$htaccess = $target_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, 'Deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$index = $target_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Unique filename to avoid collisions.
		$unique_name = 'spfw-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.zip';
		$target_path = $target_dir . '/' . $unique_name;

		if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_send_json_error( array( 'message' => __( 'Could not save the uploaded file.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$url = trailingslashit( $upload_dir['baseurl'] ) . 'mwpspfw-packages/' . $unique_name;

		wp_send_json_success(
			array(
				'url'      => $url,
				'filename' => $filename,
			)
		);
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

	/**
	 * AJAX handler: retrieve the debug log contents.
	 */
	public function ajax_get_log() {
		check_ajax_referer( 'mwpspfw_debug_log', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to view the debug log.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$lines   = isset( $_POST['lines'] ) ? intval( $_POST['lines'] ) : 100;
		$content = MWPSPFW_Connector::get_log_contents( $lines );

		wp_send_json_success(
			array(
				'content' => $content ? $content : __( 'Log is empty.', 'mainwp-simple-performance-for-wordpress' ),
				'path'    => MWPSPFW_Connector::get_log_path(),
			)
		);
	}

	/**
	 * AJAX handler: clear the debug log.
	 */
	public function ajax_clear_log() {
		check_ajax_referer( 'mwpspfw_debug_log', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to clear the debug log.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$cleared = MWPSPFW_Connector::clear_log();

		if ( $cleared ) {
			wp_send_json_success( array( 'message' => __( 'Debug log cleared.', 'mainwp-simple-performance-for-wordpress' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to clear the debug log.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}
	}
}
