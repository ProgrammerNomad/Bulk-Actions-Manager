<?php
/**
 * Filter payload validator.
 *
 * @package BulkActionsManager
 */

namespace BAM\Filters;

defined( 'ABSPATH' ) || exit;

/**
 * Class Filter_Validator
 */
class Filter_Validator {

	/**
	 * Validate filter payload.
	 *
	 * @param array<string, mixed> $payload Filter payload.
	 * @return true|\WP_Error
	 */
	public static function validate( array $payload ) {
		if ( empty( $payload['post_type'] ) || ! is_array( $payload['post_type'] ) ) {
			return new \WP_Error( 'bam_invalid_filter', __( 'Post type is required.', 'bulk-actions-manager' ) );
		}

		foreach ( $payload['post_type'] as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				return new \WP_Error(
					'bam_invalid_post_type',
					sprintf(
						/* translators: %s: post type */
						__( 'Invalid post type: %s', 'bulk-actions-manager' ),
						$post_type
					)
				);
			}
		}

		if ( isset( $payload['conditions'] ) && ! is_array( $payload['conditions'] ) ) {
			return new \WP_Error( 'bam_invalid_conditions', __( 'Conditions must be an array.', 'bulk-actions-manager' ) );
		}

		return true;
	}
}
