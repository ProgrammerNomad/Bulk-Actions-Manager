<?php
/**
 * Settings admin page.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin\Pages;

use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Settings
 */
class Page_Settings extends Page_Base {

	/**
	 * Render settings page.
	 */
	public static function render() {
		if ( isset( $_POST['bam_settings_submit'] ) && check_admin_referer( 'bam_settings', 'bam_settings_nonce' ) ) {
			self::save_settings();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'bulk-actions-manager' ) . '</p></div>';
		}

		$settings = Settings::all();
		self::header( __( 'Settings', 'bulk-actions-manager' ) );
		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'bam_settings', 'bam_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'General', 'bulk-actions-manager' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="default_batch_size"><?php esc_html_e( 'Default Batch Size', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select name="default_batch_size" id="default_batch_size">
							<?php foreach ( array( 10, 25, 50, 100 ) as $size ) : ?>
								<option value="<?php echo esc_attr( $size ); ?>" <?php selected( (int) $settings['default_batch_size'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="default_processing_mode"><?php esc_html_e( 'Default Processing Mode', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select name="default_processing_mode" id="default_processing_mode">
							<option value="ajax" <?php selected( $settings['default_processing_mode'], 'ajax' ); ?>><?php esc_html_e( 'AJAX', 'bulk-actions-manager' ); ?></option>
							<option value="background" <?php selected( $settings['default_processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background Queue', 'bulk-actions-manager' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Undo Settings', 'bulk-actions-manager' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Undo', 'bulk-actions-manager' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_undo" value="1" <?php checked( $settings['enable_undo'] ); ?> />
							<?php esc_html_e( 'Enable snapshot-based undo', 'bulk-actions-manager' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="snapshot_retention_days"><?php esc_html_e( 'Snapshot Retention', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<select name="snapshot_retention_days" id="snapshot_retention_days">
							<?php
							$retention_options = array(
								7   => __( '7 Days', 'bulk-actions-manager' ),
								30  => __( '30 Days', 'bulk-actions-manager' ),
								90  => __( '90 Days', 'bulk-actions-manager' ),
								0   => __( 'Forever', 'bulk-actions-manager' ),
							);
							foreach ( $retention_options as $days => $label ) :
								?>
								<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( (int) $settings['snapshot_retention_days'], $days ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Logging', 'bulk-actions-manager' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Logs', 'bulk-actions-manager' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_logs" value="1" <?php checked( $settings['enable_logs'] ); ?> />
							<?php esc_html_e( 'Record audit logs for all jobs', 'bulk-actions-manager' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="log_retention_days"><?php esc_html_e( 'Log Retention (days)', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<input type="number" name="log_retention_days" id="log_retention_days" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>" min="1" max="365" class="small-text" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="max_errors_before_pause"><?php esc_html_e( 'Max Errors Before Pause', 'bulk-actions-manager' ); ?></label></th>
					<td>
						<input type="number" name="max_errors_before_pause" id="max_errors_before_pause" value="<?php echo esc_attr( (string) $settings['max_errors_before_pause'] ); ?>" min="1" max="100" class="small-text" />
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Settings', 'bulk-actions-manager' ), 'primary', 'bam_settings_submit' ); ?>
		</form>
		<?php
		self::footer();
	}

	/**
	 * Save settings from POST.
	 */
	private static function save_settings() {
		$settings = array(
			'default_batch_size'          => absint( $_POST['default_batch_size'] ?? 25 ),
			'default_processing_mode'     => sanitize_text_field( wp_unslash( $_POST['default_processing_mode'] ?? 'ajax' ) ),
			'enable_undo'                 => ! empty( $_POST['enable_undo'] ),
			'snapshot_retention_days'     => absint( $_POST['snapshot_retention_days'] ?? 30 ),
			'enable_logs'                 => ! empty( $_POST['enable_logs'] ),
			'log_retention_days'          => absint( $_POST['log_retention_days'] ?? 90 ),
			'max_errors_before_pause'     => absint( $_POST['max_errors_before_pause'] ?? 10 ),
			'require_confirm_destructive' => true,
		);

		if ( ! in_array( $settings['default_batch_size'], array( 10, 25, 50, 100 ), true ) ) {
			$settings['default_batch_size'] = 25;
		}

		if ( ! in_array( $settings['default_processing_mode'], array( 'ajax', 'background' ), true ) ) {
			$settings['default_processing_mode'] = 'ajax';
		}

		Settings::update( $settings );
	}
}
