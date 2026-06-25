<?php
/**
 * WordPress Settings API registration.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Register
 */
class Settings_Register {

	const PAGE     = 'bam-settings';
	const GROUP    = 'bam_settings_group';
	const OPTION   = 'bam_settings';
	const UNINSTALL_OPTION = 'bam_drop_data_on_uninstall';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_uninstall' ) );
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section(
			'bam_general',
			__( 'General', 'bulk-actions-manager' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'default_batch_size',
			__( 'Default Batch Size', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_batch_size' ),
			self::PAGE,
			'bam_general'
		);

		add_settings_field(
			'default_processing_mode',
			__( 'Default Processing Mode', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_processing_mode' ),
			self::PAGE,
			'bam_general'
		);

		add_settings_section(
			'bam_undo',
			__( 'Undo Settings', 'bulk-actions-manager' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'enable_undo',
			__( 'Enable Undo', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_enable_undo' ),
			self::PAGE,
			'bam_undo'
		);

		add_settings_field(
			'snapshot_retention_days',
			__( 'Snapshot Retention', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_snapshot_retention' ),
			self::PAGE,
			'bam_undo'
		);

		add_settings_section(
			'bam_logging',
			__( 'Logging', 'bulk-actions-manager' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'enable_logs',
			__( 'Enable Logs', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_enable_logs' ),
			self::PAGE,
			'bam_logging'
		);

		add_settings_field(
			'log_retention_days',
			__( 'Log Retention (days)', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_log_retention' ),
			self::PAGE,
			'bam_logging'
		);

		add_settings_field(
			'max_errors_before_pause',
			__( 'Max Errors Before Pause', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_max_errors' ),
			self::PAGE,
			'bam_logging'
		);
	}

	/**
	 * Register uninstall option (separate from bam_settings array).
	 */
	public static function register_uninstall() {
		register_setting(
			self::GROUP,
			self::UNINSTALL_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => static function ( $value ) {
					return ! empty( $value );
				},
				'default'           => false,
			)
		);

		add_settings_section(
			'bam_uninstall',
			__( 'Data & Uninstall', 'bulk-actions-manager' ),
			static function () {
				echo '<p class="description">' . esc_html__( 'Control whether plugin data is removed when the plugin is deleted from WordPress.', 'bulk-actions-manager' ) . '</p>';
			},
			self::PAGE
		);

		add_settings_field(
			'drop_data_on_uninstall',
			__( 'Drop Plugin Data', 'bulk-actions-manager' ),
			array( __CLASS__, 'field_drop_data_on_uninstall' ),
			self::PAGE,
			'bam_uninstall'
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return Settings::defaults();
		}

		$clean = array(
			'default_batch_size'          => absint( $input['default_batch_size'] ?? 25 ),
			'default_processing_mode'     => sanitize_key( $input['default_processing_mode'] ?? 'ajax' ),
			'enable_undo'                 => ! empty( $input['enable_undo'] ),
			'snapshot_retention_days'     => absint( $input['snapshot_retention_days'] ?? 30 ),
			'enable_logs'                 => ! empty( $input['enable_logs'] ),
			'log_retention_days'          => absint( $input['log_retention_days'] ?? 90 ),
			'max_errors_before_pause'     => min( 1000, absint( $input['max_errors_before_pause'] ?? 10 ) ),
			'require_confirm_destructive' => true,
		);

		if ( ! in_array( $clean['default_batch_size'], array( 10, 25, 50, 100 ), true ) ) {
			$clean['default_batch_size'] = 25;
		}

		if ( ! in_array( $clean['default_processing_mode'], array( 'ajax', 'background' ), true ) ) {
			$clean['default_processing_mode'] = 'ajax';
		}

		if ( ! in_array( $clean['snapshot_retention_days'], array( 0, 7, 30, 90 ), true ) ) {
			$clean['snapshot_retention_days'] = 30;
		}

		return wp_parse_args( $clean, Settings::defaults() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function values() {
		return Settings::all();
	}

	/**
	 * @param string $key Field key.
	 * @return string
	 */
	private static function name( $key ) {
		return self::OPTION . '[' . $key . ']';
	}

	/**
	 * Batch size field.
	 */
	public static function field_batch_size() {
		$settings = self::values();
		?>
		<select name="<?php echo esc_attr( self::name( 'default_batch_size' ) ); ?>" id="default_batch_size">
			<?php foreach ( array( 10, 25, 50, 100 ) as $size ) : ?>
				<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( (int) $settings['default_batch_size'], $size ); ?>><?php echo esc_html( (string) $size ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Processing mode field.
	 */
	public static function field_processing_mode() {
		$settings = self::values();
		?>
		<select name="<?php echo esc_attr( self::name( 'default_processing_mode' ) ); ?>" id="default_processing_mode">
			<option value="ajax" <?php selected( $settings['default_processing_mode'], 'ajax' ); ?>><?php esc_html_e( 'AJAX', 'bulk-actions-manager' ); ?></option>
			<option value="background" <?php selected( $settings['default_processing_mode'], 'background' ); ?>><?php esc_html_e( 'Background Queue', 'bulk-actions-manager' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Enable undo field.
	 */
	public static function field_enable_undo() {
		$settings = self::values();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::name( 'enable_undo' ) ); ?>" value="1" <?php checked( $settings['enable_undo'] ); ?> />
			<?php esc_html_e( 'Enable snapshot-based undo', 'bulk-actions-manager' ); ?>
		</label>
		<?php
	}

	/**
	 * Snapshot retention field.
	 */
	public static function field_snapshot_retention() {
		$settings = self::values();
		$options  = array(
			7  => __( '7 Days', 'bulk-actions-manager' ),
			30 => __( '30 Days', 'bulk-actions-manager' ),
			90 => __( '90 Days', 'bulk-actions-manager' ),
			0  => __( 'Forever', 'bulk-actions-manager' ),
		);
		?>
		<select name="<?php echo esc_attr( self::name( 'snapshot_retention_days' ) ); ?>" id="snapshot_retention_days">
			<?php foreach ( $options as $days => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( (int) $settings['snapshot_retention_days'], $days ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Enable logs field.
	 */
	public static function field_enable_logs() {
		$settings = self::values();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::name( 'enable_logs' ) ); ?>" value="1" <?php checked( $settings['enable_logs'] ); ?> />
			<?php esc_html_e( 'Record audit logs for all jobs', 'bulk-actions-manager' ); ?>
		</label>
		<?php
	}

	/**
	 * Log retention field.
	 */
	public static function field_log_retention() {
		$settings = self::values();
		?>
		<input type="number" name="<?php echo esc_attr( self::name( 'log_retention_days' ) ); ?>" id="log_retention_days" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>" min="1" max="365" class="small-text" />
		<?php
	}

	/**
	 * Max errors field.
	 */
	public static function field_max_errors() {
		$settings = self::values();
		?>
		<input type="number" name="<?php echo esc_attr( self::name( 'max_errors_before_pause' ) ); ?>" id="max_errors_before_pause" value="<?php echo esc_attr( (string) $settings['max_errors_before_pause'] ); ?>" min="0" max="1000" class="small-text" />
		<p class="description"><?php esc_html_e( 'Automatically pause a job when a single processing batch reaches this many failures. Set to 0 to disable auto-pause.', 'bulk-actions-manager' ); ?></p>
		<?php
	}

	/**
	 * Drop data on uninstall field.
	 */
	public static function field_drop_data_on_uninstall() {
		$checked = (bool) get_option( self::UNINSTALL_OPTION, false );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::UNINSTALL_OPTION ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php esc_html_e( 'Drop all plugin data on uninstall', 'bulk-actions-manager' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, deleting the plugin removes database tables, logs, jobs, schedules, and plugin options.', 'bulk-actions-manager' ); ?></p>
		<?php
	}
}
