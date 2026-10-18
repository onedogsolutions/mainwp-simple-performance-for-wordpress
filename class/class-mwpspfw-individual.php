<?php
/**
 * Per-site "Performance" configuration tab.
 *
 * Registered on the individual child site's own screen (Manage Sites -> site
 * -> Performance) via 'mainwp_getsubpages_sites' in the main activator.
 *
 * @package MainWP/Extensions/SPFW
 */

namespace MainWP\Extensions\SPFW;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MWPSPFW_Individual
 *
 * @package MainWP/Extensions/SPFW
 */
class MWPSPFW_Individual {

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
	 * Render the per-site configuration tab.
	 *
	 * Fetches the child's current settings synchronously; on any failure the
	 * typed error is shown and no form is rendered, so a site we can't
	 * reliably read from is never blindly overwritten.
	 */
	public function render_page() {
		do_action( 'mainwp_pageheader_sites', 'SPFWConfig' );

		MWPSPFW_Admin::enqueue_assets();

		$site_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only site selector, matches MainWP's own convention.

		if ( ! $site_id ) {
			echo '<div class="ui segment"><div class="ui negative message">' . esc_html__( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) . '</div></div>';
			do_action( 'mainwp_pagefooter_sites', 'SPFWConfig' );
			return;
		}

		$result = MWPSPFW_Connector::get_settings( $site_id );

		if ( ! $result['ok'] ) {
			$this->render_error_notice( $result, $site_id );
			do_action( 'mainwp_pagefooter_sites', 'SPFWConfig' );
			return;
		}

		$this->render_form( $site_id, $result['data'] );

		do_action( 'mainwp_pagefooter_sites', 'SPFWConfig' );
	}

	/**
	 * Render one of the three typed connector failure states.
	 *
	 * @param array{error_type:?string,message:string} $result  Connector result.
	 * @param int                                       $site_id MainWP internal website ID.
	 */
	private function render_error_notice( $result, $site_id ) {
		?>
		<div class="ui segment">
			<div class="ui negative message mwpspfw-fetch-error">
				<div class="header"><?php echo esc_html( $result['message'] ); ?></div>
				<?php if ( 'bridge_missing' === $result['error_type'] ) : ?>
					<p><?php esc_html_e( 'Install or update the Simple Performance for WordPress plugin on this child site, then reload this page.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
					<div class="mwpspfw-bridge-missing">
						<button type="button" class="ui green button mwpspfw-install-btn" data-site-id="<?php echo esc_attr( $site_id ); ?>">
							<?php esc_html_e( 'Install SPFW', 'mainwp-simple-performance-for-wordpress' ); ?>
						</button>
					</div>
				<?php elseif ( 'transport' === $result['error_type'] ) : ?>
					<p><?php esc_html_e( 'Confirm the site is online and its MainWP connection is healthy from Manage Sites, then reload this page.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the six-tab settings form.
	 *
	 * @param int   $site_id MainWP internal website ID.
	 * @param array $data    The connector's successful 'get_settings' payload:
	 *                       'settings', 'woocommerce_active', 'version'.
	 */
	private function render_form( $site_id, array $data ) {
		$settings           = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$woocommerce_active = ! empty( $data['woocommerce_active'] );
		$flat               = MWPSPFW_Settings_Schema::flatten( $settings );
		$tabs               = MWPSPFW_Settings_Schema::get_tabs();
		$first              = true;
		?>
		<div class="ui segment">
			<p class="mwpspfw-child-version">
				<?php
				printf(
					/* translators: %s: SPFW plugin version reported by the child site. */
					esc_html__( 'Simple Performance for WordPress %s on this site.', 'mainwp-simple-performance-for-wordpress' ),
					esc_html( isset( $data['version'] ) ? $data['version'] : '?' )
				);
				?>
			</p>

			<div id="mwpspfw-notice" class="ui negative message" hidden></div>

			<form id="mwpspfw-settings-form" class="ui form" data-site-id="<?php echo esc_attr( $site_id ); ?>">
				<input type="hidden" name="action" value="mwpspfw_save_settings" />
				<?php wp_nonce_field( 'mwpspfw_save_settings', 'nonce' ); ?>
				<input type="hidden" name="site_id" value="<?php echo esc_attr( $site_id ); ?>" />

				<div class="ui top attached tabular menu mwpspfw-tabs" role="tablist">
					<?php foreach ( $tabs as $tab_id => $tab ) : ?>
						<?php if ( ! empty( $tab['requires_woocommerce'] ) && ! $woocommerce_active ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<a
							class="item mwpspfw-tab-btn<?php echo $first ? ' active' : ''; ?>"
							data-tab="<?php echo esc_attr( $tab_id ); ?>"
							role="tab"
						><?php echo esc_html( $tab['label'] ); ?></a>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</div>

				<?php
				$first = true;
				foreach ( $tabs as $tab_id => $tab ) :
					if ( ! empty( $tab['requires_woocommerce'] ) && ! $woocommerce_active ) {
						continue;
					}
					?>
					<div
						class="ui bottom attached tab segment mwpspfw-tabpanel<?php echo $first ? ' active' : ''; ?>"
						data-tab="<?php echo esc_attr( $tab_id ); ?>"
						role="tabpanel"
						<?php echo $first ? '' : 'hidden'; ?>
					>
						<?php
						foreach ( $tab['fields'] as $key => $field ) :
							if ( ! empty( $field['requires_woocommerce'] ) && ! $woocommerce_active ) {
								continue;
							}
							$dot_key = $tab['group'] . '.' . $key;
							$value   = isset( $flat[ $dot_key ] ) ? $flat[ $dot_key ] : $field['default'];
							$this->render_field( $dot_key, $field, $value, $settings );
						endforeach;

						if ( 'hardening' === $tab['group'] ) {
							$this->render_file_review( $data, $settings );
						}
						?>
					</div>
					<?php
					$first = false;
				endforeach;
				?>

				<p class="mwpspfw-actions">
					<button type="submit" class="ui big green button" id="mwpspfw-save-btn">
						<?php esc_html_e( 'Save Settings', 'mainwp-simple-performance-for-wordpress' ); ?>
					</button>
					<span id="mwpspfw-save-status" class="mwpspfw-save-status"></span>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one field row.
	 *
	 * @param string $dot_key  Dot-notation settings key (e.g. 'core.disable_emojis').
	 * @param array  $field    Field definition from the schema.
	 * @param mixed  $value    Current value.
	 * @param array  $settings Full nested settings (for the hardening status badges).
	 */
	private function render_field( $dot_key, array $field, $value, array $settings ) {
		$requires_attr = '';
		if ( ! empty( $field['requires'] ) ) {
			$requires_attr = sprintf(
				' data-requires-field="%s" data-requires-value="%s"',
				esc_attr( key( $field['requires'] ) ),
				esc_attr( current( $field['requires'] ) )
			);
		}

		// Form field name uses bracket notation: fields[group][key].
		$parts = explode( '.', $dot_key, 2 );
		$name  = 'fields[' . $parts[0] . '][' . $parts[1] . ']';
		$id    = 'mwpspfw-' . str_replace( '.', '-', $dot_key );
		?>
		<div class="field mwpspfw-field mwpspfw-field-<?php echo esc_attr( $field['type'] ); ?>"<?php echo $requires_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_attr() above. ?>>
			<?php if ( 'toggle' !== $field['type'] ) : ?>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php endif; ?>
			<?php
			switch ( $field['type'] ) :
				case 'toggle':
					?>
					<div class="ui toggle checkbox">
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
						<input
							type="checkbox"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="1"
							<?php checked( '1', $value ); ?>
						/>
						<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					</div>
					<?php
					$this->render_status_badge( $dot_key, $settings );
					break;

				case 'select':
					?>
					<select class="ui dropdown" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
						<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $option_value, (string) $value ); ?>>
								<?php echo esc_html( $option_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					break;

				case 'int':
					?>
					<div class="ui input mwpspfw-int-input">
						<input
							type="number"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
							min="<?php echo esc_attr( $field['min'] ); ?>"
							max="<?php echo esc_attr( $field['max'] ); ?>"
							step="1"
						/>
					</div>
					<?php
					break;

				case 'route_list':
					?>
					<textarea
						class="mwpspfw-route-list"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						rows="4"
					><?php echo esc_textarea( is_array( $value ) ? implode( "\n", $value ) : $value ); ?></textarea>
					<?php
					break;

				case 'text':
					?>
					<div class="ui input">
						<input
							type="<?php echo esc_attr( isset( $field['input_type'] ) ? $field['input_type'] : 'text' ); ?>"
							id="<?php echo esc_attr( $id ); ?>"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( is_string( $value ) ? $value : '' ); ?>"
						/>
					</div>
					<?php
					break;

				case 'textarea':
					?>
					<textarea
						class="mwpspfw-textarea"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						rows="3"
					><?php echo esc_textarea( is_string( $value ) ? $value : '' ); ?></textarea>
					<?php
					break;

				case 'multi_check':
					$checked_values = is_array( $value ) ? array_map( 'strval', $value ) : array();
					?>
					<div class="mwpspfw-multi-check">
						<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
							<label class="mwpspfw-multi-check-item" for="<?php echo esc_attr( $id . '-' . $option_value ); ?>">
								<input
									type="checkbox"
									id="<?php echo esc_attr( $id . '-' . $option_value ); ?>"
									name="<?php echo esc_attr( $name ); ?>[]"
									value="<?php echo esc_attr( $option_value ); ?>"
									<?php checked( in_array( (string) $option_value, $checked_values, true ) ); ?>
								/>
								<?php echo esc_html( $option_label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<?php
					break;

				case 'permissions_policy':
					$features = isset( $field['features'] ) && is_array( $field['features'] ) ? $field['features'] : array();
					$policy   = is_array( $value ) ? $value : array();
					?>
					<div class="mwpspfw-permissions-policy">
						<?php foreach ( $features as $feature ) : ?>
							<?php
							$feature_value = isset( $policy[ $feature ] ) ? $policy[ $feature ] : null;
							$blocked       = is_array( $feature_value );
							$origins       = $blocked ? implode( ' ', $feature_value ) : '';
							$block_id      = $id . '-' . $feature . '-block';
							$origins_id    = $id . '-' . $feature . '-origins';
							?>
							<div class="mwpspfw-permissions-policy-feature">
								<div class="ui toggle checkbox">
									<input type="hidden" name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $feature ); ?>][block]" value="0" />
									<input
										type="checkbox"
										id="<?php echo esc_attr( $block_id ); ?>"
										name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $feature ); ?>][block]"
										value="1"
										<?php checked( $blocked ); ?>
									/>
									<label for="<?php echo esc_attr( $block_id ); ?>"><?php echo esc_html( $feature ); ?></label>
								</div>
								<input
									type="text"
									class="mwpspfw-pp-origins"
									id="<?php echo esc_attr( $origins_id ); ?>"
									name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $feature ); ?>][origins]"
									value="<?php echo esc_attr( $origins ); ?>"
									placeholder="<?php echo esc_attr( "'self' https://example.com" ); ?>"
								/>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					break;

				case 'csp_directives':
					$directives = isset( $field['directives'] ) && is_array( $field['directives'] ) ? $field['directives'] : array();
					$stored     = is_array( $value ) ? $value : array();
					?>
					<div class="mwpspfw-csp-directives">
						<?php foreach ( $directives as $directive ) : ?>
							<?php $tokens = isset( $stored[ $directive ] ) && is_array( $stored[ $directive ] ) ? $stored[ $directive ] : array(); ?>
							<div class="mwpspfw-csp-directive">
								<label for="<?php echo esc_attr( $id . '-' . $directive ); ?>"><code><?php echo esc_html( $directive ); ?></code></label>
								<input
									type="text"
									class="mwpspfw-csp-directive-input"
									id="<?php echo esc_attr( $id . '-' . $directive ); ?>"
									name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $directive ); ?>]"
									value="<?php echo esc_attr( implode( ' ', $tokens ) ); ?>"
									placeholder="<?php echo esc_attr( "'self' data: https:" ); ?>"
								/>
							</div>
						<?php endforeach; ?>
					</div>
					<?php
					break;
			endswitch;
			?>
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<p class="mwpspfw-field-description"><?php echo esc_html( $field['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the .htaccess hardening status badge beside its toggle row.
	 *
	 * The child bridge injects 'hardening_status' / 'uploads_hardening_status'
	 * (and, from SPFW 2.21.0, 'root_hardening_status') at the top level of the settings payload (get_settings only); values and
	 * labels mirror the status chips the child's own admin shows. Kept in sync
	 * with the tone map in rehydrateStatusBadges() in mwpspfw-admin.js.
	 *
	 * @param string $dot_key  Dot-notation settings key being rendered.
	 * @param array  $settings Full nested settings from the child.
	 */
	private function render_status_badge( $dot_key, array $settings ) {
		$badge_keys = array(
			'hardening.plugins_htaccess'        => 'hardening_status',
			'hardening.uploads_htaccess'        => 'uploads_hardening_status',
			'hardening.protect_sensitive_files' => 'root_hardening_status',
		);

		if ( ! isset( $badge_keys[ $dot_key ] ) ) {
			return;
		}

		$status_key = $badge_keys[ $dot_key ];
		$status     = isset( $settings[ $status_key ] ) && is_string( $settings[ $status_key ] ) ? $settings[ $status_key ] : '';

		$statuses = array(
			'ok'          => array( 'positive', __( 'Active', 'mainwp-simple-performance-for-wordpress' ) ),
			'missing'     => array( 'negative', __( 'File missing', 'mainwp-simple-performance-for-wordpress' ) ),
			'altered'     => array( 'warning', __( 'File modified', 'mainwp-simple-performance-for-wordpress' ) ),
			'unsupported' => array( 'basic', __( 'Not available on this server', 'mainwp-simple-performance-for-wordpress' ) ),
			'advisory'    => array( 'basic', __( 'Needs server config', 'mainwp-simple-performance-for-wordpress' ) ),
		);

		if ( '' === $status || ! isset( $statuses[ $status ] ) ) {
			return;
		}

		printf(
			'<span class="ui %1$s label mwpspfw-status-badge" data-status-key="%2$s">%3$s</span>',
			esc_attr( $statuses[ $status ][0] ),
			esc_attr( $status_key ),
			esc_html( $statuses[ $status ][1] )
		);
	}

	/**
	 * Render the File integrity review container at the end of the Hardening
	 * tab. The list itself is drawn by initFileReview() in mwpspfw-admin.js
	 * from the JSON state below, so the first render and every refresh after
	 * Scan now / Mark as verified share one renderer. It holds no named
	 * inputs, so the settings form never posts it.
	 *
	 * @param array $data     The connector's 'get_settings' payload.
	 * @param array $settings Full nested settings from the child.
	 */
	private function render_file_review( array $data, array $settings ) {
		$version   = isset( $data['version'] ) ? (string) $data['version'] : '';
		$supported = MWPSPFW_Connector::supports_integrity( $version ) && isset( $settings['file_monitor'] ) && is_array( $settings['file_monitor'] );
		?>
		<div class="ui segment mwpspfw-file-review" id="mwpspfw-file-review"
			data-supported="<?php echo $supported ? '1' : '0'; ?>"
			data-state="<?php echo esc_attr( $supported ? wp_json_encode( $settings['file_monitor'] ) : '{}' ); ?>">
			<h4 class="ui header"><?php esc_html_e( 'File integrity review', 'mainwp-simple-performance-for-wordpress' ); ?></h4>
			<?php if ( ! $supported ) : ?>
				<div class="ui message">
					<?php
					printf(
						/* translators: %s: minimum SPFW version for file integrity features. */
						esc_html__( 'Reviewing file changes from the dashboard needs Simple Performance for WordPress %s or later on this site.', 'mainwp-simple-performance-for-wordpress' ),
						esc_html( MWPSPFW_Connector::INTEGRITY_MIN_VERSION )
					);
					?>
				</div>
			<?php else : ?>
				<p class="mwpspfw-field-description"><?php esc_html_e( 'Changes stay listed until something verifies them: the update package WordPress downloaded, wordpress.org checksums, matching copies on other managed sites, or you. Scanning never accepts a change on its own.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
				<div class="mwpspfw-file-review-body"></div>
				<p class="mwpspfw-file-review-actions">
					<button type="button" class="ui button mwpspfw-scan-files-btn"><?php esc_html_e( 'Scan now', 'mainwp-simple-performance-for-wordpress' ); ?></button>
					<span class="mwpspfw-save-status mwpspfw-file-review-status" role="status"></span>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Shared gate for the file-review AJAX handlers.
	 *
	 * @return int Site ID (the request ends on failure).
	 */
	private function file_review_site_id() {
		check_ajax_referer( 'mwpspfw_file_review', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this site.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		return $site_id;
	}

	/**
	 * Send a file-review connector result back to the browser.
	 *
	 * @param array $result Connector result.
	 */
	private function send_file_review_result( array $result ) {
		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
					'code'       => isset( $result['code'] ) ? $result['code'] : '',
				)
			);
		}

		$payload = isset( $result['data']['data'] ) && is_array( $result['data']['data'] ) ? $result['data']['data'] : array();

		wp_send_json_success(
			array(
				'file_monitor' => isset( $payload['file_monitor'] ) ? $payload['file_monitor'] : null,
			)
		);
	}

	/**
	 * AJAX handler backing Scan now on the File integrity review block.
	 */
	public function ajax_scan_files() {
		$site_id = $this->file_review_site_id();

		$this->send_file_review_result( MWPSPFW_Connector::scan_files( $site_id ) );
	}

	/**
	 * AJAX handler backing Mark as verified on the File integrity review
	 * block. The child rescans and refuses if the scope's files changed since
	 * the signature was issued.
	 */
	public function ajax_verify_files() {
		$site_id = $this->file_review_site_id();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in file_review_site_id().
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$scope     = isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( $_POST['scope'] ) ) : 'all';
		// phpcs:enable

		if ( 'all' !== $scope && 'uploads' !== $scope && ! preg_match( '/^[A-Za-z0-9._-]+$/', $scope ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown verification scope.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$this->send_file_review_result( MWPSPFW_Connector::verify_files( $site_id, $signature, $scope ) );
	}

	/**
	 * AJAX handler: build a settings payload from the posted form and push it
	 * to the child through the connector, then return what the child actually
	 * persisted so the form can re-hydrate with the post-save truth.
	 *
	 * After a successful update the settings are re-read once: the update
	 * envelope carries the persisted values but not the hardening status
	 * keys, which the bridge injects on get_settings only.
	 */
	public function ajax_save() {
		check_ajax_referer( 'mwpspfw_save_settings', 'nonce' );

		if ( ! function_exists( 'mainwp_current_user_can' ) || ! mainwp_current_user_can( 'dashboard', 'edit_sites' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this site.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		$site_id = isset( $_POST['site_id'] ) ? intval( $_POST['site_id'] ) : 0;
		if ( ! $site_id ) {
			wp_send_json_error( array( 'message' => __( 'No site selected.', 'mainwp-simple-performance-for-wordpress' ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above via check_ajax_referer().
		$posted = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();

		// Build flat dot-notation values from the posted nested structure.
		$flat = array();
		foreach ( MWPSPFW_Settings_Schema::get_fields() as $dot_key => $field ) {
			$group = $field['group'];
			$key   = substr( $dot_key, strlen( $group ) + 1 );

			if ( ! empty( $field['requires_woocommerce'] ) && ! isset( $posted[ $group ][ $key ] ) ) {
				// Field was hidden client-side for a non-WooCommerce site.
				continue;
			}

			if ( isset( $posted[ $group ] ) && is_array( $posted[ $group ] ) && array_key_exists( $key, $posted[ $group ] ) ) {
				$flat[ $dot_key ] = $posted[ $group ][ $key ];
			} else {
				// Toggle not posted means unchecked.
				if ( 'toggle' === $field['type'] ) {
					$flat[ $dot_key ] = '0';
				} else {
					$flat[ $dot_key ] = $field['default'];
				}
			}
		}

		// Mirror the child UI: turning "Disable XML-RPC" off also unchecks the
		// server-level block. The block row is hidden client-side at that point,
		// but hidden conditional inputs still post their values, so the coercion
		// has to happen here.
		if ( empty( $flat['hardening.disable_xmlrpc'] ) || '1' !== (string) $flat['hardening.disable_xmlrpc'] ) {
			$flat['hardening.block_xmlrpc_file'] = '0';
		}

		// Convert flat values to the nested structure SPFW expects.
		$settings = MWPSPFW_Settings_Schema::nest( $flat );

		$result = MWPSPFW_Connector::update_settings( $site_id, $settings );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message'    => $result['message'],
					'error_type' => $result['error_type'],
				)
			);
		}

		// Re-read the settings so the response carries the fresh hardening
		// status keys alongside the persisted values. If the re-read itself
		// fails, fall back to the update envelope rather than failing an
		// already-successful save.
		$refresh = MWPSPFW_Connector::get_settings( $site_id );

		wp_send_json_success( $refresh['ok'] ? $refresh['data'] : $result['data'] );
	}
}
