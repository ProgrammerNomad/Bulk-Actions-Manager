<?php
/**
 * Plugin settings helper.
 *
 * @package BulkActionsManager
 */

namespace BAM;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	const OPTION_KEY = 'bam_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'default_batch_size'            => 25,
			'default_processing_mode'       => 'ajax',
			'enable_undo'                   => true,
			'snapshot_retention_days'       => 30,
			'enable_logs'                   => true,
			'log_retention_days'            => 90,
			'max_errors_before_pause'       => 10,
			'require_confirm_destructive'   => true,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 */
	public static function update( array $settings ) {
		$merged = wp_parse_args( $settings, self::all() );
		update_option( self::OPTION_KEY, $merged );
	}
}
