<?php
/**
 * Input sanitization helpers.
 *
 * @package BulkActionsManager
 */

namespace BAM\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Sanitizer
 */
class Sanitizer {

	/**
	 * Sanitize job status.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public static function job_status( $status ) {
		$allowed = array( 'queued', 'running', 'paused', 'completed', 'failed', 'cancelled' );
		return in_array( $status, $allowed, true ) ? $status : 'queued';
	}

	/**
	 * Decode JSON payload safely.
	 *
	 * @param string|null $json JSON string.
	 * @return array<string, mixed>
	 */
	public static function json_decode( $json ) {
		if ( empty( $json ) ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Encode array to JSON.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @return string
	 */
	public static function json_encode( array $data ) {
		return wp_json_encode( $data );
	}
}
