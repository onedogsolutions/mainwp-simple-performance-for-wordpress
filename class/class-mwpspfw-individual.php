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
	 * Render the five-tab settings form.
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
	 * @param array  $settings Full nested settings (for readonly display).
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
			<?php if ( 'toggle' !== $field['type'] && 'readonly' !== $field['type'] ) : ?>
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

				case 'readonly':
					$this->render_readonly_csp( $settings );
					break;
			endswitch;
			?>
			<?php if ( ! empty( $field['description'] ) && 'readonly' !== $field['type'] ) : ?>
				<p class="mwpspfw-field-description"><?php echo esc_html( $field['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the read-only CSP directives summary.
	 *
	 * @param array $settings Full nested settings from the child.
	 */
	private function render_readonly_csp( array $settings ) {
		$hardening    = isset( $settings['hardening'] ) && is_array( $settings['hardening'] ) ? $settings['hardening'] : array();
		$csp_mode     = isset( $hardening['csp_mode'] ) ? $hardening['csp_mode'] : 'builder';
		$csp_policy   = isset( $hardening['csp_policy'] ) ? $hardening['csp_policy'] : '';
		$csp_directives = isset( $hardening['csp_directives'] ) && is_array( $hardening['csp_directives'] ) ? $hardening['csp_directives'] : array();
		?>
		<div class="mwpspfw-readonly-csp">
			<p class="mwpspfw-field-description">
				<?php esc_html_e( 'The CSP directive builder is only available in the child site\'s own admin panel. Below is a read-only summary of the current policy.', 'mainwp-simple-performance-for-wordpress' ); ?>
			</p>
			<?php if ( 'custom' === $csp_mode && ! empty( $csp_policy ) ) : ?>
				<pre class="mwpspfw-csp-preview"><?php echo esc_html( $csp_policy ); ?></pre>
			<?php elseif ( ! empty( $csp_directives ) ) : ?>
				<table class="ui very compact small table mwpspfw-csp-table">
					<tbody>
						<?php foreach ( $csp_directives as $directive => $sources ) : ?>
							<?php if ( ! is_array( $sources ) || empty( $sources ) ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<tr>
								<td class="collapsing"><code><?php echo esc_html( $directive ); ?></code></td>
								<td><code><?php echo esc_html( implode( ' ', $sources ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="mwpspfw-field-description"><?php esc_html_e( 'No CSP directives configured.', 'mainwp-simple-performance-for-wordpress' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler: build a settings payload from the posted form and push it
	 * to the child through the connector, then return what the child actually
	 * persisted so the form can re-hydrate with the post-save truth.
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
			if ( 'readonly' === $field['type'] ) {
				continue;
			}

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

		wp_send_json_success( $result['data'] );
	}
}
